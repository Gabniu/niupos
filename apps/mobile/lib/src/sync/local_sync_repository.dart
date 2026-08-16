import 'contracts.dart';

abstract interface class LocalSyncRepository {
  Future<int> cursor(SyncPartition partition);

  Future<void> enqueue(SyncPartition partition, OutboxCommand command);

  Future<List<OutboxCommand>> pending(SyncPartition partition, {int limit = 50});

  Future<void> markSending(SyncPartition partition, String commandId);

  Future<void> resolve(
    SyncPartition partition,
    String commandId,
    OutboxStatus terminalStatus, {
    String? errorCode,
  });

  Future<void> recoverInterrupted(SyncPartition partition);

  Future<void> applyChanges(
    SyncPartition partition,
    int expectedCursor,
    List<ProjectionChange> changes,
  );

  Future<Map<String, Object?>?> projection(
    SyncPartition partition,
    String entityType,
    String entityId,
  );
}

final class SyncRepositoryException implements Exception {
  const SyncRepositoryException(this.code);

  final String code;

  @override
  String toString() => 'SyncRepositoryException($code)';
}
