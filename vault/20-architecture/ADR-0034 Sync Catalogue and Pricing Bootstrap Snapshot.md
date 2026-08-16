---
id: ADR-0034
title: Sync Catalogue and Pricing Bootstrap Snapshot
status: accepted
date: 2026-08-08
requirements: [REQ-G6-SYNC-BOOTSTRAP-001]
tests: [TEST-G6-SYNC-BOOTSTRAP-001]
risks: [RISK-G6-SYNC-BOOTSTRAP-001]
modules: [MOD-SYNC, MOD-CATALOGUE, MOD-PRICING]
related: ["[[ADR-0031 Offline Sync Protocol Foundation]]", "[[ADR-0012 Tenant Catalogue and Barcode Identity]]", "[[ADR-0020 Checkout Pricing Quote Boundary]]"]
---

# ADR-0034 — Sync Catalogue and Pricing Bootstrap Snapshot

## Decision

MOD-SYNC exposes `GET /api/v1/sync/bootstrap` behind the same session, tenant,
`sync.use` permission, and device admission boundary as the change feed. The
response is version `1` and contains the tenant cursor, generation timestamp,
active catalogue identity, and currently effective pricing rows.

The snapshot includes active categories, units of measure, products, variants,
barcodes, tax categories, price books, and prices. Prices are included only when
their effective window contains the snapshot time and their referenced price
book, tax category, and product variant are active in the same tenant. The
device and tenant are selected from authenticated request context and the
`X-Device-Id` public identifier; neither is accepted in the response request
body.

The returned cursor is the tenant's current Sync change cursor. Clients apply
the snapshot atomically, then pull changes after that cursor. The endpoint is
rate-limited separately because it is larger and less frequent than a change
page.

## Traceability and evidence

- `TEST-G6-SYNC-BOOTSTRAP-001`: `SyncHttpTest` verifies middleware ordering and
  the frozen catalogue/pricing/cursor envelope; web adapter tests validate the
  same response shape and protocol version.
- `RISK-G6-SYNC-BOOTSTRAP-001`: the snapshot is not yet a resumable multi-page
  transfer; large tenants require bounded pagination before production rollout.

## Consequences

Devices can initialize catalogue and pricing projections without replaying the
entire historical change feed. The endpoint is a read boundary, not a domain
mutation path; future projection producers must publish changes transactionally
with catalogue and pricing mutations.
