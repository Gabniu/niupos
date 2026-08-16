---
id: ADR-0040
title: POS Sales Workspace Boundary
status: accepted
date: 2026-08-11
requirements:
  - REQ-POS-SALES-WEB-001
tests:
  - TEST-POS-SALES-WEB-001
risks:
  - RISK-POS-SALES-WEB-001
---

# ADR-0040 POS Sales Workspace Boundary

## Decision

The POS sales page composes existing bounded contracts through authenticated
HTTP transport. Barcode input is resolved by Catalogue, product identity is
read from the tenant-scoped variant endpoint, and pricing is requested from an
active tenant price book. The browser never calculates tax or invents a price.

Finalization sends only the selected tenant context, register, warehouse,
price book, currency, quantities, and an idempotency key to the Sales boundary.
The server remains authoritative for open-shift eligibility, current
inventory, pricing validity, tax arithmetic, and sale persistence. A failed
finalization remains a visible generic error; the client does not retry with a
new key or claim success.

The page has explicit loading, missing-context, empty-catalogue, unknown-scan,
pricing-error, and finalization-error states. A sale cannot be finalized until
every line has a current server quote and the local work context contains an
active warehouse and register.

## Acceptance

- `POST /api/v1/catalogue/scan` and the variant detail endpoint return only
  active products in the current tenant.
- `GET /api/v1/sales/price-books` and `POST /api/v1/sales/quote` use active
  tenant pricing and server-side tax calculations.
- `POST /api/v1/sales/finalize` receives a bounded request with a unique
  idempotency key and preserves the existing shift/inventory/payment boundary.
- Web lint, typecheck, build, tests, repository architecture tests, and
  Graphify refresh pass. Database-backed PHP tests remain a CI/provisioned-host
  responsibility where PostgreSQL and Composer are available.

Related decisions: [[ADR-0039 POS Live Tenant Workspace Boundary]] and
[[ADR-0036 NOVA Frontend Design Rules]].
