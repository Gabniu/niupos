---
type: adr
status: accepted
date: 2026-08-14
owner: identity-platform
---

# ADR-0058 Resend Transactional Email Provider

## Context

NOVA Auth must deliver verification and password-recovery messages without
placing provider credentials in the database, browser, image, or logs. The
existing authenticated webhook remains useful for private deployments, while
Resend provides a managed HTTPS transactional-email transport.

## Decision

NOVA Auth supports two explicit delivery transports: `webhook` (the default)
and `resend`. Resend sends a bounded plain-text payload to
`https://api.resend.com/emails` with a server-side bearer key, a verified
sender, and a fixed `User-Agent`. The control plane exposes the provider,
sender, and an uppercase secret-reference name for the API key. The key value
is resolved only from the deployment secret environment and is never persisted
or returned by the admin UI.

Resend is enabled only after the deployment supplies a rotated API key and a
verified `AUTH_MAIL_FROM` address (or equivalent control-plane value). Missing
provider configuration or a non-2xx provider response fails closed with a
generic internal delivery error; no reset or verification link is exposed.

## Consequences

- Password recovery and email verification can use a managed provider without
  coupling Auth to a consuming application.
- Sender/domain verification and provider rate limits become deployment
  responsibilities.
- Production must keep `AUTH_RESEND_API_KEY` in its secret manager and rotate it
  after accidental exposure.
- The webhook transport remains available for local and private environments.

## Traceability

- REQ-AUTH-MAIL-001: deliver verification and recovery mail through a typed,
  secret-safe provider boundary.
- TEST-AUTH-MAIL-001: Resend request shape, bearer handling, missing-secret
  fail-closed behavior, and provider error handling.
- RISK-AUTH-MAIL-SECRET-001: provider credentials must never enter source,
  control-plane values, Graphify, or logs.
- Implementation: `apps/auth/src/lib/email.ts`, `apps/auth/src/lib/auth.ts`,
  and `apps/auth/src/lib/control-plane.ts`.
