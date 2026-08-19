---
id: ADR-0073
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [payments, reports]
requirements: [REQ-G7-PAYMENT-RECON-001]
modules: [MOD-PAYMENTS, MOD-REPORTS, MOD-SALES, MOD-TENANCY]
tests: [TEST-G7-PAYMENT-RECON-001]
risks: [RISK-G7-PAYMENT-RECON-001]
---

# ADR-0073 Payment Reconciliation Evidence Boundary

## Decision

Payments owns a tenant-scoped `PaymentReconciliationReader` that returns grouped, append-only allocation totals for a bounded set of finalized sale identities. Reports uses that application boundary to compare allocated payment totals with authoritative finalized sale gross totals for a selected reporting period.

The report is read-only evidence. A payment allocation is counted only when it exists in the Payments ledger; missing allocations are reported as underpaid, excess allocations as overpaid, and exact equality as fully paid. Reports never mutates payment or sale facts, and no provider payload or secret is exposed.

## Traceability

- Requirement: `REQ-G7-PAYMENT-RECON-001` — operators can identify payment allocation drift against finalized sales without crossing tenants.
- Acceptance test: `TEST-G7-PAYMENT-RECON-001` — an empty tenant period is explicit and payment totals are tenant-scoped.
- Risk: `RISK-G7-PAYMENT-RECON-001` — the evidence is only as complete as the append-only payment allocation ledger; provider callbacks and fiscal settlement remain separate boundaries.

## Consequences

- Payment reconciliation is available through `GET /api/v1/reports/payment-reconciliation` under the existing reports permission.
- The result is bounded to 100 mismatch details while retaining the checked and fully-paid counts.
- Partial/split tender, refunds, chargebacks, and eTIMS reconciliation remain future decisions.
