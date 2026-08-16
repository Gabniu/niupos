import assert from "node:assert/strict";
import test from "node:test";

import { issueSecretStepUpToken, verifySecretStepUpToken } from "./secret-elevation.ts";

test("secret step-up tokens are user-bound, short-lived, and tamper-resistant", () => {
  const previous = process.env.BETTER_AUTH_SECRET;
  process.env.BETTER_AUTH_SECRET = "test-signing-secret";
  try {
    const issuedAt = 1_700_000_000_000;
    const token = issueSecretStepUpToken("admin-1", issuedAt);
    assert.equal(verifySecretStepUpToken(token, "admin-1", issuedAt + 599_000), true);
    assert.equal(verifySecretStepUpToken(token, "other-admin", issuedAt + 599_000), false);
    assert.equal(verifySecretStepUpToken(token, "admin-1", issuedAt + 601_000), false);
    assert.equal(verifySecretStepUpToken(`${token}x`, "admin-1", issuedAt), false);
  } finally {
    if (previous === undefined) delete process.env.BETTER_AUTH_SECRET;
    else process.env.BETTER_AUTH_SECRET = previous;
  }
});
