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

const DATABASE_VERSION = 1;
const META = "meta";
const OUTBOX = "outbox";
const PROJECTIONS = "projections";
const pk = ({ tenantId, deviceId }: SyncPartition) =>
  `${tenantId}\u0000${deviceId}`;
const outboxKey = (partition: SyncPartition, id: string) =>
  `${pk(partition)}\u0000${id}`;
const projectionKey = (
  partition: SyncPartition,
  collection: string,
  id: string,
) => `${pk(partition)}\u0000${collection}\u0000${id}`;
const request = <T>(value: IDBRequest<T>) =>
  new Promise<T>((resolve, reject) => {
    value.onsuccess = () => resolve(value.result);
    value.onerror = () =>
      reject(value.error ?? new Error("IndexedDB request failed"));
  });
const complete = (transaction: IDBTransaction) =>
  new Promise<void>((resolve, reject) => {
    transaction.oncomplete = () => resolve();
    transaction.onerror = () =>
      reject(transaction.error ?? new Error("IndexedDB transaction failed"));
    transaction.onabort = () =>
      reject(transaction.error ?? new Error("IndexedDB transaction aborted"));
  });

type StoredOutbox = OutboxEntry & { key: string; partition: string };
type StoredProjection = ProjectionRecord & { key: string; partition: string };
const withoutStorageFields = <T extends { key: string; partition: string }>(
  row: T,
): Omit<T, "key" | "partition"> => {
  const result = { ...row };
  delete (result as Partial<T>).key;
  delete (result as Partial<T>).partition;
  return result;
};

export class IndexedDbSyncRepository implements SyncRepository {
  readonly #database: Promise<IDBDatabase>;

