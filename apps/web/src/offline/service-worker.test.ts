import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

test("service worker keeps API data out of the shell cache", async () => {
  const source = await readFile(
    new URL("../../public/sw.js", import.meta.url),
    "utf8",
  );

  assert.match(source, /url\.pathname\.startsWith\("\/api\/"\)/);
  assert.match(source, /request\.mode === "navigate"/);
  assert.match(source, /caches\.match\(OFFLINE_URL\)/);
  assert.match(source, /nova-pos-shell-v2/);
});
