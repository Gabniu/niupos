---
id: ADR-0024
type: architecture-decision
status: accepted
date: 2026-08-08
owners: [sales, architecture]
requirements: [REQ-G6-SALE-REFERENCE-001, REQ-G6-RECEIPT-SNAPSHOT-001]
modules: [MOD-SALES, MOD-PAYMENTS, MOD-RECEIPTS, MOD-TENANCY]
tests: [TEST-G6-SALE-SNAPSHOT-001, TEST-G6-SALE-SNAPSHOT-TENANT-001, TEST-G6-SALE-SNAPSHOT-STATE-001, TEST-G6-SALE-SNAPSHOT-IMMUTABLE-001]
risks: [RISK-PAYMENT-WRONG-SALE-001, RISK-RECEIPT-DRIFT-001, RISK-SALE-DISCLOSURE-001]
---

# ADR-0024 Finalized Sale Snapshot Boundary

## Context

Payments must validate allocations against authoritative sale totals and Receipts must render the exact commercial evidence captured at checkout. Importing Sales models would expose mutable persistence behavior and couple both consumers to Sales storage.

## Decision

MOD-SALES exposes `FinalizedSaleSnapshotReader::resolve(string $saleId)` as its stable read/reference boundary.

- Tenant identity comes exclusively from `TenantContext`; caller-supplied tenant identity is forbidden.
- Missing, cross-tenant, and non-finalized identifiers produce the same generic rejection.
- The result contains readonly sale header values and readonly line snapshots ordered by `line_number`. It includes identifiers, currency, integer totals, finalization time, and the quantity, unit price, monetary, tax, mode, and variant evidence required by downstream consumers.
- No Eloquent model or collection crosses the application boundary. Payments and Receipts depend only on this contract and its immutable DTOs.
- The existing `SalesCheckout` command signature is unchanged.

## Consequences

Payment and receipt modules can independently consume finalized commercial evidence without learning Sales persistence. The boundary intentionally excludes payment state, receipt numbering, product display enrichment, refunds, and recomputation from current Pricing or Catalogue data. Product descriptions are not yet snapshotted by checkout, so a future requirement for immutable printed descriptions must extend the sale-line write model through a separate decision.

## Verification and risk controls

- TEST-G6-SALE-SNAPSHOT-001 proves complete headers and deterministically ordered line evidence.
- TEST-G6-SALE-SNAPSHOT-TENANT-001 proves exact tenant isolation and indistinguishable missing-sale rejection, controlling RISK-SALE-DISCLOSURE-001.
- TEST-G6-SALE-SNAPSHOT-STATE-001 rejects non-finalized records, controlling RISK-PAYMENT-WRONG-SALE-001.
- TEST-G6-SALE-SNAPSHOT-IMMUTABLE-001 proves consumers receive readonly values rather than mutable models, controlling RISK-RECEIPT-DRIFT-001.
