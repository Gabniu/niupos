import assert from "node:assert/strict";
import test from "node:test";
import { MemorySyncRepository } from "./memory-sync-repository.ts";
import {
  SYNC_PROTOCOL_VERSION,
  type SyncCommandEnvelope,
} from "./sync-contract.ts";

const partition = { tenantId: "tenant-a", deviceId: "device-1" } as const;
const commandId = "11111111-1111-4111-8111-111111111111";
const command = (
  id: string,
  payload = { total: 100 },
): SyncCommandEnvelope => ({
  version: SYNC_PROTOCOL_VERSION,
  commandId: id,
  type: "sales.finalize.v1",
  occurredAt: "2026-08-08T10:00:00.000Z",
  payload,
});

test("outbox deduplicates exact commands and rejects reused ids with changed payload", async () => {
  const repository = new MemorySyncRepository();
  await repository.enqueue(partition, command(commandId));
  await repository.enqueue(partition, command(commandId));
  assert.equal((await repository.outbox(partition)).length, 1);
  await assert.rejects(
    repository.enqueue(partition, command(commandId, { total: 101 })),
    /different envelope/,
  );
});

test("claim, interruption recovery, retry, and terminal state are durable state transitions", async () => {
  const repository = new MemorySyncRepository();
  const now = new Date("2026-08-08T10:01:00.000Z");
  await repository.enqueue(partition, command(commandId), now);
  const [first] = await repository.claimPending(partition, 10, now);
  assert.equal(first.state, "sending");
  assert.equal(first.attempts, 1);
  assert.equal(await repository.recoverInterrupted(partition, now), 1);
  const [retry] = await repository.claimPending(partition, 10, now);
  assert.equal(retry.attempts, 2);
  await repository.settle(
    partition,
    commandId,
    "conflict",
    "inventory_changed",
  );
  assert.deepEqual((await repository.outbox(partition))[0], {
    ...retry,
    state: "conflict",
    failureCode: "inventory_changed",
  });
});

test("projection batches apply atomically with a monotonic cursor", async () => {
  const repository = new MemorySyncRepository();
  await repository.applyChanges(partition, {
    version: SYNC_PROTOCOL_VERSION,
    cursor: 2,
    hasMore: false,
    changes: [
      {
        cursor: 1,
        entityType: "products",
        entityId: "b",
        operation: "upsert",
        payload: { name: "Bread" },
        occurredAt: "2026-08-08T10:00:00Z",
      },
      {
        cursor: 2,
        entityType: "products",
        entityId: "a",
        operation: "upsert",
        payload: { name: "Milk" },
        occurredAt: "2026-08-08T10:00:01Z",
      },
    ],
  });
  assert.equal(await repository.cursor(partition), 2);
  assert.deepEqual(
    (await repository.projection(partition, "products")).map(
      (item) => item.entityId,
    ),
    ["a", "b"],
  );

  await assert.rejects(
    repository.applyChanges(partition, {
      version: SYNC_PROTOCOL_VERSION,
      cursor: 1,
      hasMore: false,
      changes: [],
    }),
    /backwards/,
  );
  assert.equal(await repository.cursor(partition), 2);
  assert.equal((await repository.projection(partition, "products")).length, 2);
});

test("tenant and device partitions do not share cursors, projections, or outbox rows", async () => {
  const repository = new MemorySyncRepository();
  await repository.enqueue(partition, command(commandId));
  await repository.applyChanges(partition, {
    version: SYNC_PROTOCOL_VERSION,
    cursor: 1,
    hasMore: false,
    changes: [
      {
        cursor: 1,
        entityType: "products",
        entityId: "a",
        operation: "upsert",
        payload: { name: "Milk" },
        occurredAt: "2026-08-08T10:00:00Z",
      },
    ],
  });
  const other = { tenantId: "tenant-a", deviceId: "device-2" };
  assert.equal(await repository.cursor(other), 0);
  assert.deepEqual(await repository.projection(other, "products"), []);
  assert.deepEqual(await repository.outbox(other), []);
});

test("schema and malformed change batches fail closed before mutation", async () => {
  const repository = new MemorySyncRepository();
  await assert.rejects(
    repository.enqueue(partition, {
      ...command(commandId),
      version: "2" as "1",
    }),
    /Unsupported/,
  );
  await assert.rejects(
    repository.applyChanges(partition, {
      version: SYNC_PROTOCOL_VERSION,
      cursor: 3,
      hasMore: false,
      changes: [
        {
          cursor: 2,
          entityType: "products",
          entityId: "a",
          operation: "upsert",
          payload: {},
          occurredAt: "2026-08-08T10:00:00Z",
        },
        {
          cursor: 1,
          entityType: "products",
          entityId: "b",
          operation: "delete",
          payload: {},
          occurredAt: "2026-08-08T10:00:01Z",
        },
      ],
    }),
    /increasing/,
  );
  assert.equal(await repository.cursor(partition), 0);
});

test("partition reset clears only the selected tenant and device", async () => {
  const repository = new MemorySyncRepository();
  const other = { tenantId: "tenant-a", deviceId: "device-2" } as const;
  await repository.enqueue(partition, command(commandId));
  await repository.enqueue(
    other,
    command("22222222-2222-4222-8222-222222222222"),
  );
  await repository.applyChanges(partition, {
    version: SYNC_PROTOCOL_VERSION,
    cursor: 4,
    hasMore: false,
    changes: [
      {
        cursor: 4,
        entityType: "products",
        entityId: "a",
        operation: "upsert",
        payload: { name: "Milk" },
        occurredAt: "2026-08-08T10:00:00Z",
      },
    ],
  });

  await repository.resetPartition(partition);

  assert.equal(await repository.cursor(partition), 0);
  assert.deepEqual(await repository.outbox(partition), []);
  assert.deepEqual(await repository.projection(partition, "products"), []);
  assert.equal((await repository.outbox(other)).length, 1);
});