  constructor(
    factory: IDBFactory = globalThis.indexedDB,
    name = "nova-pos-offline",
  ) {
    if (!factory) throw new Error("IndexedDB is unavailable in this runtime");
    this.#database = new Promise((resolve, reject) => {
      const opening = factory.open(name, DATABASE_VERSION);
      opening.onupgradeneeded = () => {
        const db = opening.result;
        if (!db.objectStoreNames.contains(META)) db.createObjectStore(META);
        if (!db.objectStoreNames.contains(OUTBOX)) {
          const store = db.createObjectStore(OUTBOX, { keyPath: "key" });
          store.createIndex("partition", "partition");
        }
        if (!db.objectStoreNames.contains(PROJECTIONS)) {
          const store = db.createObjectStore(PROJECTIONS, { keyPath: "key" });
          store.createIndex("partition", "partition");
        }
      };
      opening.onsuccess = () => resolve(opening.result);
      opening.onerror = () =>
        reject(opening.error ?? new Error("Unable to open IndexedDB"));
      opening.onblocked = () =>
        reject(new Error("IndexedDB upgrade is blocked by another tab"));
    });
  }

  async cursor(partition: SyncPartition): Promise<number> {
    assertPartition(partition);
    const db = await this.#database;
    const tx = db.transaction(META, "readonly");
    return (
      (await request<number | undefined>(
        tx.objectStore(META).get(`cursor\u0000${pk(partition)}`),
      )) ?? 0
    );
  }

  async resetPartition(partition: SyncPartition): Promise<void> {
    assertPartition(partition);
    const db = await this.#database;
    const tx = db.transaction([META, OUTBOX, PROJECTIONS], "readwrite");
    tx.objectStore(META).delete(`cursor\u0000${pk(partition)}`);

    for (const storeName of [OUTBOX, PROJECTIONS] as const) {
      const store = tx.objectStore(storeName);
      const rows = await request<Array<{ key: string; partition: string }>>(
        store.index("partition").getAll(pk(partition)),
      );
      for (const row of rows) store.delete(row.key);
    }

    await complete(tx);
  }

  async enqueue(
    partition: SyncPartition,
    envelope: SyncCommandEnvelope,
    now = new Date(),
  ): Promise<OutboxEntry> {
    assertSupportedEnvelope(envelope);
    assertPartition(partition);
    const db = await this.#database;
    const tx = db.transaction(OUTBOX, "readwrite");
    const store = tx.objectStore(OUTBOX);
    const key = outboxKey(partition, envelope.commandId);
    const prior = await request<StoredOutbox | undefined>(store.get(key));
    if (prior && JSON.stringify(prior.envelope) !== JSON.stringify(envelope)) {
      tx.abort();
      throw new Error(
        "Command id is already associated with a different envelope",
      );
    }
    const entry: OutboxEntry = prior ?? {
      envelope,
      state: "pending",
      attempts: 0,
      nextAttemptAt: now.toISOString(),
    };
    if (!prior)
      store.add({
        ...entry,
        key,
        partition: pk(partition),
      } satisfies StoredOutbox);
    await complete(tx);
    return structuredClone(entry);
  }

  async claimPending(
    partition: SyncPartition,
    limit: number,
    now = new Date(),
  ): Promise<readonly OutboxEntry[]> {
    assertPartition(partition);
    if (!Number.isSafeInteger(limit) || limit < 1)
      throw new Error("Claim limit must be positive");
    const db = await this.#database;
    const tx = db.transaction(OUTBOX, "readwrite");
    const store = tx.objectStore(OUTBOX);
    const rows = await request<StoredOutbox[]>(
      store.index("partition").getAll(pk(partition)),
    );
    const claimed: OutboxEntry[] = [];
    for (const row of rows) {
      if (claimed.length === limit) break;
      if (
        row.state !== "pending" ||
        Date.parse(row.nextAttemptAt) > now.getTime()
      )
        continue;
      const updated: StoredOutbox = {
        ...row,
        state: "sending",
        attempts: row.attempts + 1,
        lastAttemptAt: now.toISOString(),
      };
      store.put(updated);
      claimed.push(withoutStorageFields(updated));
    }
    await complete(tx);
    return claimed;
  }

  async settle(
    partition: SyncPartition,
    commandId: string,
    state: "applied" | "rejected" | "conflict",
    failureCode?: string,
  ): Promise<void> {
    const db = await this.#database;
    const tx = db.transaction(OUTBOX, "readwrite");
    const store = tx.objectStore(OUTBOX);
    const key = outboxKey(partition, commandId);
    const row = await request<StoredOutbox | undefined>(store.get(key));
    if (!row) {
      tx.abort();
      throw new Error("Outbox command not found");
    }
    store.put({ ...row, state, failureCode });
    await complete(tx);
  }

  async recoverInterrupted(
    partition: SyncPartition,
    retryAt = new Date(),
  ): Promise<number> {
    const db = await this.#database;
    const tx = db.transaction(OUTBOX, "readwrite");
    const store = tx.objectStore(OUTBOX);
    const rows = await request<StoredOutbox[]>(
      store.index("partition").getAll(pk(partition)),
    );
    let recovered = 0;
    for (const row of rows)
      if (row.state === "sending") {
        store.put({
          ...row,
          state: "pending",
          nextAttemptAt: retryAt.toISOString(),
        });
        recovered += 1;
      }
    await complete(tx);
    return recovered;
  }

  async applyChanges(
    partition: SyncPartition,
    batch: ChangeBatch,
  ): Promise<void> {
    assertPartition(partition);
    const db = await this.#database;
    const tx = db.transaction([META, PROJECTIONS], "readwrite");
    const meta = tx.objectStore(META);
    const cursorKey = `cursor\u0000${pk(partition)}`;
    const current =
      (await request<number | undefined>(meta.get(cursorKey))) ?? 0;
    assertValidBatch(batch, current);
    const store = tx.objectStore(PROJECTIONS);
    for (const change of batch.changes) {
      const key = projectionKey(partition, change.entityType, change.entityId);
      if (change.operation === "delete") store.delete(key);
      else
        store.put({
          key,
          partition: pk(partition),
          collection: change.entityType,
          entityId: change.entityId,
          value: change.payload!,
        } satisfies StoredProjection);
    }
    meta.put(batch.cursor, cursorKey);
    await complete(tx);
  }

  async projection(
    partition: SyncPartition,
    collection: string,
  ): Promise<readonly ProjectionRecord[]> {
    const db = await this.#database;
    const tx = db.transaction(PROJECTIONS, "readonly");
    const rows = await request<StoredProjection[]>(
      tx.objectStore(PROJECTIONS).index("partition").getAll(pk(partition)),
    );
    return rows
      .filter((row) => row.collection === collection)
      .map(withoutStorageFields)
      .sort((a, b) => a.entityId.localeCompare(b.entityId));
  }

  async outbox(partition: SyncPartition): Promise<readonly OutboxEntry[]> {
    const db = await this.#database;
    const tx = db.transaction(OUTBOX, "readonly");
    const rows = await request<StoredOutbox[]>(
      tx.objectStore(OUTBOX).index("partition").getAll(pk(partition)),
    );
    return rows.map(withoutStorageFields);
  }
}
