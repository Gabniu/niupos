---
type: adr
status: accepted
date: 2026-08-11
requirements:
  - REQ-IAM-SHARED-001
tests:
  - TEST-IAM-SHARED-001
risks:
  - RISK-IAM-FEDERATION-001
---

# ADR-0037 Shared Better Auth Identity Provider

## Context

NOVA currently authenticates users through its Laravel Identity module and
issues opaque API sessions. Future applications need a shared authentication
experience and centrally managed users, sessions, organizations, and OAuth
clients. Sharing NOVA's tenant authorization model directly would couple every
future application to POS-specific roles and permissions.

## Decision

Add `apps/auth`, an independently deployable Next.js service using Better Auth
1.7.0-beta.10 with PostgreSQL. It provides email/password authentication, verified-email
recovery, TOTP two-factor authentication, platform administration,
organizations, and an OAuth 2.1/OpenID Connect provider.

Dynamic unauthenticated client registration is disabled. Client redirect URIs
are exact and pre-registered. Public browser/mobile clients use Authorization
Code with PKCE and no secret. Confidential clients keep secrets server-side.
Client secrets are displayed only at creation; the original plaintext is never
retrievable. An administrator who loses one must complete MFA-protected secret
rotation, which invalidates the prior secret and displays the replacement once.

The planned production issuer is `https://novaauth.niuautomations.com`. Human
clients use OAuth/OIDC rather than API keys; future machine credentials must be
scoped, rotatable, and separately revocable.

The control plane stores typed settings with optimistic versions, activation
modes, write-only secret references, and immutable audit events. Its frontend
is data-backed: users, sessions, OAuth applications, capabilities,
configuration, and audit history are read from Better Auth or PostgreSQL and
never seeded with example rows.

The identity provider proves identity and issues interoperable tokens. Each
consumer remains authoritative for its own domain authorization. NOVA therefore
continues to own tenant membership, branch/store admission, permissions, RLS
context, session-sensitive elevation policy during migration, and audit rules.
NOVA's existing opaque sessions remain active until an explicit token-validation
adapter and migration acceptance suite are implemented.

## Acceptance

- REQ-IAM-SHARED-001: Administrators can manage real Better Auth users and OAuth
  clients through a responsive frontend, and consuming applications can use the
  provider's OAuth 2.1/OIDC endpoints.
- TEST-IAM-SHARED-001: Configuration tests prove public registration is
  fail-closed and trusted origins are normalized; lint, build, and Better Auth
  schema generation must pass before deployment.

## Risks

- RISK-IAM-FEDERATION-001: Replacing NOVA sessions without dual-validation,
  subject mapping, token revocation, tenant re-authorization, and audit evidence
  could broaden access or strand active sessions. Migration is therefore staged,
  reversible, and separately accepted.

## Links

- [[ADR-0004 IAM Tenant Membership Authorization]]
- [[ADR-0005 Opaque API Sessions and Revocation]]
- [[ADR-0007 MFA Secrets and Session Elevation]]
- [[ADR-0036 NOVA Frontend Design Rules]]
- [[NOVA MVP Execution Plan]]
- [[Auth Pending Tasks]]
