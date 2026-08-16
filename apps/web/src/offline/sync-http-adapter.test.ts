import assert from "node:assert/strict";
import test from "node:test";
import { SYNC_PROTOCOL_VERSION, type SyncCommandEnvelope } from "./sync-contract.ts";
import { SyncHttpAdapter, SyncTransportError, parseBootstrap, parseChangePage, type FetchLike } from "./sync-http-adapter.ts";

const partition = { tenantId: "tenant-a", deviceId: "device-a" };
const command: SyncCommandEnvelope = {
  version: SYNC_PROTOCOL_VERSION,
  commandId: "11111111-1111-4111-8111-111111111111",
  type: "sales.finalize.v1",
  occurredAt: "2026-08-08T10:00:00Z",
  payload: { total: 100 },
};

test("submit sends the exact command body and identity only in headers", async () => {
  let captured!: Request;
  const adapter = new SyncHttpAdapter({
    baseUrl: "https://api.example.test/",
    authHeaders: () => ({ Authorization: "Bearer secret" }),
    fetch: async (input, init) => {
      captured = new Request(input, init);
      return Response.json({ commandId: command.commandId, status: "applied", attempts: 1 });
    },
  });
  const receipt = await adapter.submit(partition, command);
  assert.equal(receipt.status, "applied");
  assert.deepEqual(await captured.json(), command);
  assert.equal(captured.headers.get("X-Tenant-ID"), partition.tenantId);
  assert.equal(captured.headers.get("X-Device-ID"), partition.deviceId);
  assert.equal(captured.headers.get("Authorization"), "Bearer secret");
});

test("pull encodes the cursor and accepts strictly increasing cursor gaps", async () => {
  let url = "";
  const adapter = new SyncHttpAdapter({
    baseUrl: "https://api.example.test",
    authHeaders: () => ({}),
    fetch: async (input) => {
      url = String(input);
      return Response.json({
        version: "1", cursor: 8, hasMore: false,
        changes: [{ cursor: 8, entityType: "product", entityId: "p1", operation: "upsert", payload: { name: "Milk" }, occurredAt: "2026-08-08T10:00:00Z" }],
      });
    },
  });
  const page = await adapter.pull(partition, 3, 20);
  assert.equal(page.cursor, 8);
  assert.match(url, /after_cursor=3&limit=20$/);
});

test("bootstrap validates the catalogue and pricing snapshot envelope", async () => {
  const adapter = new SyncHttpAdapter({
    baseUrl: "https://api.example.test",
    authHeaders: () => ({}),
    fetch: async () => Response.json({
      version: "1", cursor: 8, generatedAt: "2026-08-08T10:00:00Z",
      catalogue: { products: [] }, pricing: { prices: [] },
    }),
  });
  const snapshot = await adapter.bootstrap(partition);
  assert.equal(snapshot.cursor, 8);
  assert.deepEqual(snapshot.catalogue, { products: [] });
  assert.throws(() => parseBootstrap({ version: "2", cursor: 0, generatedAt: "2026-08-08T10:00:00Z", catalogue: {}, pricing: {} }), /invalid/);
});

test("network, overload, and invalid protocol failures have stable classifications", async () => {
  const make = (fetch: FetchLike) =>
    new SyncHttpAdapter({ baseUrl: "https://api.example.test", authHeaders: () => ({}), fetch });
  await assert.rejects(make(async () => { throw new TypeError("offline"); }).pull(partition, 0), (error: unknown) =>
    error instanceof SyncTransportError && error.code === "network" && error.retryable);
  await assert.rejects(make(async () => new Response("busy", { status: 503 })).pull(partition, 0), (error: unknown) =>
    error instanceof SyncTransportError && error.code === "http" && error.retryable && error.status === 503);
  await assert.rejects(make(async () => Response.json({ version: "2", cursor: 0, changes: [], hasMore: false })).pull(partition, 0), (error: unknown) =>
    error instanceof SyncTransportError && error.code === "invalid_response" && !error.retryable);
});

test("shape validation rejects extra fields, mismatched receipts, and regressing pages", async () => {
  assert.throws(() => parseChangePage({ version: "1", cursor: 2, changes: [], hasMore: false, tenantId: "leak" }, 1), /shape/);
  const adapter = new SyncHttpAdapter({
    baseUrl: "https://api.example.test",
    authHeaders: () => ({}),
    fetch: async () => Response.json({ commandId: "22222222-2222-4222-8222-222222222222", status: "applied", attempts: 1 }),
  });
  await assert.rejects(adapter.submit(partition, command), /does not match/);
  assert.throws(() => parseChangePage({ version: "1", cursor: 1, changes: [], hasMore: false }, 2), /backwards/);
});
