---
id: ADR-0029
title: Authenticated Payment Operations Transport
status: accepted
date: 2026-08-08
requirements:
  - REQ-PAYMENTS-0001
  - REQ-SECURITY-0001
modules:
  - Payments
tests:
  - apps/api/tests/Feature/Modules/Payments/PaymentOperationsHttpTest.php
risks:
  - RISK-PAYMENT-CALLBACK-AUTH
related:
  - ADR-0025
  - ADR-0027
---

# ADR-0029 Authenticated Payment Operations Transport

## Decision

Expose tenant payment commands under `/api/v1/payments` as authenticated operator operations. Initiation requires `payments.create`; applying an externally obtained terminal result requires the stronger `payments.providerresults.manage` permission (the canonical dotted key permitted by the Identity permission grammar). Both routes execute authentication, tenant admission, permission authorization, and session-scoped throttling before controller validation and delegation.

The initiating actor is always derived from the authenticated API session. Clients cannot select or spoof it. The transport accepts only `cash` and `mpesa`, exact integer minor amounts, ISO-style three-letter currencies, a bounded `Idempotency-Key`, and the provider metadata allowlist already enforced by `PaymentProcessor`. Result ingestion accepts a terminal status, bounded reference, and SHA-256 fingerprint. Responses expose the stable payment result projection and generic errors, never provider secrets or internal exception messages.

## Security boundary

`POST /attempts/{attempt}/provider-result` is an authenticated tenant operator endpoint. It is not an M-Pesa callback and must not be presented or configured as one.

A live provider webhook remains fail-closed and unimplemented until a separate profile defines signature verification, replay resistance, trusted tenant routing, credential rotation, request-size controls, network policy, audit evidence, and reconciliation. No unauthenticated route accepts provider results in this decision.

## Consequences

- POS and back-office clients receive a versioned transport over the existing provider-neutral application contract.
- Permission assignment becomes an explicit deployment responsibility; provider-result management should be narrowly granted.
- Live M-Pesa integration, callback authentication, reconciliation, and operational credential handling remain backlog work.

## Verification

`PaymentOperationsHttpTest` proves middleware order, permission separation, session-derived actor identity, validation and allowlisting, contract mapping, generic failures, and the authenticated-only result boundary.
