import 'package:nova_mobile/nova_mobile.dart';
import 'package:test/test.dart';

final class FakeSecureStorage implements SyncSecureStorage {
  final values = <String, String>{};
  bool failWrites = false;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String encryptedValue) async {
    if (failWrites) throw StateError('write failed');
    values[key] = encryptedValue;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

void main() {
  const partition = SyncPartition(tenantId: 'tenant-a', deviceId: 'device-a');
  const otherPartition = SyncPartition(tenantId: 'tenant-b', deviceId: 'device-a');

  OutboxCommand command(String id) => OutboxCommand(
        commandId: id,
        type: 'sales.finalize.v1',
        payload: {'sale': id, 'total': 100},
        occurredAt: DateTime.utc(2026, 8, 18),
      );

  ProjectionChange change(int cursor, String id) => ProjectionChange(
        cursor: cursor,
        entityType: 'product',
        entityId: id,
        operation: 'upsert',
        payload: {'name': 'Milk'},
        occurredAt: DateTime.utc(2026, 8, 18),
      );

  test('round-trips cursors, projections, and outbox across instances', () async {
    final storage = FakeSecureStorage();
    final first = DurableSyncRepository(storage);
    await first.enqueue(partition, command('command-1'));
    await first.markSending(partition, 'command-1');
    await first.resolve(partition, 'command-1', OutboxStatus.applied);
    await first.applyChanges(partition, 0, [change(4, 'p1')]);

    final second = DurableSyncRepository(storage);
    expect(await second.cursor(partition), 4);
    expect(await second.projection(partition, 'product', 'p1'), {'name': 'Milk'});
    expect(await second.pending(partition), isEmpty);
  });

  test('fails closed on corrupt state and requires explicit partition reset', () async {
    final storage = FakeSecureStorage();
    final repository = DurableSyncRepository(storage);
    await repository.enqueue(partition, command('command-1'));
    await repository.applyChanges(partition, 0, [change(2, 'p1')]);

    final key = storage.values.keys.single;
    storage.values[key] = 'not-json';
    final reloaded = DurableSyncRepository(storage);
    await expectLater(reloaded.cursor(partition), throwsA(isA<SyncRepositoryException>()));
    expect((await reloaded.cursor(otherPartition)), 0);
    await reloaded.resetPartition(partition);
    expect(await reloaded.cursor(partition), 0);
    expect(storage.values.containsKey(key), isFalse);
  });

  test('does not lose the last durable state when a write fails', () async {
    final storage = FakeSecureStorage();
    final first = DurableSyncRepository(storage);
    await first.enqueue(partition, command('command-1'));
    storage.failWrites = true;
    await expectLater(
      first.applyChanges(partition, 0, [change(1, 'p1')]),
      throwsA(isA<SyncRepositoryException>()),
    );

    storage.failWrites = false;
    final reloaded = DurableSyncRepository(storage);
    expect(await reloaded.cursor(partition), 0);
    expect((await reloaded.pending(partition)).single.commandId, 'command-1');
    expect(await reloaded.projection(partition, 'product', 'p1'), isNull);
  });

  test('persists interrupted-send recovery and replay protection', () async {
    final storage = FakeSecureStorage();
    final first = DurableSyncRepository(storage);
    await first.enqueue(partition, command('command-1'));
    await first.markSending(partition, 'command-1');

    final second = DurableSyncRepository(storage);
    await second.recoverInterrupted(partition);
    expect((await second.pending(partition)).single.status, OutboxStatus.pending);
    await expectLater(
      second.enqueue(
        partition,
        OutboxCommand(
          commandId: 'command-1',
          type: 'sales.finalize.v1',
          payload: {'sale': 'different', 'total': 100},
          occurredAt: DateTime.utc(2026, 8, 18),
        ),
      ),
      throwsA(isA<SyncRepositoryException>()),
    );
  });
}
