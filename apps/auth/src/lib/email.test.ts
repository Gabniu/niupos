import assert from "node:assert/strict";
import test from "node:test";

import { configureAuthEmail, sendAuthEmail } from "./email.ts";

test("Resend delivery uses a server-side bearer key and plain-text payload", async () => {
  const originalFetch = globalThis.fetch;
  let request: Request | undefined;
  try {
    globalThis.fetch = async (input, init) => {
      request = new Request(input, init);
      return new Response(JSON.stringify({ id: "delivery-id" }), { status: 200, headers: { "Content-Type": "application/json" } });
    };
    configureAuthEmail({ provider: "resend", apiKey: "test-resend-key", from: "NOVA <no-reply@example.test>" });
    await sendAuthEmail({ to: "person@example.test", subject: "Verify", text: "Use this link." });
  } finally {
    globalThis.fetch = originalFetch;
    configureAuthEmail({});
  }

  assert.ok(request);
  assert.equal(request.url, "https://api.resend.com/emails");
  assert.equal(request.headers.get("authorization"), "Bearer test-resend-key");
    assert.equal(request.headers.get("user-agent"), "NIU-Auth/1.0");
  assert.deepEqual(await request.json(), {
    from: "NOVA <no-reply@example.test>",
    to: ["person@example.test"],
    subject: "Verify",
    text: "Use this link.",
  });
});

test("Resend delivery fails closed when a key or sender is missing", async () => {
  configureAuthEmail({ provider: "resend" });
  await assert.rejects(() => sendAuthEmail({ to: "person@example.test", subject: "Verify", text: "Use this link." }), /Resend email delivery is not configured/);
  configureAuthEmail({});
});

test("Resend provider errors are reduced to a generic delivery failure", async () => {
  const originalFetch = globalThis.fetch;
  try {
    globalThis.fetch = async () => new Response("provider detail must not escape", { status: 403 });
    configureAuthEmail({ provider: "resend", apiKey: "test-resend-key", from: "NOVA <no-reply@example.test>" });
    await assert.rejects(() => sendAuthEmail({ to: "person@example.test", subject: "Verify", text: "Use this link." }), /Resend email delivery failed/);
  } finally {
    globalThis.fetch = originalFetch;
    configureAuthEmail({});
  }
});
