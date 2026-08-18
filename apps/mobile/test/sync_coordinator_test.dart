import 'package:nova_mobile/nova_mobile.dart';
import 'package:test/test.dart';

final class FakeTransport implements SyncTransport {
  FakeTransport(this.pages, this.receipts);

  final List<SyncChangePage> pages;
  final List<SyncCommandReceipt> receipts;
  final submitted = <String>[];
  int pageIndex = 0;
  int receiptIndex = 0;
  Object? submitError;

  @override
  Future<SyncChangePage> pull(SyncPartition partition, int cursor, {int limit = 500}) async => pages[pageIndex++];

  @override
  Future<SyncCommandReceipt> submit(SyncPartition partition, OutboxCommand command) async {
    submitted.add(command.commandId);
    final error = submitError;
    if (error != null) throw error!;
    return receipts[receiptIndex++];
  }
}

void main() {
  const partition = SyncPartition(tenantId: 'tenant-a', deviceId: 'device-a');

  ProjectionChange change(int cursor, String id) => ProjectionChange(
        cursor: cursor,
        entityType: 'product',
        entityId: id,
        operation: 'upsert',
        payload: {'name': id},
        occurredAt: DateTime.utc(2026, 8, 18),
      );

  OutboxCommand command() => OutboxCommand(
        commandId: 'command-1',
        type: 'sales.finalize.v1',
        payload: {'saleId': 'sale-1'},
        occurredAt: DateTime.utc(2026, 8, 18),
      );

  SyncCommandReceipt receipt(SyncReceiptStatus status) => SyncCommandReceipt(
        commandId: 'command-1',
        status: status,
        attempts: 1,
      );

  SyncChangePage page(List<ProjectionChange> changes, bool hasMore) => SyncChangePage(
        cursor: changes.isEmpty ? 0 : changes.last.cursor,
        changes: changes,
        hasMore: hasMore,
      );

  test('reconnect pulls, submits, and performs a final change pull', () async {
    final repository = MemorySyncRepository();
    await repository.enqueue(partition, command());
    final transport = FakeTransport(
      [page([change(2, 'p1')], false), page([change(4, 'sale-1')], false)],
      [receipt(SyncReceiptStatus.applied)],
    );

    final result = await SyncCoordinator(repository, transport).reconnect(partition);

    expect(result.pagesPulled, 2);
    expect(result.changesApplied, 2);
    expect(result.commandsSubmitted, 1);
    expect(result.commandsApplied, 1);
    expect(await repository.cursor(partition), 4);
    expect(await repository.projection(partition, 'product', 'p1'), {'name': 'p1'});
    expect(await repository.pending(partition), isEmpty);
  });

  test('retry-pending returns the command to pending without spinning', () async {
    final repository = MemorySyncRepository();
    await repository.enqueue(partition, command());
    final transport = FakeTransport(
      [page([], false)],
      [receipt(SyncReceiptStatus.retryPending)],
    );

    final result = await SyncCoordinator(repository, transport).reconnect(partition);

    expect(result.commandsRetryPending, 1);
    expect(transport.submitted, ['command-1']);
    expect((await repository.pending(partition)).single.status, OutboxStatus.pending);
  });

  test('transport failure recovers a claimed command for a later pass', () async {
    final repository = MemorySyncRepository();
    await repository.enqueue(partition, command());
    final transport = FakeTransport([page([], false)], const []);
    transport.submitError = StateError('offline');

    await expectLater(
      SyncCoordinator(repository, transport).reconnect(partition),
      throwsA(isA<StateError>()),
    );
    expect((await repository.pending(partition)).single.status, OutboxStatus.pending);
    expect((await repository.pending(partition)).single.attempts, 1);
  });

  test('rejects a page that claims more work without cursor progress', () async {
    final repository = MemorySyncRepository();
    final transport = FakeTransport([page([], true)], const []);

    await expectLater(
      SyncCoordinator(repository, transport).reconnect(partition),
      throwsA(isA<SyncRepositoryException>()),
    );
    expect((await repository.cursor(partition)), 0);
  });
}
