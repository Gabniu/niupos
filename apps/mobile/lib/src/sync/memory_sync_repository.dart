import 'contracts.dart';
import 'local_sync_repository.dart';

final class MemorySyncRepository implements LocalSyncRepository {
  final Map<String, _PartitionState> _partitions = {};

  _PartitionState _state(SyncPartition partition) =>
      _partitions.putIfAbsent(partition.key, _PartitionState.new);

  @override
  Future<int> cursor(SyncPartition partition) async => _state(partition).cursor;

  @override
  Future<void> resetPartition(SyncPartition partition) async {
    _partitions.remove(partition.key);
  }

  @override
  Future<void> enqueue(SyncPartition partition, OutboxCommand command) async {
    _checkVersion(command.version);
    final state = _state(partition);
    final existing = state.outbox[command.commandId];
    if (existing != null && existing.fingerprintJson != command.fingerprintJson) {
      throw const SyncRepositoryException('command_fingerprint_mismatch');
    }
    state.outbox.putIfAbsent(command.commandId, () => command);
  }

  @override
  Future<List<OutboxCommand>> pending(
    SyncPartition partition, {
    int limit = 50,
  }) async {
    if (limit < 1) throw const SyncRepositoryException('invalid_limit');
    return _state(partition)
        .outbox
        .values
        .where((item) => item.status == OutboxStatus.pending)
        .take(limit)
        .toList(growable: false);
  }

  @override
  Future<void> markSending(SyncPartition partition, String commandId) async {
    final state = _state(partition);
    final command = _required(state, commandId);
    if (command.status != OutboxStatus.pending) {
      throw const SyncRepositoryException('invalid_outbox_transition');
    }
    state.outbox[commandId] = command.copyWith(
      status: OutboxStatus.sending,
      attempts: command.attempts + 1,
    );
  }

  @override
  Future<void> resolve(
    SyncPartition partition,
    String commandId,
    OutboxStatus terminalStatus, {
    String? errorCode,
  }) async {
    if (!const {
      OutboxStatus.applied,
      OutboxStatus.rejected,
      OutboxStatus.conflict,
      OutboxStatus.retryPending,
    }.contains(terminalStatus)) {
      throw const SyncRepositoryException('non_terminal_resolution');
    }
    final state = _state(partition);
    final command = _required(state, commandId);
    if (command.status != OutboxStatus.sending) {
      throw const SyncRepositoryException('invalid_outbox_transition');
    }
    state.outbox[commandId] = command.copyWith(
      status: terminalStatus,
      lastErrorCode: errorCode,
    );
  }

  @override
  Future<void> recoverInterrupted(SyncPartition partition) async {
    final state = _state(partition);
    for (final entry in state.outbox.entries.toList()) {
      if (entry.value.status == OutboxStatus.sending) {
        state.outbox[entry.key] = entry.value.copyWith(status: OutboxStatus.pending);
      }
    }
  }

  @override
  Future<void> applyChanges(
    SyncPartition partition,
    int expectedCursor,
    List<ProjectionChange> changes,
  ) async {
    final state = _state(partition);
    if (state.cursor != expectedCursor) {
      throw const SyncRepositoryException('cursor_mismatch');
    }
    if (changes.isEmpty) return;

    var next = expectedCursor;
    final staged = Map<String, Map<String, Object?>?>.from(state.projections);
    for (final change in changes) {
      _checkVersion(change.version);
      if (change.cursor <= next) {
        throw const SyncRepositoryException('non_monotonic_cursor');
      }
      final key = '${change.entityType}:${change.entityId}';
      if (change.operation == 'upsert') {
        staged[key] = Map.unmodifiable(change.payload);
      } else if (change.operation == 'delete') {
        staged.remove(key);
      } else {
        throw const SyncRepositoryException('unknown_change_operation');
      }
      next = change.cursor;
    }
    state.projections
      ..clear()
      ..addAll(staged);
    state.cursor = next;
  }

  @override
  Future<Map<String, Object?>?> projection(
    SyncPartition partition,
    String entityType,
    String entityId,
  ) async =>
      _state(partition).projections['$entityType:$entityId'];

  OutboxCommand _required(_PartitionState state, String commandId) {
    final command = state.outbox[commandId];
    if (command == null) throw const SyncRepositoryException('command_not_found');
    return command;
  }

  void _checkVersion(String version) {
    if (version != syncProtocolVersion) {
      throw const SyncRepositoryException('unsupported_protocol_version');
    }
  }
}

final class _PartitionState {
  int cursor = 0;
  final Map<String, OutboxCommand> outbox = {};
  final Map<String, Map<String, Object?>?> projections = {};
}
