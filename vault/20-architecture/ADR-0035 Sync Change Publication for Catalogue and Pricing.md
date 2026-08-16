---
id: ADR-0035
title: Sync Change Publication for Catalogue and Pricing
status: accepted
date: 2026-08-08
requirements: [REQ-G6-SYNC-001]
tests: [TEST-G6-SYNC-001, TEST-G6-CATALOGUE-001, TEST-G6-PRICING-001]
risks: [RISK-G6-SYNC-001]
---

# Context

Offline clients must receive catalogue and pricing mutations after bootstrap.

# Decision

Catalogue product, variant, and barcode creation publishes tenant-scoped
`upsert` changes. Pricing tax-category, price-book, and product-price creation
publishes corresponding `upsert` changes. Each publication occurs in the same
database transaction as its source mutation through the SyncProtocol contract.

Payloads contain only active client projection fields; tenant identity remains
derived from TenantContext and is never accepted from the payload.

# Traceability

- REQ-G6-SYNC-001: clients converge from bootstrap plus changes.
- TEST-G6-CATALOGUE-001: catalogue creation writes product and variant changes.
- TEST-G6-PRICING-001: pricing creation writes tax, book, and price changes.

# Risks and follow-up

Deactivation of products, price books, and tax categories now publishes an
inactive `upsert`; remaining field updates and hard deletes require equivalent
publication before Gate 6 can close. Payload evolution must remain compatible
with the shared sync schema.
