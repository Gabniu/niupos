---
id: ADR-0061
title: POS NIU Auth SSO Login Option
status: accepted
date: 2026-08-15
requirements:
  - REQ-IAM-FEDERATION-002
tests:
  - apps/api/tests/Feature/Modules/Identity/FederatedIdentityResolverTest.php
  - apps/web/src/app/auth/callback/page.tsx
risks:
  - RISK-IAM-FEDERATION-001
modules:
  - MOD-IAM
related:
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
  - "[[ADR-0051 POS Federation Staging Boundary]]"
  - "[[ADR-0057 POS Federated Subject Mapping and Tenant Admission]]"
---

# ADR-0061 — POS NIU Auth SSO Login Option

## Decision

NIU POS keeps its existing email/password login and adds a separate “Continue
with NIU Auth” option. The option uses OAuth 2.1/OpenID Connect Authorization
Code + PKCE against the shared Better Auth identity service. The provider's
browser session is reused when available, so an already signed-in NIU Auth user
can continue without entering a second password; a user without a provider
session is sent through the normal NIU Auth sign-in page.

The POS API remains the OAuth client and confidential client secret holder. The
browser receives only the final opaque POS bearer session. The callback page
passes the one-time code and state to the same-origin API, which consumes the
server-side PKCE transaction, validates issuer/audience/signature/nonce/expiry,
maps the exact provider subject to an existing POS user, verifies active tenant
membership, and issues the existing POS session. No email auto-linking, user
creation, token storage, refresh-token storage, or tenant claims are trusted.

If federation is disabled or unavailable, the SSO option fails closed with a
generic message and the local login remains usable. Production activation still
requires an exact registered callback at `/auth/callback`, server-only client
credentials, explicit subject linking, and the dual-session/revocation/rollback
acceptance evidence in the auth pending review.

## Traceability

- Start transaction: `apps/api/app/Modules/Identity/Infrastructure/DatabaseOidcAuthorizationService.php`.
- Browser callback: `apps/web/src/app/auth/callback/page.tsx`.
- Login entry point: `apps/web/src/app/page.tsx`.
- Callback/session boundary: `apps/api/app/Modules/Identity/Application/Http/AuthController.php`.
- Subject and tenant admission: `apps/api/app/Modules/Identity/Infrastructure/DatabaseFederatedIdentityMapper.php`.
- Focused backend coverage: `apps/api/tests/Feature/Modules/Identity/FederatedIdentityResolverTest.php`.

## Risks and follow-up

- Existing POS users must be explicitly linked to their NIU Auth subject; matching
  by email would permit account confusion and is prohibited.
- Provider session revocation and local session revocation still require the
  documented dual-validation and rollback suite before federation becomes the
  default login path.
- The production client ID, secret, issuer, and exact callback belong in the
  server deployment environment/secret manager, never in the web bundle.
