import {
  SYNC_PROTOCOL_VERSION,
  assertSupportedEnvelope,
  assertValidBatch,
  type ChangeBatch,
  type ProjectionChange,
  type SyncCommandEnvelope,
  type SyncCommandReceipt,
  type SyncBootstrap,
  type SyncPartition,
} from "./sync-contract.ts";

export type FetchLike = (input: string | URL | Request, init?: RequestInit) => Promise<Response>;

export type SyncHttpAdapterOptions = Readonly<{
  baseUrl: string;
  fetch?: FetchLike;
  authHeaders: () => HeadersInit | Promise<HeadersInit>;
  changesPath?: string;
  commandsPath?: string;
  bootstrapPath?: string;
  tenantHeader?: string;
  deviceHeader?: string;
}>;

export type SyncBootstrapPageRequest = Readonly<{
  section: "catalogue" | "pricing";
  collection: string;
  afterId?: string;
  limit?: number;
  snapshotCursor?: number;
}>;

export class SyncTransportError extends Error {
  readonly code: "network" | "http" | "invalid_response";
  readonly retryable: boolean;
  readonly status?: number;

  constructor(
    message: string,
    code: "network" | "http" | "invalid_response",
    retryable: boolean,
    status?: number,
  ) {
    super(message);
    this.name = "SyncTransportError";
    this.code = code;
    this.retryable = retryable;
    this.status = status;
  }
}

const object = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);
const exactKeys = (value: Record<string, unknown>, required: readonly string[], optional: readonly string[] = []) => {
  const allowed = new Set([...required, ...optional]);
  return required.every((key) => key in value) && Object.keys(value).every((key) => allowed.has(key));
};
const nonempty = (value: unknown, maximum: number) => typeof value === "string" && value.length >= 1 && value.length <= maximum;
const timestamp = (value: unknown) => typeof value === "string" && Number.isFinite(Date.parse(value));
const uuid = (value: unknown) => typeof value === "string" && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);

export function parseCommandReceipt(value: unknown): SyncCommandReceipt {
  if (!object(value) || !exactKeys(value, ["commandId", "status", "attempts"], ["resultCode", "resultMessage"])) {
    throw new SyncTransportError("Command receipt shape is invalid", "invalid_response", false);
  }
  if (!uuid(value.commandId) || !["applied", "rejected", "conflict", "retry_pending"].includes(String(value.status)) ||
      !Number.isSafeInteger(value.attempts) || Number(value.attempts) < 1 ||
      (value.resultCode !== undefined && !nonempty(value.resultCode, 128)) ||
      (value.resultMessage !== undefined && !nonempty(value.resultMessage, 500))) {
    throw new SyncTransportError("Command receipt values are invalid", "invalid_response", false);
  }
  return value as SyncCommandReceipt;
}

const parseChange = (value: unknown): ProjectionChange => {
  if (!object(value) || !exactKeys(value, ["cursor", "entityType", "entityId", "operation", "payload", "occurredAt"]) ||
      !Number.isSafeInteger(value.cursor) || Number(value.cursor) < 1 ||
      !nonempty(value.entityType, 128) || !nonempty(value.entityId, 128) ||
      !["upsert", "delete"].includes(String(value.operation)) || !object(value.payload) || !timestamp(value.occurredAt)) {
    throw new SyncTransportError("Change page contains an invalid change", "invalid_response", false);
  }
  return value as ProjectionChange;
};

export function parseChangePage(value: unknown, currentCursor: number): ChangeBatch {
  if (!object(value) || !exactKeys(value, ["version", "cursor", "changes", "hasMore"]) ||
      value.version !== SYNC_PROTOCOL_VERSION || !Number.isSafeInteger(value.cursor) || Number(value.cursor) < 0 ||
      !Array.isArray(value.changes) || value.changes.length > 500 || typeof value.hasMore !== "boolean") {
    throw new SyncTransportError("Change page shape or protocol version is invalid", "invalid_response", false);
  }
  const page: ChangeBatch = { version: SYNC_PROTOCOL_VERSION, cursor: Number(value.cursor), changes: value.changes.map(parseChange), hasMore: value.hasMore };
  try {
    assertValidBatch(page, currentCursor);
  } catch (error) {
    throw new SyncTransportError(error instanceof Error ? error.message : "Change page is invalid", "invalid_response", false);
  }
  return page;
}

export function parseBootstrap(value: unknown): SyncBootstrap {
  if (!object(value) || !exactKeys(value, ["version", "cursor", "generatedAt", "catalogue", "pricing"], ["page"]) ||
      value.version !== SYNC_PROTOCOL_VERSION || !Number.isSafeInteger(value.cursor) || Number(value.cursor) < 0 ||
      !timestamp(value.generatedAt) || !object(value.catalogue) || !object(value.pricing)) {
    throw new SyncTransportError("Bootstrap shape or protocol version is invalid", "invalid_response", false);
  }
  if (value.page !== undefined) {
    if (!object(value.page) || !exactKeys(value.page, ["section", "collection", "afterId", "nextAfterId", "hasMore", "limit"]) ||
        !["catalogue", "pricing"].includes(String(value.page.section)) || !nonempty(value.page.collection, 64) ||
        (value.page.afterId !== null && !uuid(value.page.afterId)) ||
        (value.page.nextAfterId !== null && !uuid(value.page.nextAfterId)) || typeof value.page.hasMore !== "boolean" ||
        !Number.isSafeInteger(value.page.limit) || Number(value.page.limit) < 1 || Number(value.page.limit) > 500) {
      throw new SyncTransportError("Bootstrap page metadata is invalid", "invalid_response", false);
    }
  }
  return value as SyncBootstrap;
}

