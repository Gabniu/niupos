---
id: ADR-0015
title: Pricing Tax and Rounding Foundation
status: accepted
date: 2026-08-08
requirements:
  - REQ-G3-PRICING-001
tests:
  - TEST-G3-PRICING-001
risks:
  - RISK-PRICING-ROUNDING-001
  - RISK-PRICING-OVERLAP-001
---

# ADR-0015 Pricing Tax and Rounding Foundation

## Context

NOVA needs deterministic tenant pricing before cart, sales, payment, promotion, or fiscal integration work can rely on monetary totals. Floating-point arithmetic, ambiguous price-window boundaries, and cross-tenant catalogue references would make downstream totals unsafe.

## Decision

- Store price amounts as non-negative integer minor units and tax rates as non-negative integer basis points.
- Store whether each active tax category is inclusive or exclusive.
- Calculate tax exclusively with integer arithmetic and explicit half-up rounding.
- For inclusive tax, split gross using `tax = round_half_up(gross × rate / (10000 + rate))`. For exclusive tax, calculate `tax = round_half_up(net × rate / 10000)`.
- Price books use normalized three-letter ISO currency codes. Currency conversion is outside this boundary.
- A product price references tenant-qualified price-book, tax-category, and catalogue-variant identities.
- Effective windows are half-open: `effective_from` is inclusive and `effective_until` is exclusive. A null end is unbounded.
- Reject overlapping windows for the same tenant, price book, and variant inside a transaction while locking the price book and candidate price rows.
- Force PostgreSQL row-level security on every tenant-owned pricing table.
- Depend on Catalogue through `ActiveVariantLookup`; Pricing does not import Catalogue domain models.

## Traceability

- **REQ-G3-PRICING-001:** Resolve exactly one effective, tenant-owned price and calculate deterministic inclusive or exclusive tax.
- **TEST-G3-PRICING-001:** Verify half-up rounding, inclusive/exclusive splits, half-open selection, overlap rejection, invalid inputs, inactive references, and tenant isolation.
- **RISK-PRICING-ROUNDING-001:** Different runtimes may round monetary fractions differently. Mitigation: integer-only formulas with explicit half-up behavior and boundary tests.
- **RISK-PRICING-OVERLAP-001:** Concurrent writes could create ambiguous effective prices. Mitigation: application validation inside a transaction with parent price-book locking; PostgreSQL exclusion constraints may be added if later write paths bypass this service.

## Consequences

Pricing is ready as a foundation for later cart and sales work, but it does not implement discounts, promotions, tax filing, Kenya eTIMS submission, sales, inventory, HTTP endpoints, or currency conversion.
