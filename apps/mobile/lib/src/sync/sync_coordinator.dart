import 'contracts.dart';
import 'local_sync_repository.dart';

/// Transport boundary for the frozen v1 sync protocol. An HTTP, gRPC, or
/// platform-specific client can implement this without leaking its response
/// types into the repository or domain code.
abstract interface class SyncTransport {
  Future<SyncChangePage> pull(
    SyncPartition partition,
    int cursor, {
    int limit = 500,
  });

  Future<SyncCommandReceipt> submit(
    SyncPartition partition,
    OutboxCommand command,
  );
}

final class SyncRunResult {
  const SyncRunResult({
    required this.pagesPulled,
    required this.changesApplied,
    required this.commandsSubmitted,
    required this.commandsApplied,
    required this.commandsRejected,
    required this.commandsConflicted,
    required this.commandsRetryPending,
  });

  final int pagesPulled;
  final int changesApplied;
  final int commandsSubmitted;
  final int commandsApplied;
  final int commandsRejected;
  final int commandsConflicted;
  final int commandsRetryPending;
}

/// Runs one bounded, idempotent reconnect pass.
///
/// Storage owns durability and transport owns authentication/retry policy. If
/// a network call fails after a command is marked sending, the command is
/// recovered to pending with the same id before the error is rethrown.
final class SyncCoordinator {
  SyncCoordinator(
    this._repository,
    this._transport, {
    int pageSize = 500,
    int commandBatchSize = 50,
    int maxPages = 1000,
  })  : _pageSize = _bound(pageSize, 500, 'pageSize'),
        _commandBatchSize = _bound(commandBatchSize, 500, 'commandBatchSize'),
        _maxPages = _bound(maxPages, 10000, 'maxPages');

  final LocalSyncRepository _repository;
  final SyncTransport _transport;
  final int _pageSize;
  final int _commandBatchSize;
  final int _maxPages;

  Future<SyncRunResult> reconnect(SyncPartition partition) async {
    var pagesPulled = 0;
    var changesApplied = 0;
    var commandsSubmitted = 0;
    var commandsApplied = 0;
    var commandsRejected = 0;
    var commandsConflicted = 0;
    var commandsRetryPending = 0;

    await _repository.recoverInterrupted(partition);
    final first = await _pullUntilCurrent(partition);
    pagesPulled += first.pages;
    changesApplied += first.changes;

    try {
      while (true) {
        final pending = await _repository.pending(partition, limit: _commandBatchSize);
        if (pending.isEmpty) break;
        for (final command in pending) {
          await _repository.markSending(partition, command.commandId);
          final receipt = await _transport.submit(partition, command);
          commandsSubmitted += 1;
          if (receipt.status == SyncReceiptStatus.retryPending) {
            commandsRetryPending += 1;
            await _repository.recoverInterrupted(partition);
            return SyncRunResult(
              pagesPulled: pagesPulled,
              changesApplied: changesApplied,
              commandsSubmitted: commandsSubmitted,
              commandsApplied: commandsApplied,
              commandsRejected: commandsRejected,
              commandsConflicted: commandsConflicted,
              commandsRetryPending: commandsRetryPending,
            );
          }
          final status = switch (receipt.status) {
            SyncReceiptStatus.applied => OutboxStatus.applied,
            SyncReceiptStatus.rejected => OutboxStatus.rejected,
            SyncReceiptStatus.conflict => OutboxStatus.conflict,
            SyncReceiptStatus.retryPending => OutboxStatus.retryPending,
          };
          await _repository.resolve(partition, command.commandId, status, errorCode: receipt.resultCode);
          if (receipt.status == SyncReceiptStatus.applied) commandsApplied += 1;
          if (receipt.status == SyncReceiptStatus.rejected) commandsRejected += 1;
          if (receipt.status == SyncReceiptStatus.conflict) commandsConflicted += 1;
        }
      }
    } catch (_) {
      await _repository.recoverInterrupted(partition);
      rethrow;
    }

    final finalPull = await _pullUntilCurrent(partition);
    pagesPulled += finalPull.pages;
    changesApplied += finalPull.changes;
    return SyncRunResult(
      pagesPulled: pagesPulled,
      changesApplied: changesApplied,
      commandsSubmitted: commandsSubmitted,
      commandsApplied: commandsApplied,
      commandsRejected: commandsRejected,
      commandsConflicted: commandsConflicted,
      commandsRetryPending: commandsRetryPending,
    );
  }

  Future<({int pages, int changes})> _pullUntilCurrent(SyncPartition partition) async {
    var cursor = await _repository.cursor(partition);
    var pages = 0;
    var changes = 0;
    while (true) {
      pages += 1;
      if (pages > _maxPages) throw const SyncRepositoryException('sync_page_limit_exceeded');
      final page = await _transport.pull(partition, cursor, limit: _pageSize);
      if (page.version != syncProtocolVersion) throw const SyncRepositoryException('unsupported_protocol_version');
      if (page.cursor < cursor) throw const SyncRepositoryException('regressing_sync_cursor');
      if (page.changes.isNotEmpty && page.changes.last.cursor > page.cursor) {
        throw const SyncRepositoryException('change_cursor_exceeds_page_cursor');
      }
      final before = cursor;
      await _repository.applyChanges(partition, cursor, page.changes);
      changes += page.changes.length;
      cursor = await _repository.cursor(partition);
      if (!page.hasMore) return (pages: pages, changes: changes);
      if (page.changes.isEmpty || cursor <= before) {
        throw const SyncRepositoryException('sync_page_did_not_advance');
      }
    }
  }
}

int _bound(int value, int maximum, String name) {
  if (value < 1 || value > maximum) {
    throw ArgumentError.value(value, name, 'must be between 1 and $maximum');
  }
  return value;
}