const retryableStatus = (status: number) => status === 408 || status === 425 || status === 429 || status >= 500;

export class SyncHttpAdapter {
  readonly #fetch: FetchLike;
  readonly #baseUrl: string;
  readonly #authHeaders: SyncHttpAdapterOptions["authHeaders"];
  readonly #changesPath: string;
  readonly #commandsPath: string;
  readonly #bootstrapPath: string;
  readonly #tenantHeader: string;
  readonly #deviceHeader: string;

  constructor(options: SyncHttpAdapterOptions) {
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis);
    this.#baseUrl = options.baseUrl.replace(/\/$/, "");
    this.#authHeaders = options.authHeaders;
    this.#changesPath = options.changesPath ?? "/api/v1/sync/changes";
    this.#commandsPath = options.commandsPath ?? "/api/v1/sync/commands";
    this.#bootstrapPath = options.bootstrapPath ?? "/api/v1/sync/bootstrap";
    this.#tenantHeader = options.tenantHeader ?? "X-Tenant-ID";
    this.#deviceHeader = options.deviceHeader ?? "X-Device-ID";
  }

  async #headers(partition: SyncPartition): Promise<Headers> {
    const headers = new Headers(await this.#authHeaders());
    headers.set("Accept", "application/json");
    headers.set(this.#tenantHeader, partition.tenantId);
    headers.set(this.#deviceHeader, partition.deviceId);
    return headers;
  }

  async #request(url: string, init: RequestInit): Promise<unknown> {
    let response: Response;
    try {
      response = await this.#fetch(url, init);
    } catch (error) {
      throw new SyncTransportError(error instanceof Error ? error.message : "Network request failed", "network", true);
    }
    if (!response.ok) {
      throw new SyncTransportError(`Sync HTTP request failed with ${response.status}`, "http", retryableStatus(response.status), response.status);
    }
    try {
      return await response.json();
    } catch {
      throw new SyncTransportError("Sync response is not valid JSON", "invalid_response", false, response.status);
    }
  }

  async pull(partition: SyncPartition, cursor: number, limit = 500): Promise<ChangeBatch> {
    if (!Number.isSafeInteger(cursor) || cursor < 0 || !Number.isSafeInteger(limit) || limit < 1 || limit > 500) {
      throw new Error("Pull cursor or limit is invalid");
    }
    const query = new URLSearchParams({ after_cursor: String(cursor), limit: String(limit) });
    const value = await this.#request(`${this.#baseUrl}${this.#changesPath}?${query}`, {
      method: "GET",
      headers: await this.#headers(partition),
    });
    return parseChangePage(value, cursor);
  }

  async bootstrap(partition: SyncPartition, page?: SyncBootstrapPageRequest): Promise<SyncBootstrap> {
    const query = new URLSearchParams();
    if (page) {
      if (!page.section || !page.collection.trim()) throw new Error("Bootstrap page section and collection are required");
      if (page.limit !== undefined && (!Number.isSafeInteger(page.limit) || page.limit < 1 || page.limit > 500)) throw new Error("Bootstrap page limit is invalid");
      if (page.snapshotCursor !== undefined && (!Number.isSafeInteger(page.snapshotCursor) || page.snapshotCursor < 0)) throw new Error("Bootstrap snapshot cursor is invalid");
      query.set("section", page.section);
      query.set("collection", page.collection);
      if (page.afterId) query.set("after_id", page.afterId);
      if (page.limit !== undefined) query.set("limit", String(page.limit));
      if (page.snapshotCursor !== undefined) query.set("snapshot_cursor", String(page.snapshotCursor));
    }
    const suffix = query.toString() ? `?${query}` : "";
    const value = await this.#request(`${this.#baseUrl}${this.#bootstrapPath}${suffix}`, {
      method: "GET",
      headers: await this.#headers(partition),
    });
    return parseBootstrap(value);
  }

  async submit(partition: SyncPartition, command: SyncCommandEnvelope): Promise<SyncCommandReceipt> {
    assertSupportedEnvelope(command);
    const headers = await this.#headers(partition);
    headers.set("Content-Type", "application/json");
    const value = await this.#request(`${this.#baseUrl}${this.#commandsPath}`, {
      method: "POST",
      headers,
      body: JSON.stringify(command),
    });
    const receipt = parseCommandReceipt(value);
    if (receipt.commandId !== command.commandId) {
      throw new SyncTransportError("Command receipt does not match the submitted command", "invalid_response", false);
    }
    return receipt;
  }
}
