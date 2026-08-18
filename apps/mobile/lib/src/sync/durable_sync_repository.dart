import 'dart:convert';

import 'contracts.dart';
import 'local_sync_repository.dart';

/// Platform adapters must use an OS-backed encrypted store (Keychain,
/// Keystore, or an equivalent hardware-backed implementation). This boundary
/// deliberately accepts and returns opaque strings; JSON is serialization, not
/// encryption. A fake implementation is appropriate only for tests.
abstract interface class SyncSecureStorage {
  Future<String?> read(String key);

  Future<void> write(String key, String encryptedValue);

  Future<void> delete(String key);
}

/// A durable, partition-scoped repository. Every mutation is persisted before
/// the operation completes, so a process death cannot acknowledge a cursor or
/// command that was not written to secure storage.
final class DurableSyncRepository implements LocalSyncRepository {
  DurableSyncRepository(this._storage, {this.keyPrefix = 'niu.sync.v1.'});

  final SyncSecureStorage _storage;
  final String keyPrefix;
  final Map<String, _DurablePartitionState> _loaded = {};

  String _key(SyncPartition partition) => '$keyPrefix${partition.key}';

  Future<_DurablePartitionState> _state(SyncPartition partition) async {
    final existing = _loaded[partition.key];
    if (existing != null) return existing;
    final String? raw;
    try {
      raw = await _storage.read(_key(partition));
    } catch (_) {
      throw const SyncRepositoryException('secure_storage_unavailable');
    }
    if (raw == null) {
      final fresh = _DurablePartitionState();
      _loaded[partition.key] = fresh;
      return fresh;
    }
    try {
      final decoded = jsonDecode(raw);
      final loaded = _DurablePartitionState.fromJson(decoded);
      _loaded[partition.key] = loaded;
      return loaded;
    } catch (_) {
      throw const SyncRepositoryException('corrupt_sync_state');
    }
  }

  Future<void> _persist(SyncPartition partition, _DurablePartitionState state) async {
    try {
      await _storage.write(_key(partition), jsonEncode(state.toJson()));
    } catch (_) {
      // The in-memory mutation is no longer authoritative if the durable
      // write failed. Force the next operation to reload the last good value.
      _loaded.remove(partition.key);
      throw const SyncRepositoryException('secure_storage_unavailable');
    }
  }

  @override
  Future<int> cursor(SyncPartition partition) async => (await _state(partition)).cursor;

  @override
  Future<void> resetPartition(SyncPartition partition) async {
    try {
      await _storage.delete(_key(partition));
    } catch (_) {
      throw const SyncRepositoryException('secure_storage_unavailable');
    }
    _loaded.remove(partition.key);
  }

  @override
  Future<void> enqueue(SyncPartition partition, OutboxCommand command) async {
    _checkVersion(command.version);
    final state = await _state(partition);
    final existing = state.outbox[command.commandId];
    if (existing != null && existing.fingerprintJson != command.fingerprintJson) {
      throw const SyncRepositoryException('command_fingerprint_mismatch');
    }
    if (existing == null) {
      state.outbox[command.commandId] = command;
      await _persist(partition, state);
    }
  }

  @override
  Future<List<OutboxCommand>> pending(SyncPartition partition, {int limit = 50}) async {
    if (limit < 1) throw const SyncRepositoryException('invalid_limit');
    final state = await _state(partition);
    return state.outbox.values.where((item) => item.status == OutboxStatus.pending).take(limit).toList(growable: false);
  }

  @override
  Future<void> markSending(SyncPartition partition, String commandId) async {
    final state = await _state(partition);
    final command = _required(state, commandId);
    if (command.status != OutboxStatus.pending) throw const SyncRepositoryException('invalid_outbox_transition');
    state.outbox[commandId] = command.copyWith(status: OutboxStatus.sending, attempts: command.attempts + 1);
    await _persist(partition, state);
  }

