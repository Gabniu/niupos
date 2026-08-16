---
id: ADR-0017
title: Inventory Ledger and Balance Foundation
status: accepted
date: 2026-08-08
requirements:
  - REQ-G4-INV-LEDGER-001
tests:
  - TEST-G4-INV-LEDGER-001
risks:
  - RISK-INV-LOST-UPDATE-001
  - RISK-INV-LEDGER-TAMPER-001
  - RISK-INV-IDEMPOTENCY-001
modules:
  - MOD-INVENTORY
---

# ADR-0017 Inventory Ledger and Balance Foundation

## Context

NOVA needs a tenant-safe stock foundation before receiving, sales finalization, transfers, counts, reservations, or offline synchronization can be built. Stock must not use floating-point quantities, retries must not double-post, and the history used to explain a balance must be immutable.

## Decision

Implement `MOD-INVENTORY` with an append-only `StockMovement` ledger and a current `StockBalance` projection keyed by the exact tenant, warehouse, and catalogue variant.

- `quantity_delta`, `balance_after`, and balance `quantity` are signed 64-bit integers. Each value is expressed in the catalogue unit's documented smallest indivisible scale; no float enters the contract or persistence model.
- Initially accepted commands are positive receipts and non-zero signed adjustments. Sales, reservations, releases, transfers, and count workflows remain deferred.
- Every command supplies an idempotency key unique within its tenant. A SHA-256 fingerprint binds the key to movement type, warehouse, variant, and quantity. Exact replay returns the original movement; conflicting reuse fails.
- Posting is one database transaction. A PostgreSQL transaction advisory lock serializes the tenant/idempotency key, and the tenant/warehouse/variant balance row is created idempotently then selected `FOR UPDATE` before arithmetic and ledger append.
- Negative stock is denied by default. `inventory.allow_negative_stock` is an explicit policy escape hatch and defaults to `false`.
- Inventory consumes Catalogue's `ActiveVariantLookup` application contract. Active warehouse existence is checked with a tenant-scoped query at the database boundary, avoiding imports from another module's Domain layer.
- Both tables use composite tenant foreign keys, forced PostgreSQL RLS, and exact tenant policies.
- Eloquent rejects movement update/delete. PostgreSQL triggers independently reject both operations, including raw SQL.

## Traceability

- **REQ-G4-INV-LEDGER-001:** Post tenant-scoped receipt and adjustment movements idempotently while maintaining an atomic integer stock balance.
- **TEST-G4-INV-LEDGER-001:** `InventoryLedgerTest` proves context enforcement, receipt and adjustment arithmetic, exact replay, conflicting reuse, cross-tenant and inactive-reference rejection, negative-stock denial, row-lock/advisory-lock shape, model immutability, PostgreSQL triggers, and forced RLS.
- **RISK-INV-LOST-UPDATE-001:** Concurrent writers could overwrite balances; mitigated by an atomic transaction and locked balance row.
- **RISK-INV-LEDGER-TAMPER-001:** History could be rewritten; mitigated independently at model and PostgreSQL trigger layers.
- **RISK-INV-IDEMPOTENCY-001:** Retries or key collisions could double-count or disguise a different command; mitigated by tenant-unique keys, command fingerprints, and transaction advisory locks.

## Consequences

The balance is inexpensive to query while every change remains explainable from the ledger. The initial implementation intentionally does not expose HTTP endpoints or implement reservations, sale finalization, transfers, counts, or offline conflict handling. Those workflows must compose new ledger commands rather than mutate existing movements.
