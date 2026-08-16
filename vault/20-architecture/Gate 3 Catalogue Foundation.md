---
id: GATE-3-CATALOGUE-0001
type: implementation-evidence
status: in-progress
date: 2026-08-08
owners: [catalogue, architecture]
requirements: [REQ-G3-CAT-001]
adrs: [ADR-0001, ADR-0003, ADR-0012, ADR-0015, ADR-0019]
modules: [MOD-CATALOGUE, MOD-TENANCY]
risks: [RISK-CAT-TENANT-001, RISK-CAT-IDENTITY-001]
tests: [TEST-G3-CAT-001, TEST-G1-POSTGRES-RLS-001]
---

# Gate 3 Catalogue Foundation

## Implemented slice

REQ-G3-CAT-001 establishes tenant-owned categories, units of measure, products, variants, SKUs, and barcode identities. MOD-CATALOGUE normalizes SKU and barcode identities, enforces tenant-local uniqueness, rejects cross-tenant category/unit/variant references, resolves only active barcodes and variants inside the current `TenantContext`, and applies forced PostgreSQL RLS to every catalogue table.

TEST-G3-CAT-001 proves product/default-variant creation, normalization, duplicate rejection, unknown barcode behavior, exact-tenant resolution, and composite-key rejection of cross-tenant references. TEST-G1-POSTGRES-RLS-001 includes a real PostgreSQL catalogue-barcode visibility proof.

REQ-G3-PRICING-001 and TEST-G3-PRICING-001 add tenant-owned tax categories, price books, half-open effective product-price windows, overlap rejection, and deterministic integer half-up tax calculation under [[ADR-0015 Pricing Tax and Rounding Foundation]]. Catalogue exposes active variant identity through an application contract; Pricing does not import Catalogue domain internals.

REQ-CAT-SCAN-001 and REQ-CAT-SCAN-002 add normalized barcode, manual, keyboard-wedge, and camera input through a tenant-safe versioned HTTP boundary under [[ADR-0019-scanner-resolution-boundary]]. The scanner returns generic found/unknown outcomes, protects reads with authentication, tenant admission, catalogue permission, and throttling, and parses the initial weighted-EAN policy without pricing, cart, or inventory side effects.

## Remaining Gate 3 work

- Product and variant lifecycle HTTP contracts, richer attributes, category hierarchy, unit conversions, weighted-item metadata, and import/export.
- Price rules, Kenya tax mapping, jurisdiction-effective tax changes, promotions, and price/tax HTTP contracts.
- Device-specific scanner adapters, check-digit profiles, and hardware certification.
- Local catalogue projection, search, degraded-mode behavior, duplicate temporal behavior, and end-to-end checkout scan tests.
