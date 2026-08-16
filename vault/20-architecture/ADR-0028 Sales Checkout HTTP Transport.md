---
id: ADR-0028
title: Sales Checkout HTTP Transport
status: accepted
date: 2026-08-08
requirements:
  - REQ-SALES-CHECKOUT
  - REQ-PAY-CASH
modules:
  - MOD-SALES
tests:
  - TEST-SALES-HTTP-001
risks:
  - RISK-IDEMPOTENCY-REPLAY
  - RISK-ACTOR-SPOOFING
---

# ADR-0028 Sales Checkout HTTP Transport

## Decision

Expose versioned authenticated endpoints for finalizing a sale and atomically completing an existing sale with cash. The transport delegates to `SalesCheckout` and `CashSaleCompletion`; it contains no pricing, stock, payment, or receipt business logic.

Middleware executes authentication, tenant admission, checkout permission authorization, then session-scoped throttling. Actor identity always comes from the authenticated session and is not accepted in request payloads. `Idempotency-Key` is a required bounded printable header. UUIDs, positive integer quantities, uppercase ISO currency codes, bounded line collections, and offset timestamps are validated before contract invocation.

Both a first execution and a successful idempotent replay return the same immutable resource representation with HTTP 201 because the current application contracts intentionally do not expose replay provenance. This avoids transport inference and preserves stable retry semantics.

## Consequences

- HTTP concerns remain at the Sales application boundary.
- Cash completion reuses the cross-module application orchestration contract without duplicating payment or receipt behavior.
- Clients may retry safely using the same key and payload.
- Distinguishing a replay would require a future explicit contract result field and is not inferred here.

## Verification

`TEST-SALES-HTTP-001` covers middleware ordering, validation, session-derived actors, contract mapping, stable success/replay representations, and throttling.
