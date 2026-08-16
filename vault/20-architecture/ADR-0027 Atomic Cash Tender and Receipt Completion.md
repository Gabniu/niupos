---
id: ADR-0027
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [payments, receipts, shifts, sales, architecture]
requirements: [REQ-G5-CASH-COMPLETE-001, REQ-G5-CASH-DRAWER-001, REQ-G5-RECEIPT-ISSUE-001]
modules: [MOD-SALES, MOD-PAYMENTS, MOD-RECEIPTS, MOD-SHIFTS, MOD-TENANCY]
tests: [TEST-G5-CASH-COMPLETE-001, TEST-G5-CASH-DRAWER-001, TEST-G5-CASH-ACTOR-001]
risks: [RISK-CASH-PARTIAL-001, RISK-CASH-DOUBLE-COUNT-001, RISK-RECEIPT-DUPLICATE-001]
---

# ADR-0027 Atomic Cash Tender and Receipt Completion

## Context

A successful cash checkout must not leave payment allocation, expected drawer cash, and receipt issuance in contradictory states. Each owning module must retain its invariants while the initial monolithic PostgreSQL deployment provides an atomic composition boundary.

## Decision

The application-level `CashSaleCompletion` service composes only Application contracts from Sales, Payments, Shifts, and Receipts inside one database transaction.

- Sales supplies the immutable finalized-sale snapshot and authoritative actor, shift, amount, and currency.
- Payments records an exact-full cash attempt and immutable allocation.
- Shifts records a dedicated append-only `sale_cash` movement and increments expected drawer cash exactly once. It validates the open shift, currency, opening actor's active tenant membership, tenant-local idempotency, and checked integer arithmetic.
- Receipts issues the one immutable receipt only after its Payments settlement adapter confirms the sale is fully paid.
- A caller idempotency key is transformed into stable purpose-specific SHA-256 keys for payment, drawer, and receipt operations. Exact replay therefore returns the same evidence at every boundary; conflicting component reuse fails.
- Any exception rolls back all database effects. This atomicity depends on the current shared PostgreSQL connection and must be redesigned as explicit saga/outbox coordination before any module is separated into another database.

## Consequences and evidence

TEST-G5-CASH-COMPLETE-001 proves ordered payment, drawer, and receipt composition and the returned evidence identities. TEST-G5-CASH-DRAWER-001 proves one expected-cash increment under exact replay and rejection of conflicting reuse. TEST-G5-CASH-ACTOR-001 proves actor mismatch fails before side effects.

This decision does not expose HTTP, support change/partial/split tenders, call M-Pesa, print or deliver a receipt, or claim fiscal/eTIMS compliance. Those remain explicit later boundaries.
