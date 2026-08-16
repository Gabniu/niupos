---
id: ADR-0051
title: POS NOVA Identity Federation Staging Boundary
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
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
  - "[[ADR-0050 NOVA Auth Container Healthcheck]]"
---

# ADR-0051 — POS NOVA Identity Federation Staging Boundary

## Decision

Prepare POS for OAuth 2.1/OIDC Authorization Code + PKCE without changing the
existing local login or bearer-session behavior. Typed configuration records
the issuer, registered client, exact callback, audience, clock tolerance, and
discovery cache policy. A narrow discovery client validates HTTPS metadata,
issuer equality, required endpoints, and S256 PKCE support before any future
token verifier can use it.

The federated bearer resolver remains fail-closed. No identity token is trusted,
no user is auto-linked by email, and no production login route switches until
the verifier, state/nonce/PKCE callback, stable-subject mapping, tenant
admission, revocation behavior, and dual-validation rollback suite are accepted.

## Consequences

The existing opaque POS sessions remain safe and operational while the provider
registration and verifier are completed. Discovery is cached only after strict
HTTPS and issuer checks; endpoint values are read from provider metadata rather
than hardcoded.

The discovery regression test locks NOVA Auth's provider-specific metadata path
(`/.well-known/openid-configuration/api/auth`) and requires `S256` support before
any future authorization request is constructed.
