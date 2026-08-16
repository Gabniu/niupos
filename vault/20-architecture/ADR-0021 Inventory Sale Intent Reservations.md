---
id: ADR-0021
title: Inventory Sale Intent Reservations
status: accepted
date: 2026-08-08
requirements:
  - REQ-MVP-INVENTORY-RESERVATION-001
  - REQ-MVP-SALES-CHECKOUT-001
modules:
  - Inventory Module
tests:
  - apps/api/tests/Feature/Modules/Inventory/InventorySaleIntentTest.php
risks:
  - RISK-INVENTORY-OVERSELL-001
  - RISK-CHECKOUT-DUPLICATE-FINALIZE-001
related:
  - ADR-0017
  - ADR-0020
---

# ADR-0021 Inventory Sale Intent Reservations

## Context

Checkout needs an inventory boundary that can hold stock while a sale is being finalized without importing Sales domain or infrastructure code into Inventory. On-hand stock alone is insufficient because concurrent checkouts could both observe the same available quantity. Retries must not create duplicate reservations or stock movements.

## Decision

Inventory owns tenant-scoped stock reservations identified by a caller-supplied immutable UUID. A reservation records an active warehouse, active product variant, positive integer quantity, reservation idempotency key, and command fingerprint.

Availability is `on-hand - active reservations`. Reservation creation locks the stock-balance row and denies a command that would make availability negative. PostgreSQL advisory locks serialize idempotency identities while the balance row lock serializes competing reservations for the same stock identity.

The only transitions are:

- `active -> finalized`: append exactly one `sale` stock movement through the existing inventory ledger and consume the reservation.
- `active -> released`: consume the reservation without changing on-hand stock.

Terminal transitions replay only when their idempotency key and fingerprint match. Conflicting reuse and transitions from an already terminal reservation fail. Reversal, refund, transfer, stock-count, HTTP, and offline behavior remain outside this decision.

The `InventorySaleIntent` application contract is the cross-module boundary. It exposes availability, reserve, finalize, and release operations without depending on Sales classes.

## Data integrity and tenancy

- Composite tenant foreign keys bind reservations to warehouses, variants, and finalized stock movements.
- Forced PostgreSQL RLS applies the current tenant to reads and writes.
- PostgreSQL triggers protect immutable reservation facts and prohibit deletion.
- Finalization and its stock movement execute in one database transaction under row/advisory locks.
- Negative availability and negative on-hand stock are denied by default.

## Consequences

Sales can safely coordinate checkout through a stable Inventory application contract. Active reservations temporarily reduce sellable availability, release restores it, and finalization decrements on-hand exactly once. Expiration, cancellation policy, and reversals require later explicit decisions.

## Verification

`InventorySaleIntentTest` covers availability, oversell denial, idempotent replay and conflicts, finalize/release terminal behavior, exact-once stock decrement, tenant and active-reference checks, model immutability, locking shape, composite foreign keys, and forced RLS declarations.
