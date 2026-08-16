---
type: adr
status: accepted
date: 2026-08-11
requirements:
  - REQ-IAM-FEDERATION-001
tests:
  - TEST-IAM-FEDERATION-001
risks:
  - RISK-IAM-FEDERATION-001
---

# ADR-0038 NOVA OIDC Federation Boundary

## Decision

NOVA introduces a narrow `FederatedIdentityResolver` contract in the Identity
module for the staged Better Auth migration. The current binding is explicitly
fail-closed and does not replace opaque API sessions. A future adapter must
validate the configured issuer, audience, JWKS signature and algorithm
allow-list, expiry, nonce/PKCE at the client boundary, token revocation policy,
and local subject mapping before creating a NOVA session.

The local user record stores a nullable `(identity_issuer, identity_subject)`
pair with a uniqueness constraint. Tenant admission, membership, permissions,
RLS context, business session elevation, and audit remain NOVA-owned after
identity resolution.

## Acceptance

- REQ-IAM-FEDERATION-001: a consumer can add a verified identity adapter
  without importing Better Auth internals or bypassing NOVA tenant checks.
- TEST-IAM-FEDERATION-001: the default resolver rejects all tokens until a
  reviewed issuer/JWKS adapter and dual-session migration tests are enabled.

## Risks

- RISK-IAM-FEDERATION-001: accepting a token without issuer/audience/JWKS
  validation or local tenant admission could grant cross-tenant access. The
  default remains fail-closed and the migration is reversible.

## Links

- [[ADR-0004 IAM Tenant Membership Authorization]]
- [[ADR-0005 Opaque API Sessions and Revocation]]
- [[ADR-0037 Shared Better Auth Identity Provider]]
