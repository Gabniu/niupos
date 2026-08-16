---
id: ADR-0032
title: Web Offline Repository and Sync v1 Envelope
status: accepted
---

# ADR-0032 Web Offline Repository and Sync v1 Envelope

## Context

The Next.js client needs durable offline state before the shared server synchronization contract is frozen. Binding UI or checkout logic directly to IndexedDB or a provisional HTTP payload would make contract evolution unsafe and difficult to test.

## Decision

MOD-WEB-OFFLINE defines a transport-neutral `SyncRepository`. Its browser implementation stores cursor metadata, projections, and command outbox entries in IndexedDB. A memory implementation is the deterministic test substitute. Every record is partitioned by both tenant and enrolled device; authentication remains a server concern and is not persisted in this repository.

The wire envelope follows the frozen protocol version `"1"`: commands contain `commandId`, `type`, `occurredAt`, and `payload`; change pages contain `cursor`, `changes`, and `hasMore`; each change contains its cursor, entity type and id, operation, payload, and occurrence time. Tenant and device identities are deliberately absent from wire envelopes. They remain local storage partition keys and authenticated transport context. Outbox transitions are `pending -> sending -> applied | rejected | conflict`; startup recovery moves interrupted `sending` work back to `pending` without changing its command id. A server `retry_pending` receipt maps back to local `pending` with transport-selected retry timing. Reusing a command id with a different envelope fails closed.

Change batches reject unsupported versions, backward cursors, unordered change cursors, malformed identifiers, invalid occurrence times, and valueless upserts. Cursor gaps are valid because filtered feeds may omit unrelated changes. Projection writes and cursor advancement share one IndexedDB transaction, preventing a cursor from acknowledging partially applied state. The repository does not prescribe polling, HTTP paths, authentication headers, retry timing, or conflict resolution; those remain transport/application concerns.

The web HTTP adapter accepts injected fetch and authentication-header providers. It sends tenant and device identity only through configurable headers (defaulting to `X-Tenant-ID` and `X-Device-ID`) and never augments command bodies. Endpoint paths are configurable pending transport-route integration. Network errors, HTTP 408/425/429, and 5xx responses are retryable; other HTTP errors and malformed/version-incompatible success responses fail terminally. Responses reject unknown fields and are validated before reaching persistence.

Both repository implementations expose a partition-scoped `resetPartition`
operation for a verified local corruption event. It clears that tenant/device
cursor, outbox, and projections atomically (or as one in-memory partition) and
cannot clear another partition. It does not delete the IndexedDB database or
pretend that server-side authoritative facts were removed.

The POS now registers a conservative installable service worker. It caches only
successful same-origin GET responses, never intercepts `/api/` requests, uses
network-first navigation, and falls back to a clear offline status page. It does
not fabricate business data or treat cached HTML as fresh tenant state; the
IndexedDB repository and authenticated API remain authoritative for data.

## Traceability

- Requirement: REQ-G6-WEB-OFFLINE-001
- Module: MOD-WEB-OFFLINE
- Acceptance: cursor never moves backward; projection and cursor commit atomically; command identity survives retries; tenant/device partitions never mix; unknown schema versions fail closed
- Tests: `apps/web/src/offline/memory-sync-repository.test.ts`, `apps/web/src/offline/sync-http-adapter.test.ts`, `apps/web/src/offline/service-worker.test.ts`, `apps/web/src/offline/partition-reset.test.ts`, web lint, web production build
- Risks: RISK-OFFLINE-CROSS-TENANT-001, RISK-OUTBOX-DUPLICATE-001, RISK-CURSOR-PARTIAL-APPLY-001, RISK-SYNC-CONTRACT-DRIFT-001
- Related: ADR-0001, ADR-0005, ADR-0014, ADR-0023, Shared Synchronization Protocol
