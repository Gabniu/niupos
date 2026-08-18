import type { SyncRepository } from "./sync-repository.ts";
import type {
  ChangeBatch,
  OutboxEntry,
  SyncCommandEnvelope,
  SyncCommandReceipt,
  SyncPartition,
} from "./sync-contract.ts";

export type SyncTransport = Readonly<{
  pull: (
    partition: SyncPartition,
    cursor: number,
    limit?: number,
  ) => Promise<ChangeBatch>;
  submit: (
    partition: SyncPartition,
    command: SyncCommandEnvelope,
  ) => Promise<SyncCommandReceipt>;
}>;

export type SyncRunResult = Readonly<{
  pagesPulled: number;
  changesApplied: number;
  commandsSubmitted: number;
  commandsApplied: number;
  commandsRejected: number;
  commandsConflicted: number;
  commandsRetryPending: number;
}>;

export type SyncCoordinatorOptions = Readonly<{
  pageSize?: number;
  commandBatchSize?: number;
  maxPages?: number;
  now?: () => Date;
}>;

const positiveBound = (value: number, maximum: number, name: string): number => {
  if (!Number.isSafeInteger(value) || value < 1 || value > maximum) {
    throw new Error(`${name} must be between 1 and ${maximum}`);
  }
  return value;
};

/**
 * Coordinates one reconnect pass without owning transport, storage, or domain
 * policy. It is deliberately safe to call again after a network interruption:
 * outbox command ids and server cursors make the second pass idempotent.
 */
export class SyncCoordinator {
  readonly #repository: SyncRepository;
  readonly #transport: SyncTransport;
  readonly #pageSize: number;
  readonly #commandBatchSize: number;
  readonly #maxPages: number;
  readonly #now: () => Date;

  constructor(
    repository: SyncRepository,
    transport: SyncTransport,
    options: SyncCoordinatorOptions = {},
  ) {
    this.#repository = repository;
    this.#transport = transport;
    this.#pageSize = positiveBound(options.pageSize ?? 500, 500, "pageSize");
    this.#commandBatchSize = positiveBound(
      options.commandBatchSize ?? 50,
      500,
      "commandBatchSize",
    );
    this.#maxPages = positiveBound(options.maxPages ?? 1000, 10_000, "maxPages");
    this.#now = options.now ?? (() => new Date());
  }

  async reconnect(partition: SyncPartition): Promise<SyncRunResult> {
    const result = {
      pagesPulled: 0,
      changesApplied: 0,
      commandsSubmitted: 0,
      commandsApplied: 0,
      commandsRejected: 0,
      commandsConflicted: 0,
      commandsRetryPending: 0,
    };

    // A tab or process can disappear after claiming work. Recover it before
    // reading the queue so reconnect never strands a command in `sending`.
    await this.#repository.recoverInterrupted(partition, this.#now());
    await this.#pullUntilCurrent(partition, result);

    try {
      await this.#flushOutbox(partition, result);
    } catch (error) {
      // The current command and any commands claimed beside it are safe to
      // retry with the same ids after a network/transport interruption.
      await this.#repository.recoverInterrupted(partition, this.#now());
      throw error;
    }

    // A successful command can publish a change (for example a finalized
    // sale), so finish with another pull before reporting the pass complete.
    await this.#pullUntilCurrent(partition, result);
    return result;
  }

  async #pullUntilCurrent(
    partition: SyncPartition,
    result: { pagesPulled: number; changesApplied: number },
  ): Promise<void> {
    let cursor = await this.#repository.cursor(partition);
    let pages = 0;
    while (true) {
      pages += 1;
      if (pages > this.#maxPages) {
        throw new Error("Sync pull exceeded its safety page limit");
      }
      const page = await this.#transport.pull(partition, cursor, this.#pageSize);
      if (page.cursor < cursor) {
        throw new Error("Sync pull returned a regressing cursor");
      }
      await this.#repository.applyChanges(partition, page);
      result.pagesPulled += 1;
      result.changesApplied += page.changes.length;
      cursor = page.cursor;
      if (!page.hasMore) return;
      if (page.cursor === (await this.#repository.cursor(partition)) && page.changes.length === 0) {
        throw new Error("Sync pull reported more pages without advancing");
      }
    }
  }

  async #flushOutbox(
    partition: SyncPartition,
    result: {
      commandsSubmitted: number;
      commandsApplied: number;
      commandsRejected: number;
      commandsConflicted: number;
      commandsRetryPending: number;
    },
  ): Promise<void> {
    while (true) {
      const entries = await this.#repository.claimPending(
        partition,
        this.#commandBatchSize,
        this.#now(),
      );
      if (entries.length === 0) return;
      for (const entry of entries) {
        const deferred = await this.#submitEntry(partition, entry, result);
        // Do not spin on a server-deferred command. It is pending again and
        // belongs to the next reconnect/backoff window.
        if (deferred) return;
      }
    }
  }

  async #submitEntry(
    partition: SyncPartition,
    entry: OutboxEntry,
    result: {
      commandsSubmitted: number;
      commandsApplied: number;
      commandsRejected: number;
      commandsConflicted: number;
      commandsRetryPending: number;
    },
  ): Promise<boolean> {
    const receipt = await this.#transport.submit(partition, entry.envelope);
    result.commandsSubmitted += 1;
    if (receipt.status === "retry_pending") {
      result.commandsRetryPending += 1;
      await this.#repository.recoverInterrupted(partition, this.#now());
      return true;
    }
    await this.#repository.settle(
      partition,
      entry.envelope.commandId,
      receipt.status,
      receipt.resultCode,
    );
    if (receipt.status === "applied") result.commandsApplied += 1;
    if (receipt.status === "rejected") result.commandsRejected += 1;
    if (receipt.status === "conflict") result.commandsConflicted += 1;
    return false;
  }
}
