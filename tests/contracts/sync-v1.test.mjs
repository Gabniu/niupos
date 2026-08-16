import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const schemaUrl = new URL("../../packages/contracts/schemas/sync-v1.schema.json", import.meta.url);

test("sync v1 schema freezes shared command and change vocabulary", async () => {
  const schema = JSON.parse(await readFile(schemaUrl, "utf8"));
  const definitions = schema.$defs;

  assert.deepEqual(definitions.command.required, [
    "version", "commandId", "type", "occurredAt", "payload",
  ]);
  assert.equal(definitions.command.properties.version.const, "1");
  assert.deepEqual(definitions.receipt.properties.status.enum, [
    "applied", "rejected", "conflict", "retry_pending",
  ]);
  assert.deepEqual(definitions.change.properties.operation.enum, ["upsert", "delete"]);
  assert.equal(definitions.changePage.properties.cursor.minimum, 0);
  assert.equal(definitions.changePage.properties.changes.maxItems, 500);
  assert.deepEqual(definitions.bootstrap.required, [
    "version", "cursor", "generatedAt", "catalogue", "pricing",
  ]);
});
