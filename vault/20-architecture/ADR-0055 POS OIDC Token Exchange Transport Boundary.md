---
id: ADR-0055
title: POS OIDC Token Exchange Transport Boundary
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
  - "[[ADR-0054 POS OIDC Callback Fail-Closed Boundary]]"
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
---

# ADR-0055 — POS OIDC Token Exchange Transport Boundary

## Decision

Add a server-side token transport that reads the discovered token endpoint,
submits the one-time authorization code with the PKCE verifier, and parses only
the bounded OAuth response envelope. Client secrets remain server-side and are
sent with HTTP Basic authentication when configured.

This transport is not connected to callback session issuance. The callback
continues to fail closed until a JOSE/JWKS implementation validates the ID token
signature and issuer, audience, nonce, expiry, and clock tolerance, followed by
stable subject mapping and tenant admission.
