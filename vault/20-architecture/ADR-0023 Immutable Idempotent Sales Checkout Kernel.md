---
id: ADR-0023
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [sales, architecture]
requirements: [REQ-G5-SALES-FINALIZE-001, REQ-G5-SALES-SNAPSHOT-001, REQ-G5-SALES-IDEMPOTENCY-001, REQ-G5-SALES-INVENTORY-001]
modules: [MOD-SALES, MOD-PRICING, MOD-INVENTORY, MOD-SHIFTS, MOD-AUDIT, MOD-TENANCY]
tests: [TEST-G5-SALES-FINALIZE-001, TEST-G5-SALES-IDEMPOTENCY-001, TEST-G5-SALES-IMMUTABLE-001, TEST-G5-SALES-BOUNDARY-001]
risks: [RISK-SALES-PARTIAL-001, RISK-SALES-REPLAY-001, RISK-SALES-ROUNDING-001, RISK-SALES-SHIFT-RACE-001]
---

# ADR-0023 Immutable Idempotent Sales Checkout Kernel

## Context

The [[NOVA MVP Execution Plan]] makes server-side checkout the first consumer of the stabilized catalogue, pricing, inventory, register, shift, tenancy, and audit foundations. A finalized sale is financial evidence: its amounts cannot be recalculated from mutable catalogue or pricing records, a retry cannot create another sale or stock movement, and a shift cannot close concurrently with finalization.

## Decision

MOD-SALES owns an atomic `SalesCheckout` application boundary and immutable `Sale` and `SaleLine` records.

- The command derives tenant identity only from `TenantContext` and requires a bounded tenant-local idempotency key. A SHA-256 fingerprint binds that key to register, actor, warehouse, price book, currency, effective timestamp, and normalized lines. Exact replay returns the prior sale; conflicting reuse fails.
- Duplicate variant lines are rejected and callers must combine them explicitly. Quantities and all monetary values are positive or non-negative integers; totals use checked integer addition.
- Sales executes inside MOD-SHIFTS' transaction-scoped eligible-open-shift callback. The callback holds the shift row lock until pricing, inventory intents, sale persistence, and audit evidence complete.
- MOD-PRICING supplies immutable line quotes. Sales snapshots quantity, currency, unit price, net, tax, gross, tax identity/rate/mode, price identity, and quote time; it never recalculates Pricing rules.
- MOD-INVENTORY owns reservation and stock movement state. Sales creates deterministic reservation identities from tenant, command key, and variant, then reserves and finalizes each line through the application contract. Nested operations share the database transaction, so failure rolls back stock, sale, and audit writes together.
- Finalized sales and lines use tenant-qualified foreign keys, forced PostgreSQL RLS, Eloquent mutation guards, and database update/delete rejection triggers.
- MOD-AUDIT records the successful financially material outcome without storing secrets or mutable pricing payloads.

## Consequences

The kernel produces a finalized sale and inventory effect but intentionally does not claim payment, tender allocation, receipt numbering/rendering, discounts, promotions, voids, refunds, offline conflict handling, or HTTP transport. Those remain explicit later contracts. Because the current modules share one PostgreSQL database and connection, the atomic composition is valid; splitting modules into independent databases would require an outbox/saga redesign rather than preserving this transaction assumption.

## Verification and risk controls

- TEST-G5-SALES-FINALIZE-001 proves immutable line snapshots, checked totals, eligible-shift composition, inventory reserve/finalize calls, and audit evidence.
- TEST-G5-SALES-IDEMPOTENCY-001 proves exact replay and conflicting-key rejection without duplicate sales or inventory effects.
- TEST-G5-SALES-IMMUTABLE-001 proves model and database mutation guards.
- TEST-G5-SALES-BOUNDARY-001 is the repository architecture suite proving Sales imports only other modules' Application contracts/data.
- RISK-SALES-PARTIAL-001 is controlled by one database transaction and rollback tests; external provider calls are forbidden inside this kernel.
- RISK-SALES-SHIFT-RACE-001 is controlled structurally by the locked callback and still requires real PostgreSQL contention evidence before production acceptance.
