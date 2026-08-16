---
id: ADR-0020
title: Checkout Pricing Quote Boundary
status: accepted
date: 2026-08-08
requirements:
  - REQ-G4-PRICING-QUOTE-001
tests:
  - TEST-G4-PRICING-QUOTE-001
risks:
  - RISK-PRICING-QUOTE-OVERFLOW-001
  - RISK-PRICING-QUOTE-DRIFT-001
related:
  - ADR-0015
  - PLAN-NOVA-MVP-EXECUTION-0001
---

# ADR-0020 Checkout Pricing Quote Boundary

## Context

Batch 4 in [[NOVA MVP Execution Plan]] requires Sales to snapshot a line's monetary meaning without importing Pricing domain models or repeating price and tax rules. Resolving only a mutable `ProductPrice` leaves Sales to perform tax arithmetic, active-state checks, and currency validation itself.

## Decision

- Pricing exposes `CheckoutQuoteProvider`, an application contract accepting a price-book identity, active variant identity, positive integer sale-unit quantity, ISO currency, and effective timestamp.
- The returned immutable value contains the variant and quantity, normalized currency, unit price, net/tax/gross totals, tax category identity/code/rate/inclusive mode, price-book and price identities, and the exact quote timestamp.
- Effective price windows remain half-open according to [[ADR-0015 Pricing Tax and Rounding Foundation]]. The price book, variant, effective price, and tax category must all be active and tenant-owned at quote time.
- Currency conversion is forbidden. The requested currency must equal the selected price book currency.
- Line multiplication, tax numerator multiplication, gross addition, and half-up rounding use checked integer arithmetic. Overflow is rejected instead of wrapping or converting to floating point.
- The quote is a deterministic snapshot input. Later Pricing changes do not reinterpret a stored Sale line.
- Sales consumes only the application contract and DTO. Pricing does not import Sales domain or infrastructure types.

## Traceability

- **REQ-G4-PRICING-QUOTE-001:** Produce a tenant-safe, deterministic, immutable checkout line quote with sufficient price and tax provenance for a Sale snapshot.
- **TEST-G4-PRICING-QUOTE-001:** Verify exclusive and inclusive tax, quantity multiplication and rounding, half-open effective boundaries, currency mismatch, invalid quantity, missing/inactive records, tenant isolation, overflow rejection, and repeated snapshot determinism.
- **RISK-PRICING-QUOTE-OVERFLOW-001:** Extreme price, quantity, or tax values can exceed runtime integer capacity. Mitigation: checked multiplication and addition with explicit rejection.
- **RISK-PRICING-QUOTE-DRIFT-001:** Recalculating historical lines from current price or tax records can change completed-sale totals. Mitigation: return all monetary values and source identifiers required for an immutable Sale-line snapshot.

## Consequences

Sales can now request one authoritative quote without coupling to Pricing persistence. The boundary does not implement carts, promotions, discounts, HTTP endpoints, currency conversion, sale aggregation, inventory mutation, or tender processing.
