---
id: ADR-0026
title: Immutable Receipt Snapshots and Register Numbering
status: accepted
date: 2026-08-08
requirements:
  - REQ-G1-TENANT-ISOLATION-001
  - REQ-SALE-IDEMPOTENT-FINALIZATION-001
modules:
  - MOD-RECEIPTS
  - MOD-SALES
  - MOD-PAYMENTS
tests:
  - apps/api/tests/Feature/Modules/Receipts/ReceiptIssuerTest.php
risks:
  - RISK-RECEIPT-DUPLICATE-NUMBER
  - RISK-RECEIPT-MUTABLE-EVIDENCE
---

# ADR-0026 Immutable Receipt Snapshots and Register Numbering

## Context

A receipt must remain reproducible after catalogue, pricing, tax, user, or register data changes. Issuance must not imply Kenyan fiscal-authority acceptance, and it must not race into duplicate or skipped application-visible numbers during ordinary concurrent issuance.

## Decision

`MOD-RECEIPTS` consumes two narrow Application ports: a finalized-sale render snapshot and a fully-paid settlement decision. It never imports another module's Domain or Infrastructure classes and never recalculates sale values.

Issuance stores an immutable tenant-owned header and ordered immutable lines. The header snapshots sale, shift, register and seller identifiers, currency, totals, sale-finalization time, issuance time, and the idempotency fingerprint. Lines snapshot the variant identity, stable description or deterministic fallback, quantity, unit/net/tax/gross amounts, and tax code/rate/mode.

Receipt numbers are fiscal-neutral positive integers, monotonically allocated per tenant and register. Allocation occurs in the issuance transaction under a PostgreSQL advisory lock and a locked sequence row; database uniqueness is the final guard. One tenant sale can own exactly one receipt. Replaying the same command returns it, while conflicting key reuse is rejected.

Printer, email, and SMS attempts are append-only evidence containing only channel, outcome, timestamp and an optional bounded error code. They contain no address, body, credential, or provider secret.

All receipt tables use forced PostgreSQL RLS. Receipt headers, lines, and delivery attempts have model and database mutation guards. The sequence row is intentionally mutable and is not render evidence.

## Consequences

- Root integration must provide adapters for `ReceiptSaleSnapshots` and `ReceiptSettlementStatus`, and register `ReceiptsServiceProvider` after upstream modules.
- A receipt is issued only for a finalized sale whose settlement adapter reports the exact currency and gross amount fully paid.
- Rendering, networking, refunds, eTIMS integration, and fiscal-authority numbering remain separate later decisions.
- PostgreSQL deployment evidence must cover the four receipt tables and immutable triggers.

## Acceptance evidence

- `ReceiptIssuerTest` covers deterministic snapshot storage, description fallback, replay, unpaid and cross-tenant rejection, delivery evidence, and mutation rejection.
- Migration constraints prove tenant-scoped sale uniqueness, idempotency uniqueness, register-number uniqueness, composite tenant foreign keys, forced RLS, and append-only evidence triggers.
