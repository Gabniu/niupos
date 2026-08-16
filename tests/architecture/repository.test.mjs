import assert from "node:assert/strict";
import { readFile, readdir, stat } from "node:fs/promises";
import test from "node:test";

const requiredPaths = [
  "apps/api",
  "apps/web",
  "apps/mobile",
  "packages/contracts",
  "infra/compose.yaml",
  "vault/20-architecture/ADR-0001 Full-Stack Modular Platform.md"
];

test("Gate 1 repository boundaries exist", async () => {
  for (const path of requiredPaths) {
    assert.ok(await stat(path), `${path} must exist`);
  }
});

test("ADR-0001 is accepted before framework code is introduced", async () => {
  const adr = await readFile(
    "vault/20-architecture/ADR-0001 Full-Stack Modular Platform.md",
    "utf8"
  );
  assert.match(adr, /status: accepted/);
});

test("runtime policy and web lockfile are committed", async () => {
  const policy = await readFile(
    "vault/20-architecture/ADR-0002 Runtime and Dependency Policy.md",
    "utf8"
  );
  const web = JSON.parse(await readFile("apps/web/package.json", "utf8"));
  assert.match(policy, /status: accepted/);
  assert.equal(web.dependencies.next, "16.3.0");
  assert.equal(web.dependencies.react, "19.2.8");
  assert.equal(web.devDependencies.typescript, "7.0.2");
  assert.ok(await stat("package-lock.json"));
  const api = JSON.parse(await readFile("apps/api/composer.json", "utf8"));
  assert.equal(api.require.php, "^8.3");
  assert.equal(api.require["laravel/framework"], "^13.8");
  assert.ok(await stat("apps/api/composer.lock"));
});

test("local infrastructure contains every mandatory data component", async () => {
  const compose = await readFile("infra/compose.yaml", "utf8");
  for (const service of ["postgres:", "redis:", "rabbitmq:", "elasticsearch:"]) {
    assert.ok(compose.includes(service), `${service} must be present`);
  }
});

test("generated knowledge outputs stay outside the authored corpus", async () => {
  const ignore = await readFile(".graphifyignore", "utf8");
  assert.match(ignore, /^graphify-out\/$/m);
  assert.match(ignore, /^vault\/90-generated\/$/m);
});

async function phpFiles(path) {
  const entries = await readdir(path, { withFileTypes: true });
  const nested = await Promise.all(
    entries.map((entry) => {
      const child = `${path}/${entry.name}`;
      if (entry.isDirectory()) return phpFiles(child);
      return entry.name.endsWith(".php") ? [child] : [];
    })
  );
  return nested.flat();
}

test("Laravel modules cannot import another module's internals", async () => {
  const root = "apps/api/app/Modules";
  for (const file of await phpFiles(root)) {
    const module = file.slice(root.length + 1).split("/")[0];
    const source = await readFile(file, "utf8");
    if (!file.includes("/Database/Migrations/")) {
      assert.match(source, new RegExp(`namespace App\\\\Modules\\\\${module}(?:\\\\|;)`));
    }

    for (const match of source.matchAll(/use App\\Modules\\([^\\]+)\\(Domain|Infrastructure)\\/g)) {
      assert.equal(
        match[1],
        module,
        `${file} imports the internal ${match[2]} layer of ${match[1]}`
      );
    }
  }
});
