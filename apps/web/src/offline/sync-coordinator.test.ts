import assert from "node:assert/strict";
import test from "node:test";
import { MemorySyncRepository } from "./memory-sync-repository.ts";
import { SyncCoordinator, type SyncTransport } from "./sync-coordinator.ts";
import { SYNC_PROTOCOL_VERSION, type SyncCommandEnvelope } from "./sync-contract.ts";

const partition = { tenantId: "tenant-a", deviceId: "device-a" } as const;
const command: SyncCommandEnvelope = {
  version: SYNC_PROTOCOL_VERSION,
  commandId: "11111111-1111-4111-8111-111111111111",
  type: "sales.finalize.v1",
  occurredAt: "2026-08-08T10:00:00Z",
  payload: { saleId: "sale-1" },
};

test("reconnect pulls gapped pages, flushes the outbox, and pulls command changes", async () => {
  const repository = new MemorySyncRepository();
  await repository.enqueue(partition, command);
  const requests: string[] = [];
  let pullCount = 0;
  const transport: SyncTransport = {
    pull: async (_partition, cursor) => {
      requests.push(`pull:${cursor}`);
      pullCount += 1;
      if (pullCount === 1) {
        return {
          version: "1", cursor: 4, hasMore: true,
          changes: [{ cursor: 4, entityType: "products", entityId: "p1", operation: "upsert", payload: { name: "Milk" }, occurredAt: "2026-08-08T10:00:00Z" }],
        };
      }
      if (pullCount === 2) {
        return {
          version: "1", cursor: 5, hasMore: false,
          changes: [{ cursor: 5, entityType: "products", entityId: "p2", operation: "upsert", payload: { name: "Bread" }, occurredAt: "2026-08-08T10:00:01Z" }],
        };
      }
      return {
        version: "1", cursor: 6, hasMore: false,
        changes: [{ cursor: 6, entityType: "sales", entityId: "sale-1", operation: "upsert", payload: { status: "finalized" }, occurredAt: "2026-08-08T10:00:02Z" }],
      };
    },
    submit: async (_partition, submitted) => {
      requests.push(`submit:${submitted.commandId}`);
      return { commandId: submitted.commandId, status: "applied", attempts: 1 };
    },
  };

  const result = await new SyncCoordinator(repository, transport).reconnect(partition);
  assert.deepEqual(requests, [
    "pull:0", "pull:4", "submit:11111111-1111-4111-8111-111111111111", "pull:5",
  ]);
  assert.deepEqual(result, {
    pagesPulled: 3, changesApplied: 3, commandsSubmitted: 1,
    commandsApplied: 1, commandsRejected: 0, commandsConflicted: 0, commandsRetryPending: 0,
  });
  assert.equal((await repository.cursor(partition)), 6);
  assert.deepEqual((await repository.projection(partition, "sales"))[0]?.value, { status: "finalized" });
  assert.equal((await repository.outbox(partition))[0]?.state, "applied");
});

test("retry_pending returns the command to pending without losing its id", async () => {
  const repository = new MemorySyncRepository();
  await repository.enqueue(partition, command);
  const transport: SyncTransport = {
    pull: async () => ({ version: "1", cursor: 0, hasMore: false, changes: [] }),
    submit: async (_partition, submitted) => ({ commandId: submitted.commandId, status: "retry_pending", attempts: 1, resultCode: "worker_busy" }),
  };
  const result = await new SyncCoordinator(repository, transport).reconnect(partition);
  assert.equal(result.commandsRetryPending, 1);
  assert.equal((await repository.outbox(partition))[0]?.state, "pending");
  assert.equal((await repository.outbox(partition))[0]?.envelope.commandId, command.commandId);
});

test("a transport interruption recovers claimed commands for a later reconnect", async () => {
  const repository = new MemorySyncRepository();
  await repository.enqueue(partition, command);
  const transport: SyncTransport = {
    pull: async () => ({ version: "1", cursor: 0, hasMore: false, changes: [] }),
    submit: async () => { throw new Error("offline"); },
  };
  await assert.rejects(new SyncCoordinator(repository, transport).reconnect(partition), /offline/);
  assert.equal((await repository.outbox(partition))[0]?.state, "pending");
  assert.equal((await repository.outbox(partition))[0]?.attempts, 1);
});
