---
id: ADR-0018
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [pos, architecture]
requirements: [REQ-G2-SHIFT-001, REQ-G2-CASH-001, REQ-G2-CASH-IDEMPOTENCY-001, REQ-G2-CASH-ISOLATION-001]
modules: [MOD-SHIFTS, MOD-REGISTER, MOD-IAM, MOD-TENANCY]
tests: [TEST-G2-SHIFT-LIFECYCLE-001, TEST-G2-SHIFT-UNIQUE-001, TEST-G2-CASH-ARITHMETIC-001, TEST-G2-CASH-IDEMPOTENCY-001, TEST-G2-CASH-APPEND-001, TEST-G2-CASH-TENANT-001]
risks: [RISK-SHIFT-CONCURRENCY-001, RISK-CASH-MUTATION-001, RISK-CASH-ACCOUNTING-SCOPE-001]
---

# ADR-0018 Register Shift and Cash Control Foundation

## Context

A register must have an accountable operating interval before sales can safely affect a drawer. Cash additions and removals must be attributable, replay-safe, tenant-isolated, and incapable of silent rewriting. This foundation must not prematurely define sale tenders, accounting postings, payroll, or denomination reconciliation.

## Decision

REQ-G2-SHIFT-001 defines a tenant-owned shift linked to an active register and an opening user with an active tenant membership. Its only lifecycle is `open` to `closed`. Opening stores non-negative integer minor-unit float and an uppercase three-letter ISO 4217 currency code. Closing stores non-negative counted cash and computes `variance_minor = counted_cash_minor - expected_cash_minor`.

REQ-G2-CASH-001 defines append-only `pay_in` and `pay_out` movements. Each positive integer minor-unit movement records its reason, actor, currency inherited from the shift, occurrence time, and tenant. Pay-outs cannot make expected drawer cash negative. Expected cash starts at opening float, increases for pay-ins, and decreases for pay-outs.

REQ-G2-CASH-IDEMPOTENCY-001 assigns tenant-unique idempotency keys to shift opening and cash movements. Repetition with identical input returns the original resource; reuse with different input is rejected. Database transactions lock the register or shift rows, while a partial unique database index prevents two open shifts for one tenant/register on PostgreSQL and SQLite.

REQ-G2-CASH-ISOLATION-001 requires tenant-qualified register, shift, and active-membership checks. Composite foreign keys prevent cross-tenant register and shift references. PostgreSQL forced RLS protects shifts and cash movements. Cash movement update/delete is blocked by model events and database triggers.

## Consequences and evidence

- TEST-G2-SHIFT-LIFECYCLE-001 proves the bounded open-to-closed lifecycle and rejection of activity after close.
- TEST-G2-SHIFT-UNIQUE-001 proves the application guard and database partial uniqueness boundary.
- TEST-G2-CASH-ARITHMETIC-001 proves integer pay-in/pay-out arithmetic and close variance.
- TEST-G2-CASH-IDEMPOTENCY-001 proves same-request replay and conflicting-key rejection without duplicate arithmetic.
- TEST-G2-CASH-APPEND-001 proves model and database mutation guards.
- TEST-G2-CASH-TENANT-001 proves cross-tenant register/user rejection and tenant-scoped shift visibility.
- RISK-SHIFT-CONCURRENCY-001: database engines differ in row-lock and partial-index behavior. Control: transactional locks plus database uniqueness; retain PostgreSQL contention evidence before production acceptance.
- RISK-CASH-MUTATION-001: privileged database access can bypass application controls. Control: database append-only triggers, forced RLS, and restricted production database roles.
- RISK-CASH-ACCOUNTING-SCOPE-001: expected drawer cash is not a general ledger balance. Control: keep accounting, payroll, tenders, refunds, safe drops, and reconciliation in explicit later contracts.

## Acceptance boundary

This ADR establishes application service contracts and persistence only. It does not expose HTTP endpoints, integrate sales or payments, capture denominations, authorize cash limits, create accounting entries, implement payroll, or prove high-contention PostgreSQL behavior.
