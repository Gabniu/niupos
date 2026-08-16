---
id: ADR-0054
title: POS OIDC Callback Fail-Closed Boundary
status: accepted
date: 2026-08-13
requirements:
  - REQ-IAM-SHARED-001
tests:
  - apps/api/tests/Feature/Modules/Identity/FederatedIdentityResolverTest.php
risks:
  - RISK-IAM-FEDERATION-001
modules:
  - MOD-IAM
related:
  - "[[ADR-0053 POS OIDC Authorization Start Transaction]]"
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
---

# ADR-0054 — POS OIDC Callback Fail-Closed Boundary

## Decision

The staged callback consumes a cached authorization transaction only once and
rejects malformed, expired, replayed, denied, or code-less callbacks. It then
stops with a generic unavailable response until the token exchange, JOSE/JWKS
signature validation, issuer/audience/nonce checks, stable-subject mapping, and
tenant admission implementation is accepted.

No authorization code, token, nonce, or verifier is logged or returned. No
callback can issue a POS session while the verifier is disabled.
