---
id: ADR-0056
title: POS OIDC JWKS Identity Verification Boundary
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
  - "[[ADR-0055 POS OIDC Token Exchange Transport Boundary]]"
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
---

# ADR-0056 — POS OIDC JWKS Identity Verification Boundary

## Decision

Use a maintained JOSE implementation to verify NOVA Identity ID tokens against
discovered JWKS keys. The verifier must require a valid signature, the
discovered issuer, the registered client audience, the authorization transaction
nonce, a non-empty stable subject, and bounded `exp`/`iat` claims.

NOVA Auth currently advertises EdDSA ID tokens. `lcobucci/jwt` 5.6.0 resolves
against PHP 8.5 and passed `composer audit` with no advisories; it is now the
selected dependency. The Ed25519 JWKS key-material adapter and its claim-level
tests remain required before enabling this adapter. The callback remains
fail-closed until the verifier, subject mapping, tenant admission, and rollback
tests are deployed together.

The adapter selects only `OKP` / `Ed25519` / `EdDSA` keys by JWT `kid`, decodes
the public key from JWK `x`, and rejects malformed tokens before any network key
lookup. Focused tests cover this malformed-token fail-closed path.
