---
id: ADR-0070
title: Read-only Sales Reconciliation Evidence
status: accepted
date: 2026-08-18
requirements:
  - REQ-G7-RECON-001
tests:
  - TEST-G7-RECON-001
risks:
  - RISK-G7-RECON-001
modules:
  - MOD-REPORTS
  - MOD-SALES
---

# ADR-0070 - Read-only Sales Reconciliation Evidence

## Decision

Reports expose a tenant-scoped reconciliation view that compares finalized sale
headers with their immutable sale-line sums for gross, net, and tax amounts.
The endpoint returns an explicit `ok` or `attention` status, the number of
checked sales, and at most 100 discrepancy details. It never repairs or mutates
authoritative sales data.

Bounds use the tenant reporting timezone and are converted to UTC before the
database query. The database remains the source of truth; this endpoint is
operational evidence for review, alerting, and later export workflows.

## Traceability

- `REQ-G7-RECON-001`: operators can detect immutable sale/line total drift
  without changing financial facts.
- `TEST-G7-RECON-001`: an empty finalized-sales period returns an explicit
  healthy result and timezone-aware bounds.
- `RISK-G7-RECON-001`: a discrepancy is evidence, not an automatic correction;
  repair requires an audited domain-specific workflow.

## Consequences

The report can be polled or exported by a future reconciliation worker. Payment,
inventory, and fiscal reconciliation remain separate boundaries and are not
inferred from this sale-line check.
