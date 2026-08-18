export const SYNC_PROTOCOL_VERSION = "1" as const;

export type SyncPartition = Readonly<{
  tenantId: string;
  deviceId: string;
}>;

export type OutboxState =
  | "pending"
  | "sending"
  | "applied"
  | "rejected"
  | "conflict";

export type SyncCommandEnvelope = Readonly<{
  version: typeof SYNC_PROTOCOL_VERSION;
  commandId: string;
  type: string;
  occurredAt: string;
  payload: Readonly<Record<string, unknown>>;
}>;

export type OutboxEntry = Readonly<{
  envelope: SyncCommandEnvelope;
  state: OutboxState;
  attempts: number;
  nextAttemptAt: string;
  lastAttemptAt?: string;
  failureCode?: string;
}>;

export type SyncCommandReceipt = Readonly<{
  commandId: string;
  status: "applied" | "rejected" | "conflict" | "retry_pending";
  attempts: number;
  resultCode?: string;
  resultMessage?: string;
}>;

export type ProjectionChange = Readonly<{
  cursor: number;
  entityType: string;
  entityId: string;
  operation: "upsert" | "delete";
  payload: Readonly<Record<string, unknown>>;
  occurredAt: string;
}>;

export type ChangeBatch = Readonly<{
  version: typeof SYNC_PROTOCOL_VERSION;
  cursor: number;
  changes: readonly ProjectionChange[];
  hasMore: boolean;
}>;

export type SyncBootstrap = Readonly<{
  version: typeof SYNC_PROTOCOL_VERSION;
  cursor: number;
  generatedAt: string;
  catalogue: Readonly<Record<string, unknown>>;
  pricing: Readonly<Record<string, unknown>>;
  page?: Readonly<{
    section: "catalogue" | "pricing";
    collection: string;
    afterId: string | null;
    nextAfterId: string | null;
    hasMore: boolean;
    limit: number;
  }>;
}>;

export function assertPartition(partition: SyncPartition): void {
  if (!partition.tenantId.trim() || !partition.deviceId.trim()) {
    throw new Error("Sync partition requires tenantId and deviceId");
  }
}

export function assertSupportedEnvelope(envelope: SyncCommandEnvelope): void {
  const keys = Object.keys(envelope);
  if (keys.length !== 5 || !["version", "commandId", "type", "occurredAt", "payload"].every((key) => keys.includes(key))) {
    throw new Error("Sync command shape is invalid");
  }
  if (envelope.version !== SYNC_PROTOCOL_VERSION) {
    throw new Error("Unsupported sync protocol version");
  }
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(envelope.commandId) ||
      !/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/.test(envelope.type) || envelope.type.length > 128) {
    throw new Error("Sync command requires commandId and type");
  }
  if (!Number.isFinite(Date.parse(envelope.occurredAt))) {
    throw new Error("Sync command occurredAt must be an ISO timestamp");
  }
  if (typeof envelope.payload !== "object" || envelope.payload === null || Array.isArray(envelope.payload)) {
    throw new Error("Sync command payload must be an object");
  }
}

export function assertValidBatch(batch: ChangeBatch, currentCursor: number): void {
  if (batch.version !== SYNC_PROTOCOL_VERSION) {
    throw new Error("Unsupported sync protocol version");
  }
  if (!Number.isSafeInteger(batch.cursor) || batch.cursor < currentCursor) {
    throw new Error("Sync cursor cannot move backwards");
  }

  let previous = currentCursor;
  for (const change of batch.changes) {
    if (!Number.isSafeInteger(change.cursor) || change.cursor <= previous || change.cursor > batch.cursor) {
      throw new Error("Projection changes must have increasing cursors within the batch cursor");
    }
    if (!change.entityType.trim() || !change.entityId.trim()) {
      throw new Error("Projection change requires entityType and entityId");
    }
    if (!Number.isFinite(Date.parse(change.occurredAt))) {
      throw new Error("Projection change occurredAt must be an ISO timestamp");
    }
    previous = change.cursor;
  }
}
