import type {
  ChangeBatch,
  OutboxEntry,
  OutboxState,
  SyncCommandEnvelope,
  SyncPartition,
} from "./sync-contract.ts";

export type ProjectionRecord = Readonly<{
  collection: string;
  entityId: string;
  value: Readonly<Record<string, unknown>>;
}>;

export interface SyncRepository {
  cursor(partition: SyncPartition): Promise<number>;
  /** Clear only this tenant/device partition after a verified local corruption event. */
  resetPartition(partition: SyncPartition): Promise<void>;
  enqueue(
    partition: SyncPartition,
    envelope: SyncCommandEnvelope,
    now?: Date,
  ): Promise<OutboxEntry>;
  claimPending(
    partition: SyncPartition,
    limit: number,
    now?: Date,
  ): Promise<readonly OutboxEntry[]>;
  settle(
    partition: SyncPartition,
    commandId: string,
    state: Extract<OutboxState, "applied" | "rejected" | "conflict">,
    failureCode?: string,
  ): Promise<void>;
  recoverInterrupted(partition: SyncPartition, retryAt?: Date): Promise<number>;
  applyChanges(partition: SyncPartition, batch: ChangeBatch): Promise<void>;
  projection(
    partition: SyncPartition,
    collection: string,
  ): Promise<readonly ProjectionRecord[]>;
  outbox(partition: SyncPartition): Promise<readonly OutboxEntry[]>;
}
