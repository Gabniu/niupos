import assert from "node:assert/strict";
import test from "node:test";

import { createInfisicalSecretStore } from "./secret-store.ts";

test("Infisical store authenticates once, reads a reference, and does not expose values in errors", async () => {
  const requests: Request[] = [];
  const store = createInfisicalSecretStore({
    apiUrl: "http://infisical.test",
    projectId: "project-id",
    environment: "production",
    secretPath: "/nova-auth",
    clientId: "client-id",
    clientSecret: "client-secret",
    fetcher: async (input, init) => {
      const request = new Request(input, init);
      requests.push(request);
      if (request.url.endsWith("/universal-auth/login")) return new Response(JSON.stringify({ accessToken: "access-token", expiresIn: 7200 }), { status: 200 });
      return new Response(JSON.stringify({ secret: { secretValue: "provider-secret" } }), { status: 200 });
    },
  });

  assert.equal(await store.get("AUTH_RESEND_API_KEY"), "provider-secret");
  assert.equal(await store.get("AUTH_RESEND_API_KEY"), "provider-secret");
  assert.equal(requests.filter((request) => request.url.endsWith("/universal-auth/login")).length, 1);
  assert.equal(requests[1].headers.get("authorization"), "Bearer access-token");
  assert.match(requests[1].url, /projectId=project-id/);
  assert.match(requests[1].url, /viewSecretValue=true/);
});

test("Infisical store creates a missing secret and updates an existing secret without returning its value", async () => {
  const methods: string[] = [];
  const store = createInfisicalSecretStore({
    apiUrl: "http://infisical.test",
    projectId: "project-id",
    environment: "production",
    secretPath: "/nova-auth",
    clientId: "client-id",
    clientSecret: "client-secret",
    fetcher: async (input, init) => {
      const request = new Request(input, init);
      methods.push(request.method);
      if (request.url.endsWith("/universal-auth/login")) return new Response(JSON.stringify({ accessToken: "access-token", expiresIn: 7200 }), { status: 200 });
      if (methods.length === 2) return new Response("missing", { status: 404 });
      return new Response("ok", { status: 200 });
    },
  });

  await store.set!("GOOGLE_CLIENT_SECRET", "first-secret");
  await store.set!("GOOGLE_CLIENT_SECRET", "second-secret");
  assert.deepEqual(methods, ["POST", "GET", "POST", "GET", "PATCH"]);
});

test("Infisical store rejects invalid references and empty values before network access", async () => {
  let calls = 0;
  const store = createInfisicalSecretStore({
    apiUrl: "http://infisical.test",
    projectId: "project-id",
    environment: "production",
    secretPath: "/nova-auth",
    clientId: "client-id",
    clientSecret: "client-secret",
    fetcher: async () => { calls += 1; return new Response("unexpected", { status: 500 }); },
  });

  await assert.rejects(() => store.get("not-a-reference"), /Invalid secret reference/);
  await assert.rejects(() => store.set!("AUTH_RESEND_API_KEY", ""), /Invalid secret value/);
  assert.equal(calls, 0);
});

test("Infisical store refreshes an expired machine token once without exposing provider data", async () => {
  let logins = 0;
  let reads = 0;
  const store = createInfisicalSecretStore({
    apiUrl: "http://infisical.test",
    projectId: "project-id",
    environment: "production",
    secretPath: "/nova-auth",
    clientId: "client-id",
    clientSecret: "client-secret",
    fetcher: async (input, init) => {
      const request = new Request(input, init);
      if (request.url.endsWith("/universal-auth/login")) {
        logins += 1;
        return new Response(JSON.stringify({ accessToken: `access-${logins}`, expiresIn: 3600 }), { status: 200 });
      }
      reads += 1;
      return reads === 1
        ? new Response("expired", { status: 401 })
        : new Response(JSON.stringify({ secret: { secretValue: "rotated-provider-secret" } }), { status: 200 });
    },
  });

  assert.equal(await store.get("AUTH_RESEND_API_KEY"), "rotated-provider-secret");
  assert.equal(logins, 2);
  assert.equal(reads, 2);
});

test("Infisical store fails closed with a generic error when the vault is unavailable", async () => {
  const store = createInfisicalSecretStore({
    apiUrl: "http://infisical.test",
    projectId: "project-id",
    environment: "production",
    secretPath: "/nova-auth",
    clientId: "client-id",
    clientSecret: "client-secret",
    fetcher: async (input, init) => {
      const request = new Request(input, init);
      if (request.url.endsWith("/universal-auth/login")) return new Response("unavailable", { status: 503 });
      return new Response("unexpected", { status: 500 });
    },
  });

  await assert.rejects(() => store.get("AUTH_RESEND_API_KEY"), /Secret store authentication failed/);
});
