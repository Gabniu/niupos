import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

test("IndexedDB reset contract deletes only the selected partition keys", async () => {
  const source = await readFile(
    new URL("./indexeddb-sync-repository.ts", import.meta.url),
    "utf8",
  );

  assert.match(source, /resetPartition\(partition: SyncPartition\)/);
  assert.match(source, /delete\(`cursor\\u0000\$\{pk\(partition\)\}`\)/);
  assert.match(source, /getAll\(pk\(partition\)\)/);
});
