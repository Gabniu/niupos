import {
  assertPartition,
  assertSupportedEnvelope,
  assertValidBatch,
  type ChangeBatch,
  type OutboxEntry,
  type SyncCommandEnvelope,
  type SyncPartition,
} from "./sync-contract.ts";
import type { ProjectionRecord, SyncRepository } from "./sync-repository.ts";

const partitionKey = ({ tenantId, deviceId }: SyncPartition) =>
  `${tenantId}\u0000${deviceId}`;
const projectionKey = (collection: string, entityId: string) =>
  `${collection}\u0000${entityId}`;
const copy = <T>(value: T): T => structuredClone(value);

type PartitionData = {
  cursor: number;
  outbox: Map<string, OutboxEntry>;
  projections: Map<string, ProjectionRecord>;
};

export class MemorySyncRepository implements SyncRepository {
  readonly #partitions = new Map<string, PartitionData>();

  #data(partition: SyncPartition): PartitionData {
    assertPartition(partition);
    const key = partitionKey(partition);
    let data = this.#partitions.get(key);
    if (!data) {
      data = { cursor: 0, outbox: new Map(), projections: new Map() };
      this.#partitions.set(key, data);
    }
    return data;
  }

  async cursor(partition: SyncPartition): Promise<number> {
    return this.#data(partition).cursor;
  }

  async resetPartition(partition: SyncPartition): Promise<void> {
    assertPartition(partition);
    this.#partitions.delete(partitionKey(partition));
  }

  async enqueue(
    partition: SyncPartition,
    envelope: SyncCommandEnvelope,
    now = new Date(),
  ): Promise<OutboxEntry> {
    assertSupportedEnvelope(envelope);
    const data = this.#data(partition);
    const prior = data.outbox.get(envelope.commandId);
    if (prior) {
      if (JSON.stringify(prior.envelope) !== JSON.stringify(envelope)) {
        throw new Error(
          "Command id is already associated with a different envelope",
        );
      }
      return copy(prior);
    }
    const entry: OutboxEntry = {
      envelope: copy(envelope),
      state: "pending",
      attempts: 0,
      nextAttemptAt: now.toISOString(),
    };
    data.outbox.set(envelope.commandId, entry);
    return copy(entry);
  }

  async claimPending(
    partition: SyncPartition,
    limit: number,
    now = new Date(),
  ): Promise<readonly OutboxEntry[]> {
    if (!Number.isSafeInteger(limit) || limit < 1)
      throw new Error("Claim limit must be positive");
    const data = this.#data(partition);
    const claimed: OutboxEntry[] = [];
    for (const [id, entry] of data.outbox) {
      if (claimed.length === limit) break;
      if (
        entry.state !== "pending" ||
        Date.parse(entry.nextAttemptAt) > now.getTime()
      )
        continue;
      const sending: OutboxEntry = {
        ...entry,
        state: "sending",
        attempts: entry.attempts + 1,
        lastAttemptAt: now.toISOString(),
      };
      data.outbox.set(id, sending);
      claimed.push(copy(sending));
    }
    return claimed;
  }

  async settle(
    partition: SyncPartition,
    commandId: string,
    state: "applied" | "rejected" | "conflict",
    failureCode?: string,
  ): Promise<void> {
    const data = this.#data(partition);
    const current = data.outbox.get(commandId);
    if (!current) throw new Error("Outbox command not found");
    data.outbox.set(commandId, { ...current, state, failureCode });
  }

  async recoverInterrupted(
    partition: SyncPartition,
    retryAt = new Date(),
  ): Promise<number> {
    const data = this.#data(partition);
    let recovered = 0;
    for (const [id, entry] of data.outbox) {
      if (entry.state !== "sending") continue;
      data.outbox.set(id, {
        ...entry,
        state: "pending",
        nextAttemptAt: retryAt.toISOString(),
      });
      recovered += 1;
    }
    return recovered;
  }

  async applyChanges(
    partition: SyncPartition,
    batch: ChangeBatch,
  ): Promise<void> {
    const data = this.#data(partition);
    assertValidBatch(batch, data.cursor);
    const projections = new Map(data.projections);
    for (const change of batch.changes) {
      const key = projectionKey(change.entityType, change.entityId);
      if (change.operation === "delete") projections.delete(key);
      else
        projections.set(key, {
          collection: change.entityType,
          entityId: change.entityId,
          value: copy(change.payload!),
        });
    }
    data.projections = projections;
    data.cursor = batch.cursor;
  }

  async projection(
    partition: SyncPartition,
    collection: string,
  ): Promise<readonly ProjectionRecord[]> {
    return [...this.#data(partition).projections.values()]
      .filter((record) => record.collection === collection)
      .map(copy)
      .sort((left, right) => left.entityId.localeCompare(right.entityId));
  }

  async outbox(partition: SyncPartition): Promise<readonly OutboxEntry[]> {
    return [...this.#data(partition).outbox.values()].map(copy);
  }
}
