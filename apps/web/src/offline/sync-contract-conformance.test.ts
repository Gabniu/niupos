import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { SYNC_PROTOCOL_VERSION } from "./sync-contract.ts";

type Schema = {
  $defs: Record<string, {
    required?: string[];
    properties?: Record<string, { const?: string; enum?: string[] }>;
  }>;
};

test("web wire DTO field names and statuses track the shared sync v1 schema", () => {
  const path = new URL("../../../../packages/contracts/schemas/sync-v1.schema.json", import.meta.url);
  const schema = JSON.parse(readFileSync(path, "utf8")) as Schema;
  assert.equal(schema.$defs.command.properties?.version.const, SYNC_PROTOCOL_VERSION);
  assert.deepEqual(schema.$defs.command.required, ["version", "commandId", "type", "occurredAt", "payload"]);
  assert.deepEqual(schema.$defs.receipt.required, ["commandId", "status", "attempts"]);
  assert.deepEqual(schema.$defs.receipt.properties?.status.enum, ["applied", "rejected", "conflict", "retry_pending"]);
  assert.deepEqual(schema.$defs.change.required, ["cursor", "entityType", "entityId", "operation", "payload", "occurredAt"]);
  assert.deepEqual(schema.$defs.changePage.required, ["version", "cursor", "changes", "hasMore"]);
});
