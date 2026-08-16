---
id: ADR-0041
title: POS Checkout Completion and Receipt Boundary
status: accepted
date: 2026-08-11
requirements:
  - REQ-POS-CHECKOUT-WEB-001
tests:
  - TEST-POS-CHECKOUT-WEB-001
risks:
  - RISK-POS-CHECKOUT-WEB-001
---

# ADR-0041 POS Checkout Completion and Receipt Boundary

## Decision

After Sales finalizes a sale, the POS web client enters a payment state. Cash
completion calls the existing cash-completion boundary, which atomically owns
the payment allocation, drawer movement, and receipt issuance. The browser does
not call those tables or attempt to reproduce the transaction.

The receipt screen reads the immutable receipt through the Receipts HTTP
reader. It renders server-provided lines, totals, currency, receipt number, and
issue time. A receipt identifier or number is never fabricated locally.

M-Pesa remains visibly pending until a provider-confirmed result is accepted by
the Payments boundary. The POS does not claim success, retry with a different
idempotency key, or expose provider secrets while that result is pending.

## Acceptance

- Cash completion uses a bounded idempotency key and surfaces generic failure
  states without clearing an unconfirmed transaction.
- A successful cash completion links to a tenant-scoped immutable receipt view.
- Receipt loading, not-found, and malformed-response states are explicit.
- Web lint, typecheck, build, tests, repository architecture tests, and
  `git diff --check` pass. PostgreSQL/PHP end-to-end evidence remains pending
  in the Auth/CI environment note.

Related decisions: [[ADR-0040 POS Sales Workspace Boundary]] and
[[ADR-0026 Receipts Foundation]].
