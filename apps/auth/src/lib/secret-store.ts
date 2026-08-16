export type SecretStore = {
  get(reference: string): Promise<string | undefined>;
  set?(reference: string, value: string): Promise<void>;
};

const timeoutMs = 5_000;

function assertReference(reference: string): void {
  if (!/^[A-Z][A-Z0-9_]{2,100}$/.test(reference)) throw new Error("Invalid secret reference.");
}

function envSecretStore(): SecretStore {
  return {
    async get(reference) {
      assertReference(reference);
      return process.env[reference] || undefined;
    },
    async set(reference, value) {
      assertReference(reference);
      if (!value || value.length > 16_384) throw new Error("Invalid secret value.");
      throw new Error("The environment secret store is read-only at runtime.");
    },
  };
}

type InfisicalSecretResponse = { secret?: { secretValue?: unknown } };
type InfisicalTokenResponse = { accessToken?: unknown; expiresIn?: unknown };

export function createInfisicalSecretStore(configuration: {
  apiUrl: string;
  projectId: string;
  environment: string;
  secretPath: string;
  clientId: string;
  clientSecret: string;
  organizationSlug?: string;
  fetcher?: typeof fetch;
}): SecretStore {
  const fetcher = configuration.fetcher ?? fetch;
  let accessToken: string | undefined;
  let accessTokenExpiresAt = 0;

  async function token(): Promise<string> {
    if (accessToken && Date.now() < accessTokenExpiresAt - 30_000) return accessToken;
    const response = await fetcher(`${configuration.apiUrl.replace(/\/$/, "")}/api/v1/auth/universal-auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        clientId: configuration.clientId,
        clientSecret: configuration.clientSecret,
        ...(configuration.organizationSlug ? { organizationSlug: configuration.organizationSlug } : {}),
      }),
      signal: AbortSignal.timeout(timeoutMs),
    });
    if (!response.ok) throw new Error("Secret store authentication failed.");
    const body = await response.json() as InfisicalTokenResponse;
    if (typeof body.accessToken !== "string" || typeof body.expiresIn !== "number") throw new Error("Secret store authentication response was invalid.");
    accessToken = body.accessToken;
    accessTokenExpiresAt = Date.now() + Math.max(60, body.expiresIn) * 1_000;
    return accessToken;
  }

  function secretUrl(reference: string): string {
    assertReference(reference);
    const url = new URL(`${configuration.apiUrl.replace(/\/$/, "")}/api/v4/secrets/${encodeURIComponent(reference)}`);
    url.searchParams.set("projectId", configuration.projectId);
    url.searchParams.set("environment", configuration.environment);
    url.searchParams.set("secretPath", configuration.secretPath);
    url.searchParams.set("type", "shared");
    return url.toString();
  }

  async function request(url: string, init: RequestInit = {}): Promise<Response> {
    for (let attempt = 0; attempt < 2; attempt += 1) {
      const response = await fetcher(url, {
        ...init,
        headers: { Authorization: `Bearer ${await token()}`, ...(init.headers ?? {}) },
        signal: AbortSignal.timeout(timeoutMs),
      });
      if (response.status !== 401 || attempt === 1) return response;
      accessToken = undefined;
      accessTokenExpiresAt = 0;
    }
    throw new Error("Secret store request failed.");
  }

  return {
    async get(reference) {
      const response = await request(`${secretUrl(reference)}&viewSecretValue=true&expandSecretReferences=false`);
      if (response.status === 404) return undefined;
      if (!response.ok) throw new Error("Secret store read failed.");
      const body = await response.json() as InfisicalSecretResponse;
      return typeof body.secret?.secretValue === "string" ? body.secret.secretValue : undefined;
    },
    async set(reference, value) {
      assertReference(reference);
      if (!value || value.length > 16_384) throw new Error("Invalid secret value.");
      const url = secretUrl(reference);
      const existing = await request(`${url}&viewSecretValue=false`);
      const payload = JSON.stringify({
        projectId: configuration.projectId,
        environment: configuration.environment,
        secretPath: configuration.secretPath,
        type: "shared",
        secretValue: value,
      });
      const response = existing.status === 404
        ? await request(url, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload })
        : await request(url, { method: "PATCH", headers: { "Content-Type": "application/json" }, body: payload });
      if (!response.ok) throw new Error("Secret store write failed.");
    },
  };
}

export function createSecretStoreFromEnvironment(): SecretStore {
  if (process.env.AUTH_SECRET_STORE !== "infisical") return envSecretStore();
  const required = [
    ["INFISICAL_API_URL", process.env.INFISICAL_API_URL],
    ["INFISICAL_PROJECT_ID", process.env.INFISICAL_PROJECT_ID],
    ["INFISICAL_ENVIRONMENT", process.env.INFISICAL_ENVIRONMENT],
    ["INFISICAL_CLIENT_ID", process.env.INFISICAL_CLIENT_ID],
    ["INFISICAL_CLIENT_SECRET", process.env.INFISICAL_CLIENT_SECRET],
  ].filter(([, value]) => !value).map(([name]) => name);
  if (required.length > 0) throw new Error(`Missing Infisical configuration: ${required.join(", ")}`);
  return createInfisicalSecretStore({
    apiUrl: process.env.INFISICAL_API_URL!,
    projectId: process.env.INFISICAL_PROJECT_ID!,
    environment: process.env.INFISICAL_ENVIRONMENT!,
    secretPath: process.env.INFISICAL_SECRET_PATH || "/nova-auth",
    clientId: process.env.INFISICAL_CLIENT_ID!,
    clientSecret: process.env.INFISICAL_CLIENT_SECRET!,
    organizationSlug: process.env.INFISICAL_ORG_SLUG,
  });
}

let configuredStore: SecretStore | undefined;
export function secretStore(): SecretStore {
  configuredStore ??= createSecretStoreFromEnvironment();
  return configuredStore;
}

export function resetSecretStoreForTests(): void {
  configuredStore = undefined;
}
