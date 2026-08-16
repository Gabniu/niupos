import assert from "node:assert/strict";
import test from "node:test";

test("public sign-up is fail-closed unless explicitly enabled", async () => {
  const previous = process.env.AUTH_ALLOW_PUBLIC_SIGN_UP;
  delete process.env.AUTH_ALLOW_PUBLIC_SIGN_UP;
  const loadedConfig = await import(`./runtime-config.ts?closed=${Date.now()}`);
  assert.equal(loadedConfig.authRuntimeConfig.publicSignUpEnabled, false);
  if (previous !== undefined) process.env.AUTH_ALLOW_PUBLIC_SIGN_UP = previous;
});

test("trusted origins are trimmed and empty entries are discarded", async () => {
  const previous = process.env.AUTH_TRUSTED_ORIGINS;
  process.env.AUTH_TRUSTED_ORIGINS = "https://one.test, ,https://two.test";
  const loadedConfig = await import(`./runtime-config.ts?origins=${Date.now()}`);
  assert.deepEqual(loadedConfig.authRuntimeConfig.trustedOrigins, ["https://one.test", "https://two.test"]);
  if (previous === undefined) delete process.env.AUTH_TRUSTED_ORIGINS;
  else process.env.AUTH_TRUSTED_ORIGINS = previous;
});
