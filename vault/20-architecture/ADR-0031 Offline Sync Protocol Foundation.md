---
id: ADR-0031
title: Offline Sync Protocol Foundation
status: accepted
date: 2026-08-08
requirements:
  - REQ-SYNC-OFFLINE-001
modules:
  - MOD-SYNC
tests:
  - apps/api/tests/Feature/Modules/Sync/SyncProtocolTest.php
risks:
  - RISK-SYNC-REPLAY-001
  - RISK-SYNC-CONFLICT-001
related:
  - "[[ADR-0003 Tenant Context and PostgreSQL RLS]]"
  - "[[ADR-0014 Register and Device Enrollment Foundation]]"
  - "[[ADR-0023 Immutable Idempotent Sales Checkout Kernel]]"
---

# ADR-0031 Offline Sync Protocol Foundation

## Decision

Adopt a transport-neutral version `1` synchronization boundary owned by `MOD-SYNC`. The server exposes an append-only tenant change feed with a monotonically increasing numeric cursor, a non-regressing cursor per enrolled active device, and an immutable command inbox.

The frozen command envelope is `{ version, commandId, type, occurredAt, payload }`. `version` is `"1"`; `commandId` is a UUID; `type` is a bounded versioned command name; `occurredAt` is ISO-8601; and `payload` is JSON. The SHA-256 fingerprint covers the canonical complete envelope. Replaying the same tenant, device, and command UUID returns the original receipt. Reusing it with different envelope content is rejected.

The frozen change page is `{ version, cursor, changes, hasMore }`; each change is `{ cursor, entityType, entityId, operation, payload, occurredAt }`, where operation is `upsert` or `delete`.

Command receipts are `{ commandId, status, attempts, resultCode, resultMessage }`. Only `applied`, `rejected`, `conflict`, and `retry_pending` are externally observable outcomes. A conflict stores explicit structured evidence. A retry increments the attempt count and can occur only from `retry_pending`; terminal outcomes are stable.

## Safety and isolation

All sync tables carry `tenant_id`, use composite tenant/device foreign keys, and enable and force PostgreSQL row-level security. Pull and command paths require an active device resolved inside the current tenant. Advisory and row locks serialize cursor and command identities. The feed may contain global cursor gaps because another tenant consumed a sequence value; clients treat cursors as opaque monotonic positions.

## Execution boundary

`SyncCommandHandler` remains an application port. The registered handler now
supports `sales.finalize.v1`: it derives the actor from the authenticated
session, resolves the internal register from the active tenant/device row, and
calls the existing `SalesCheckout` application contract with the command UUID as
the tenant-local idempotency key. Invalid sale payloads and unsupported command
types remain explicit terminal rejections. Catalogue/pricing bootstrap producers,
and an end-to-end reconnect acceptance path, remain outside this slice.

## Authenticated HTTP transport

`GET /api/v1/sync/changes` exposes the frozen change page and accepts bounded `after_cursor` and `limit` query parameters. `POST /api/v1/sync/commands` accepts only the frozen command envelope and returns its stable receipt. Both routes require, in order, `api.session`, `tenant`, `permission:sync.use`, and their operation-specific rate limiter.

The public UUID in `X-Device-Id` is the only device selector accepted by the transport. The application resolves it as an active device under the middleware-established tenant; tenant and device identifiers in request bodies are neither required nor trusted. Invalid headers receive a validation response, while inactive and cross-tenant device lookups share the generic unavailable response.

## Acceptance evidence

`SyncProtocolTest` and `SalesSyncCommandHandlerTest` cover tenant/device isolation,
feed ordering, cursor regression, identical replay, mismatched fingerprints, retry
transitions, terminal stability, conflict evidence, authenticated actor derivation,
device-to-register resolution, and Sales idempotency delegation.
