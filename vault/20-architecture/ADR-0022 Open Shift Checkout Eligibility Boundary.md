---
id: ADR-0022
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [pos, architecture]
requirements: [REQ-G2-CHECKOUT-SHIFT-001, REQ-G2-CHECKOUT-SHIFT-ISOLATION-001, REQ-G2-CHECKOUT-SHIFT-CONCURRENCY-001]
modules: [MOD-SHIFTS, MOD-SALES, MOD-REGISTER, MOD-IAM, MOD-TENANCY]
tests: [TEST-G2-CHECKOUT-SHIFT-ELIGIBLE-001, TEST-G2-CHECKOUT-SHIFT-REJECT-001, TEST-G2-CHECKOUT-SHIFT-TENANT-001, TEST-G2-CHECKOUT-SHIFT-LOCK-001]
risks: [RISK-SHIFT-CLOSE-RACE-001, RISK-CHECKOUT-IDENTITY-LEAK-001, RISK-CROSS-MODULE-COUPLING-001]
---

# ADR-0022 Open Shift Checkout Eligibility Boundary

## Context

Checkout requires a currently open shift for the exact register and acting user in the current tenant. A read followed later by sale finalization would allow shift close to race between those operations. Sales also must not depend on Shifts, Register, or Identity persistence internals.

## Decision

REQ-G2-CHECKOUT-SHIFT-001 introduces the Shifts application contract `OpenShiftCheckoutEligibility`. Its `withEligibleOpenShift` operation accepts a register ID, actor user ID, and callback. Tenant identity is derived only from `TenantContext`; callers cannot supply or override it.

The boundary requires exactly one tenant-qualified open shift for the register, an active tenant-qualified register, and an active tenant membership for the actor. Register and membership checks use tenant-scoped database queries inside Shifts rather than importing another module's Domain or Infrastructure layer. Every missing, closed, inactive, or cross-tenant case raises the same rejection to avoid disclosing which prerequisite exists.

The callback receives only `EligibleOpenShift`: tenant, shift, register, and actor stable IDs; the shift currency; and its immutable opened timestamp. It receives no Eloquent model and cannot mutate shift or cash state through this contract.

REQ-G2-CHECKOUT-SHIFT-CONCURRENCY-001 requires the eligibility implementation to open a database transaction, acquire a `FOR UPDATE` lock on the open shift row, recheck the register and membership, and invoke the callback before that transaction ends. Sales must perform persistent checkout finalization inside the callback. Returning from the callback releases the lock; deferring finalization until afterward forfeits the close-race guarantee. The existing close operation locks the same shift row, establishing serialization on databases with row-level locking.

## Consequences and evidence

- TEST-G2-CHECKOUT-SHIFT-ELIGIBLE-001 proves the stable result and transaction-scoped callback.
- TEST-G2-CHECKOUT-SHIFT-REJECT-001 proves missing, closed, inactive-register, and inactive-membership rejection.
- TEST-G2-CHECKOUT-SHIFT-TENANT-001 proves tenant, register, and actor references cannot cross boundaries or reveal shift existence.
- TEST-G2-CHECKOUT-SHIFT-LOCK-001 proves callback rollback composition and the tenant/register/status/row-lock query shape.
- RISK-SHIFT-CLOSE-RACE-001: SQLite does not reproduce PostgreSQL row-lock contention. Control: transaction and query-shape tests now; retain a PostgreSQL concurrent-close/finalize integration test before production acceptance.
- RISK-CHECKOUT-IDENTITY-LEAK-001: distinct prerequisite errors could reveal tenant data. Control: one generic rejection message.
- RISK-CROSS-MODULE-COUPLING-001: direct persistence imports would make Sales and Shifts evolve together. Control: expose an application contract and immutable DTO only.

## Acceptance boundary

This ADR establishes checkout eligibility and lock ownership only. It does not create a sale, reserve or decrement inventory, accept tenders, expose HTTP, authorize permissions, change expected cash, or implement accounting. Those operations belong to their owning modules and must compose inside the callback transaction where race protection is required.
