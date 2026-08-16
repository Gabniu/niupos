import 'package:nova_mobile/nova_mobile.dart';
import 'package:test/test.dart';

void main() {
  const a = SyncPartition(tenantId: 'tenant-a', deviceId: 'device-a');
  const b = SyncPartition(tenantId: 'tenant-b', deviceId: 'device-a');

  OutboxCommand command(Map<String, Object?> payload) => OutboxCommand(
        commandId: '018f0000-0000-7000-8000-000000000001',
        type: 'sales.finalize.v1',
        payload: payload,
        occurredAt: DateTime.utc(2026, 8, 8),
      );

  test('partitions cursors and projections by tenant and device', () async {
    final repository = MemorySyncRepository();
    await repository.applyChanges(a, 0, [
      ProjectionChange(
        cursor: 1,
        entityType: 'product',
        entityId: 'p1',
        operation: 'upsert',
        payload: {'name': 'Milk'},
        occurredAt: DateTime.utc(2026, 8, 8),
      ),
    ]);

    expect(await repository.cursor(a), 1);
    expect(await repository.cursor(b), 0);
    expect(await repository.projection(b, 'product', 'p1'), isNull);
  });

  test('accepts gaps and rejects a reordered batch atomically', () async {
    final repository = MemorySyncRepository();
    await repository.applyChanges(a, 0, [
      ProjectionChange(
        cursor: 2,
        entityType: 'product',
        entityId: 'p0',
        operation: 'upsert',
        payload: {'name': 'Flour'},
        occurredAt: DateTime.utc(2026, 8, 8),
      ),
    ]);
    await expectLater(
      repository.applyChanges(a, 2, [
        ProjectionChange(
          cursor: 5,
          entityType: 'product',
          entityId: 'p1',
          operation: 'upsert',
          payload: {'name': 'Milk'},
          occurredAt: DateTime.utc(2026, 8, 8),
        ),
        ProjectionChange(
          cursor: 4,
          entityType: 'product',
          entityId: 'p2',
          operation: 'upsert',
          payload: {'name': 'Bread'},
          occurredAt: DateTime.utc(2026, 8, 8),
        ),
      ]),
      throwsA(isA<SyncRepositoryException>()),
    );
    expect(await repository.cursor(a), 2);
    expect(await repository.projection(a, 'product', 'p1'), isNull);
  });

  test('deduplicates commands and rejects changed replay payloads', () async {
    final repository = MemorySyncRepository();
    await repository.enqueue(a, command({'sale': 'one', 'total': 100}));
    await repository.enqueue(a, command({'total': 100, 'sale': 'one'}));
    expect(await repository.pending(a), hasLength(1));
    await expectLater(
      repository.enqueue(a, command({'sale': 'two', 'total': 100})),
      throwsA(isA<SyncRepositoryException>()),
    );
  });

  test('recovers interrupted sends for retry', () async {
    final repository = MemorySyncRepository();
    await repository.enqueue(a, command({'sale': 'one'}));
    await repository.markSending(a, command({}).commandId);
    expect(await repository.pending(a), isEmpty);
    await repository.recoverInterrupted(a);
    final retried = await repository.pending(a);
    expect(retried.single.status, OutboxStatus.pending);
    expect(retried.single.attempts, 1);
  });
}
