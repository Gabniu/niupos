---
id: ADR-0067
title: Catalogue Pricing Mutation Feed Completeness
status: accepted
date: 2026-08-18
requirements:
  - REQ-G6-SYNC-001
  - REQ-G6-MOB-001
tests:
  - TEST-G6-SYNC-001
risks:
  - RISK-G6-SYNC-001
modules:
  - MOD-CATALOGUE
  - MOD-PRICING
  - MOD-SYNC
---

# ADR-0067 — Catalogue Pricing Mutation Feed Completeness

## Decision

Catalogue and pricing application managers publish every supported mutation in
the same tenant-scoped database transaction as the source change. Field updates
publish `upsert` projections with the complete current row. Hard deletes publish
`delete` projections before removing the row, including product descendants
(variants and barcodes).

Catalogue does not query pricing tables. A product delete therefore relies on
the database's composite foreign keys to reject a product that still has price
references; the transaction rolls back its already-staged sync changes when the
delete cannot complete. Pricing deletes explicitly reject price books and tax
categories that still have product-price references. Price deletes are allowed
and publish their own tombstone.

All lookups derive the tenant from `TenantContext`; cross-tenant references,
inactive references, duplicate normalized identities, and overlapping price
windows remain rejected. The sync feed keeps the same `upsert`/`delete`
operation vocabulary used by web and mobile clients.

## Traceability

- `REQ-G6-SYNC-001`: clients receive field updates and tombstones instead of a
  stale projection that only changes on creation/deactivation.
- `TEST-G6-SYNC-001`: catalogue and pricing manager tests cover updates,
  descendant deletes, price-reference guards, and emitted operations.
- `RISK-G6-SYNC-001`: hard deletes are irreversible locally; clients must apply
  tombstones atomically and may re-bootstrap when a cursor gap is detected.

## Consequences

The feed is complete for the current catalogue/pricing mutation API without
cross-module database coupling. Future mutation methods must publish a complete
upsert or a tombstone in the same transaction and add a focused feed test.
