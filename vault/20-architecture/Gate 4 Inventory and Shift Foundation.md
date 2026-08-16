---
id: GATE-4-INVENTORY-SHIFT-0001
type: implementation-evidence
status: in-progress
date: 2026-08-08
owners: [inventory, pos, architecture]
requirements: [REQ-G4-INV-LEDGER-001, REQ-G2-SHIFT-001, REQ-G2-CASH-001, REQ-G2-CASH-IDEMPOTENCY-001, REQ-G2-CASH-ISOLATION-001]
adrs: [ADR-0003, ADR-0017, ADR-0018]
modules: [MOD-INVENTORY, MOD-SHIFTS, MOD-CATALOGUE, MOD-REGISTER, MOD-TENANCY]
risks: [RISK-INV-LOST-UPDATE-001, RISK-INV-LEDGER-TAMPER-001, RISK-INV-IDEMPOTENCY-001, RISK-SHIFT-CONCURRENCY-001, RISK-CASH-MUTATION-001]
tests: [TEST-G4-INV-LEDGER-001, TEST-G2-SHIFT-LIFECYCLE-001, TEST-G2-SHIFT-UNIQUE-001, TEST-G2-CASH-ARITHMETIC-001, TEST-G2-CASH-IDEMPOTENCY-001, TEST-G2-CASH-APPEND-001, TEST-G2-CASH-TENANT-001, TEST-G1-POSTGRES-RLS-001]
---

# Gate 4 Inventory and Shift Foundation

## Implemented slice

MOD-INVENTORY now owns an append-only stock-movement ledger and locked current-balance projection for tenant, warehouse, and active catalogue variant. Receipt and signed-adjustment commands use integer quantities, tenant-local idempotency fingerprints, atomic transaction and locking controls, default-deny negative stock, model mutation guards, PostgreSQL mutation triggers, and forced RLS. See [[ADR-0017 Inventory Ledger and Balance Foundation]].

MOD-SHIFTS now owns an accountable register operating interval with one open shift per register, integer opening/expected/counted/variance money, append-only pay-in/pay-out movements, tenant-local idempotency, active register and membership checks, atomic locking, mutation guards, and forced RLS. See [[ADR-0018 Register Shift and Cash Control Foundation]].

These application contracts are checkout prerequisites. Neither module imports another module's Domain or Infrastructure internals, and neither slice prematurely implements sale tenders, reservations, accounting, HTTP mutation endpoints, or offline synchronization.

## Current evidence

- TEST-G4-INV-LEDGER-001: 8 focused tests and 18 assertions pass.
- Shift/cash focused evidence: 5 tests and 24 assertions pass.
- Repository architecture fitness: all 6 tests pass after moving Inventory configuration outside the module namespace scan.
- TEST-G1-POSTGRES-RLS-001 passes against real PostgreSQL with explicit stock-movement and register-shift tenant visibility checks.

## Remaining Gate 4 work

- Inventory reservations, sale finalization/reversal intents, transfers, counts, receiving workflow, and reconciliation.
- Cash tender integration, safe drops, denomination counts, supervisor policies, and reconciliation.
- High-contention PostgreSQL tests for balance, register, and idempotency locks.
- HTTP and offline command boundaries after the checkout and synchronization protocols stabilize.
