---
id: ADR-0053
title: POS OIDC Authorization Start Transaction
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
  - "[[ADR-0051 POS Federation Staging Boundary]]"
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
---

# ADR-0053 — POS OIDC Authorization Start Transaction

## Decision

When federation is explicitly enabled and a registered HTTPS callback/client
are configured, `GET /api/v1/auth/federation/start` discovers the provider and
creates an authorization URL using Authorization Code + PKCE. It generates
independent high-entropy state, nonce, and verifier values, derives an S256
challenge, and stores the short-lived transaction server-side for ten minutes.

The endpoint returns a generic 404 while disabled or incomplete. It never
accepts tokens, creates a local session, logs secrets, or trusts a callback
until the separate code-exchange and validation slice is complete.

The consumer does not force `select_account`. When the user already has an
active NIU Auth browser session, the provider may continue without another
password; otherwise Better Auth presents its normal sign-in page. The POS web
callback at `/auth/callback` forwards only the one-time `state`, `code`, or
provider error to the same-origin API and stores only the resulting opaque POS
session token.

The feature tests verify both the disabled response and the enabled transaction
shape, including persisted state, nonce, verifier, redirect URI, and S256 URL
parameters. Cache contents are asserted without exposing verifier values.
