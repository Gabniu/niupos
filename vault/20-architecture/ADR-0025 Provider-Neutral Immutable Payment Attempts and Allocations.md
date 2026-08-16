---
id: ADR-0025
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [payments, architecture]
requirements: [REQ-G6-PAYMENT-001, REQ-G6-PAYMENT-IDEMPOTENCY-001, REQ-G6-PAYMENT-SETTLEMENT-001, REQ-G6-MPESA-BOUNDARY-001]
modules: [MOD-PAYMENTS, MOD-SALES, MOD-TENANCY]
tests: [TEST-G6-PAYMENT-CASH-001, TEST-G6-PAYMENT-MPESA-001, TEST-G6-PAYMENT-IDEMPOTENCY-001, TEST-G6-PAYMENT-IMMUTABLE-001, TEST-G6-PAYMENT-TENANCY-001]
risks: [RISK-PAYMENT-DUPLICATE-001, RISK-PAYMENT-OVERALLOCATION-001, RISK-PAYMENT-SECRET-001, RISK-PAYMENT-CALLBACK-RACE-001]
---

# ADR-0025 Provider-Neutral Immutable Payment Attempts and Allocations

## Context

The [[NOVA MVP Execution Plan]] places payment evidence between an immutable finalized sale and receipt issuance. Provider retries and callbacks may be duplicated or reordered, while neither provider payloads nor credentials belong in the business ledger.

## Decision

MOD-PAYMENTS owns tenant-scoped `PaymentAttempt` and append-only `PaymentAllocation` records behind application contracts.

- `PaymentProcessor` accepts a finalized sale identity, `cash` or `mpesa`, a positive integer amount, ISO currency, actor, and bounded tenant-local idempotency key. A SHA-256 command fingerprint binds normalized inputs to the key; exact replay returns the original attempt and conflicting reuse fails.
- Payments looks up finalized sale facts through its `SalePaymentLookup` port. It does not import Sales Domain or Infrastructure. The composition root must provide an adapter from MOD-SALES.
- The first policy requires exactly the sale gross in its currency. Partial payment, split tender, change, refunds, and multi-currency allocation are deferred. A succeeded allocation cannot make the tenant-and-sale total exceed gross.
- Cash succeeds and allocates atomically. M-Pesa begins pending and accepts one explicit, idempotent terminal provider result. Only `pending` to `succeeded` or `failed` is legal; success appends the allocation and failure does not.
- `MpesaPaymentGateway` and normalized request/result DTOs define the outbound boundary. This foundation performs no network request and stores no credential or raw callback payload. Only allow-listed bounded references and a secret-safe SHA-256 result fingerprint are persisted.
- Transactions, PostgreSQL advisory locks, row locks, tenant-qualified foreign keys, unique constraints, forced RLS, Eloquent guards, and database triggers protect replay, transition, and append-only invariants.
- `PaymentSettlementReader` exposes tenant-scoped full-settlement state to Receipts without permitting another module to query Payments tables.

## Consequences

Receipts can depend on a narrow settlement adapter and providers can be integrated later without changing the ledger lifecycle. The deliberately strict exact-payment policy keeps the first release auditable but means real-world split tender and cash change require a later ADR and allocation redesign. Callback authentication, reconciliation, refunds, HTTP endpoints, and cash-drawer effects remain outside this slice.

## Verification and risk controls

- TEST-G6-PAYMENT-CASH-001 proves immediate success, exactly one allocation, replay safety, and settlement reading.
- TEST-G6-PAYMENT-MPESA-001 proves pending-to-success and pending-to-failure behavior plus terminal replay/conflict handling.
- TEST-G6-PAYMENT-IDEMPOTENCY-001 proves conflicting keys and duplicate full allocation are rejected.
- TEST-G6-PAYMENT-IMMUTABLE-001 proves allocation immutability, controlled attempt transition, and secret-like metadata rejection.
- TEST-G6-PAYMENT-TENANCY-001 proves a lookup result from another tenant is rejected; PostgreSQL RLS evidence remains an integration-gate obligation.
- RISK-PAYMENT-CALLBACK-RACE-001 is structurally reduced by advisory and row locks and still requires real PostgreSQL contention testing before production acceptance.
