---
id: GATE-5-SALES-CHECKOUT-0001
type: implementation-evidence
status: in-progress
date: 2026-08-08
owners: [sales, pricing, inventory, pos, architecture]
requirements: [REQ-G5-SALES-FINALIZE-001, REQ-G5-SALES-SNAPSHOT-001, REQ-G5-SALES-IDEMPOTENCY-001, REQ-G5-SALES-INVENTORY-001]
adrs: [ADR-0003, ADR-0017, ADR-0018, ADR-0020, ADR-0021, ADR-0022, ADR-0023, ADR-0024, ADR-0025, ADR-0026, ADR-0027, ADR-0028, ADR-0029, ADR-0030]
modules: [MOD-SALES, MOD-PRICING, MOD-INVENTORY, MOD-SHIFTS, MOD-REGISTER, MOD-AUDIT, MOD-TENANCY]
risks: [RISK-SALES-PARTIAL-001, RISK-SALES-REPLAY-001, RISK-SALES-ROUNDING-001, RISK-SALES-SHIFT-RACE-001]
tests: [TEST-G5-SALES-FINALIZE-001, TEST-G5-SALES-IDEMPOTENCY-001, TEST-G5-SALES-IMMUTABLE-001, TEST-G5-SALES-BOUNDARY-001, TEST-G1-POSTGRES-RLS-001]
---

# Gate 5 Sales Checkout Kernel

## Implemented slice

MOD-PRICING exposes an immutable checkout quote with checked integer arithmetic, effective-price and currency validation, deterministic inclusive/exclusive tax rounding, and complete price/tax provenance. See [[ADR-0020 Checkout Pricing Quote Boundary]].

MOD-INVENTORY exposes active reservations and terminal finalize/release intents. Availability is on-hand minus active reservations; locking and idempotency prevent oversell and duplicate stock movements. See [[ADR-0021 Inventory Sale Intent Reservations]].

MOD-SHIFTS exposes a transaction-scoped eligible-open-shift callback that locks the shift while checkout work completes and validates tenant, register, and active membership through application-safe boundaries. See [[ADR-0022 Open Shift Checkout Eligibility Boundary]].

MOD-SALES composes those contracts to finalize immutable sale and line snapshots. A tenant-local command fingerprint makes exact replay stable and conflicting reuse fail. Inventory reservations use deterministic identities, financial totals use checked integer addition, successful outcomes produce audit evidence, and PostgreSQL RLS plus application/database mutation guards protect finalized facts. See [[ADR-0023 Immutable Idempotent Sales Checkout Kernel]].

## Evidence

- Full Laravel regression: 98 tests and 485 assertions pass.
- Repository architecture: 6 of 6 tests pass with no cross-module Domain or Infrastructure imports.
- Focused Sales: 2 tests and 16 assertions pass.
- Pricing quote: 4 focused tests; Pricing total 9 tests and 27 assertions pass.
- Inventory sale intents: 6 focused tests and 25 assertions; ledger regression 8 tests and 18 assertions pass.
- Shift eligibility: 5 focused tests and 22 assertions; Shifts total 10 tests and 46 assertions pass.
- TEST-G1-POSTGRES-RLS-001 passes against real PostgreSQL for stock reservations, sales, and sale lines.

## Remaining Gate 5 work

- Generated client contracts for checkout, payment, receipt, and delivery evidence.
- Live M-Pesa adapter, credential delivery, callback authentication, reconciliation worker, timeout/unknown outcomes, and sandbox evidence.
- Printer/email/SMS adapters and end-to-end delivery evidence.
- Discounts, promotions, voids, refunds, and compensating inventory/payment records.
- Real PostgreSQL concurrent shift-close, reservation, and idempotency contention tests.

## Tender and receipt foundation

MOD-SALES exposes immutable finalized header and ordered-line snapshots through [[ADR-0024 Finalized Sale Snapshot Boundary]]. MOD-PAYMENTS owns exact-full cash and M-Pesa attempt/allocation lifecycles, controlled terminal provider results, secret-safe references, settlement reads, and forced RLS under [[ADR-0025 Provider-Neutral Immutable Payment Attempts and Allocations]]. MOD-RECEIPTS owns register-scoped monotonic numbering, immutable render snapshots, and append-only delivery evidence under [[ADR-0026 Immutable Receipt Snapshots and Register Numbering]].

[[ADR-0027 Atomic Cash Tender and Receipt Completion]] composes cash payment, expected-drawer evidence, and receipt issuance atomically with stable purpose-specific idempotency keys. The full Laravel suite now passes 110 tests and 538 assertions; focused cash completion and drawer tests add 3 tests and 14 assertions. Real PostgreSQL RLS evidence includes payment attempts and receipts.

## Authenticated transport evidence

[[ADR-0028 Sales Checkout HTTP Transport]] exposes versioned sale finalization and cash completion with session-derived actors, tenant admission, `sales.checkout.create`, bounded idempotency headers, validation, and per-session throttling. [[ADR-0029 Authenticated Payment Operations Transport]] exposes payment initiation and a privileged operator-only terminal-result route; it explicitly does not claim to be a public M-Pesa callback. [[ADR-0030 Receipt Retrieval and Delivery Evidence HTTP Boundary]] exposes immutable tenant-scoped receipt reads and closed-schema delivery evidence that rejects destinations, message bodies, and secret fields.

The integrated Laravel suite passes 126 tests and 739 assertions. The three transport suites contribute 15 tests and 197 assertions, and the repository architecture suite remains 6 of 6.