  @override
  Future<void> resolve(SyncPartition partition, String commandId, OutboxStatus terminalStatus, {String? errorCode}) async {
    if (!const {OutboxStatus.applied, OutboxStatus.rejected, OutboxStatus.conflict, OutboxStatus.retryPending}.contains(terminalStatus)) {
      throw const SyncRepositoryException('non_terminal_resolution');
    }
    final state = await _state(partition);
    final command = _required(state, commandId);
    if (command.status != OutboxStatus.sending) throw const SyncRepositoryException('invalid_outbox_transition');
    state.outbox[commandId] = command.copyWith(status: terminalStatus, lastErrorCode: errorCode);
    await _persist(partition, state);
  }

  @override
  Future<void> recoverInterrupted(SyncPartition partition) async {
    final state = await _state(partition);
    var changed = false;
    for (final entry in state.outbox.entries.toList()) {
      if (entry.value.status == OutboxStatus.sending) {
        state.outbox[entry.key] = entry.value.copyWith(status: OutboxStatus.pending);
        changed = true;
      }
    }
    if (changed) await _persist(partition, state);
  }

  @override
  Future<void> applyChanges(SyncPartition partition, int expectedCursor, List<ProjectionChange> changes) async {
    final state = await _state(partition);
    if (state.cursor != expectedCursor) throw const SyncRepositoryException('cursor_mismatch');
    if (changes.isEmpty) return;
    var next = expectedCursor;
    final staged = Map<String, Map<String, Object?>?>.from(state.projections);
    for (final change in changes) {
      _checkVersion(change.version);
      if (change.cursor <= next) throw const SyncRepositoryException('non_monotonic_cursor');
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
    await _persist(partition, state);
  }

  @override
  Future<Map<String, Object?>?> projection(SyncPartition partition, String entityType, String entityId) async =>
      (await _state(partition)).projections['$entityType:$entityId'];

  OutboxCommand _required(_DurablePartitionState state, String id) {
    final command = state.outbox[id];
    if (command == null) throw const SyncRepositoryException('command_not_found');
    return command;
  }

  void _checkVersion(String version) {
    if (version != syncProtocolVersion) throw const SyncRepositoryException('unsupported_protocol_version');
  }
}

final class _DurablePartitionState {
  int cursor = 0;
  final Map<String, OutboxCommand> outbox = {};
  final Map<String, Map<String, Object?>?> projections = {};

  factory _DurablePartitionState.fromJson(Object? value) {
    if (value is! Map || value['version'] != 1 || value['cursor'] is! int || value['outbox'] is! List || value['projections'] is! Map) {
      throw const FormatException('invalid sync state envelope');
    }
    final state = _DurablePartitionState()..cursor = value['cursor'] as int;
    for (final raw in value['outbox'] as List) {
      if (raw is! Map || raw['commandId'] is! String || raw['type'] is! String || raw['payload'] is! Map || raw['occurredAt'] is! String) throw const FormatException('invalid outbox row');
      final command = OutboxCommand(commandId: raw['commandId'] as String, type: raw['type'] as String, payload: Map<String, Object?>.from(raw['payload'] as Map), occurredAt: DateTime.parse(raw['occurredAt'] as String), version: raw['version'] as String? ?? syncProtocolVersion, status: OutboxStatus.values.byName(raw['status'] as String? ?? 'pending'), attempts: raw['attempts'] as int? ?? 0, lastErrorCode: raw['lastErrorCode'] as String?);
      state.outbox[command.commandId] = command;
    }
    (value['projections'] as Map).forEach((key, value) {
      if (key is! String || (value != null && value is! Map)) throw const FormatException('invalid projection row');
      state.projections[key] = value == null ? null : Map<String, Object?>.from(value as Map);
    });
    return state;
  }

  Map<String, Object?> toJson() => {
        'version': 1,
        'cursor': cursor,
        'outbox': outbox.values.map((command) => {
              'version': command.version, 'commandId': command.commandId, 'type': command.type,
              'occurredAt': command.occurredAt.toUtc().toIso8601String(), 'payload': command.payload,
              'status': command.status.name, 'attempts': command.attempts, 'lastErrorCode': command.lastErrorCode,
            }).toList(growable: false),
        'projections': projections,
      };
}
