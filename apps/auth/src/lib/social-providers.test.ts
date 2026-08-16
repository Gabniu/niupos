import assert from "node:assert/strict";
import test from "node:test";

import { assertGoogleProviderConfiguration, googleProviderReady } from "./social-provider-config.ts";

test("Google sign-in remains valid when disabled without credentials", () => {
  assert.equal(googleProviderReady({ enabled: false, clientId: "", clientSecret: "" }), false);
  assert.doesNotThrow(() =>
    assertGoogleProviderConfiguration({ enabled: false, clientId: "", clientSecret: "" }),
  );
});

test("enabled Google sign-in fails closed when credentials are incomplete", () => {
  assert.equal(googleProviderReady({ enabled: true, clientId: "client-id", clientSecret: "" }), false);
  assert.equal(googleProviderReady({ enabled: true, clientId: "client-id", clientSecret: "client-secret" }), true);
  assert.throws(
    () => assertGoogleProviderConfiguration({ enabled: true, clientId: "client-id", clientSecret: "" }),
    /client credentials are missing/,
  );
  assert.throws(
    () => assertGoogleProviderConfiguration({ enabled: true, clientId: "", clientSecret: "client-secret" }),
    /client credentials are missing/,
  );
});
