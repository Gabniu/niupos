---
id: GATE-6-SYNC-FOUNDATION
title: Gate 6 Offline Synchronization Foundation
status: in-progress
date: 2026-08-08
requirements: [REQ-G6-SYNC-001, REQ-G6-WEB-001, REQ-G6-MOB-001]
tests:
  [TEST-G6-SYNC-001, TEST-G6-SYNC-HTTP-001, TEST-G6-WEB-001, TEST-G6-MOB-001]
risks: [RISK-G6-SYNC-001, RISK-G6-WEB-001, RISK-G6-MOB-001]
modules: [MOD-SYNC, MOD-WEB, MOD-MOBILE]
adrs: [ADR-0031, ADR-0032, ADR-0033, ADR-0034, ADR-0035]
---

# Gate 6 Offline Synchronization Foundation

## Implemented

- `packages/contracts/schemas/sync-v1.schema.json` freezes one command, receipt,
  and change-page vocabulary for Laravel, Next.js, and Flutter/Dart.
- MOD-SYNC persists tenant/device cursors, append-only changes, idempotent command
  receipts, retry attempts, and explicit conflict evidence under forced RLS.
- Authenticated `GET /api/v1/sync/changes` and `POST /api/v1/sync/commands` derive
  tenant and device identity from admitted request context and bounded headers.
- Authenticated `GET /api/v1/sync/bootstrap` returns active catalogue, effective
  pricing, and the current tenant cursor for atomic local initialization.
- Catalogue product/variant/barcode and pricing tax/book/price creation append
  tenant-scoped `upsert` changes in the same database transaction.
- Product, price-book, and tax-category deactivation propagates inactive
  projections through the same feed.
- The web client has IndexedDB and in-memory repositories plus a strictly validating
  fetch adapter. The mobile client has a Flutter-ready repository contract and
  in-memory conformance implementation.
- The web client registers a conservative service worker for same-origin UI
  resources, excludes `/api/` responses, uses network-first navigation, and
  provides an explicit offline fallback page without fabricating business data.
- Opaque cursors must increase but need not be contiguous; tenant-filtered global
  sequences can legitimately contain gaps.
- The web repository can recover a verified corrupt local partition by clearing
  only its tenant/device cursor, outbox, and projections before bootstrapping
  from the authoritative server again.

## Verification evidence

- Laravel integrated suite: 138 tests, 794 assertions.
- MOD-SYNC focused suite: 9 tests, 42 assertions.
- Web offline suite: 14 tests; ESLint and Next.js production build pass.
- Repository architecture and shared-contract checks: 7 tests pass.
- Mobile source and tests exist, but Dart/Flutter execution is pending because the
  SDK is not installed in the current environment.

## Remaining exit work

Gate 6 remains **in progress**. The server now executes one `sales.finalize.v1`
command through the existing Sales application contract; unsupported commands
remain explicit rejections. Catalogue/pricing field updates and hard deletes and
bootstrap pagination/large-tenant transfer, persistent encrypted mobile storage,
behavior, corrupt-state recovery, clock-skew/prolonged-partition tests, and an
end-to-end reconnect scenario must pass before the gate can close.

## Links

- [[NOVA MVP Execution Plan]]
- [[ADR-0031 Offline Synchronization Protocol Foundation]]
- [[ADR-0032 Web Offline Repository and Sync Transport]]
- [[ADR-0033 Flutter Offline Repository Boundary]]
- [[ADR-0034 Sync Catalogue and Pricing Bootstrap Snapshot]]
- [[ADR-0014 Register and Device Enrollment Foundation]]
- [[ADR-0023 Immutable Idempotent Sales Checkout Kernel]]
