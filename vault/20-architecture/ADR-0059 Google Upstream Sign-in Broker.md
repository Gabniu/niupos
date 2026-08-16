---
type: adr
status: accepted
date: 2026-08-14
owner: identity-platform
---

# ADR-0059 Google Upstream Sign-in Broker

## Decision

NOVA Auth may broker Google sign-in as an optional upstream provider. Google
client credentials remain server-side and are configured through environment
secrets or the Auth control plane. When enabled, the Auth UI offers
“Continue with Google”; consuming applications still receive NOVA Auth
sessions/OIDC tokens and perform their own tenant and business authorization.

The production callback is exactly
`https://novaauth.niuautomations.com/api/auth/callback/google`. A client secret
is stored only by secret-reference name, never as a control-plane value. Google
sign-in is disabled by default. If it is enabled without both credentials, the
provider is omitted from Better Auth and a safe startup warning is emitted;
password/OIDC/Auth administration remain available so one optional provider
cannot lock out the identity service.

This is a shared-provider integration, not per-customer credential passthrough.
If a customer later requires its own Google Workspace tenant, add an explicit
enterprise connection model with tenant-scoped issuer/client secrets and
consent boundaries; do not put customer secrets in a global Google provider.

## Traceability

- REQ-AUTH-SOCIAL-001: optional Google sign-in with server-only credentials.
- TEST-AUTH-SOCIAL-001: hidden when disabled, visible when configured, missing
  credentials fail closed for Google while core Auth remains available, exact
  callback configuration.
- RISK-AUTH-SOCIAL-TENANT-001: upstream identity does not grant local tenant or
  business authorization.
