---
type: review
status: open
date: 2026-08-14
owner: identity-platform
---

# Auth pending tasks

The shared Better Auth service has a working frontend, control-plane surfaces,
one-time admin bootstrap, deployment scaffolding, and a fail-closed Laravel
federation boundary. POS work can proceed independently while these production
items remain open.

## Recent UX hardening

The first-run administrator flow now has an explicit pending state, disabled
controls during submission, a dedicated completion confirmation, automatic
sign-in handoff, and a friendly recovery path when the initial request already
completed and a retry receives HTTP 409. Database-backed E2E evidence is still
required before marking bootstrap production-ready.

The sign-in page also passes production prerendering after its query-string
reader was placed behind a Suspense boundary. Auth lint, TypeScript, tests
(10/10), and the production build pass with inert build-only configuration.

## Must close before production

- [x] Provision dedicated PostgreSQL credentials and generate/apply Better Auth
  and control-plane schemas on the deployed identity stack.
- [x] Run the real first-admin bootstrap and remove the bootstrap token
  afterward; the bootstrap API now fails closed.
- [x] Complete database-backed E2E for login, logout, TOTP, OAuth
  authorization/consent/PKCE token exchange, userinfo, and admin writes.
  Disposable PostgreSQL E2E proves bootstrap, password sign-in, session
  retrieval, logout, post-logout denial, generic bad-credential denial, TOTP
  enable/verification/disable, an audited admin setting write, and a temporary
  confidential OAuth client through authorization → consent → token → userinfo
  against the deployed Auth image. All temporary state is removed after the run.
- [ ] Complete a real password-recovery E2E after a secure mail provider/webhook
  is configured. Until then delivery intentionally fails closed and no recovery
  link is issued.

Resend delivery support is enabled in the deployed Auth container through the
`AUTH_RESEND_API_KEY` Infisical reference and a verified sender. A real
password-recovery delivery assertion remains open; no raw API key belongs in
this note or the control-plane database.

Google upstream sign-in is implemented but remains disabled by default. To offer
customers a Google button without exposing Google credentials to consuming apps,
configure the Auth server with `AUTH_GOOGLE_ENABLED=true`, `GOOGLE_CLIENT_ID`,
and `GOOGLE_CLIENT_SECRET` (or the audited control-plane equivalents, with the
secret represented only as `GOOGLE_CLIENT_SECRET`). Register the exact callback
`https://novaauth.niuautomations.com/api/auth/callback/google` in Google Cloud,
then recreate only the Auth service. Downstream applications still use NOVA
OAuth/OIDC and retain local tenant admission and business authorization. A
future tenant-scoped enterprise connection model is required before storing
different Google/IdP credentials per organization.
- [x] Add the POS OIDC/JWKS verifier boundary enforcing issuer, audience,
  EdDSA/Ed25519 signatures, expiry, nonce, clock tolerance, and JWKS selection.
- [x] Complete exact local subject mapping and active-tenant admission without
  email auto-linking or user creation.
- [x] Add the POS login's optional “Continue with NIU Auth” path and same-origin
  browser callback while preserving local email/password login.
- [ ] Complete consumer dual-session migration, provider revocation,
  tenant re-authorization, disabled/revoked-user, audit, and rollback tests.
- [ ] Register production consumers with exact redirect URIs, PKCE, scopes, and
  server-side secrets where required.
- [x] Deploy and verify `https://novaauth.niuautomations.com`: TLS, trusted
  origins, proxy forwarding, health checks, and security headers. Repeatable
  public checks are in `scripts/Test-AuthDeployment.ps1`.
- [ ] Verify secure cookies, rate limits, backups/restore, monitoring, and
  rollback with authenticated database-backed evidence.
- [x] Run the focused PHP/Composer/Docker federation suite in an isolated
  server-side PHP 8.5.3 container: 11 tests / 32 assertions passed; touched
  files pass Pint.
- [x] Clean up unrelated failures in the complete API suite and run the full
  suite on the provisioned host: 153 tests / 846 assertions passed; production
  containers were not modified.
- [x] Verify the deployed identity endpoint: HTTPS redirect, valid managed
  certificate, HSTS/security headers, healthy containers, and OIDC discovery.
- [x] Remove `AUTH_BOOTSTRAP_TOKEN` from production and restart the auth
  container. One identity user exists; the bootstrap API returns 404 while
  sign-in and OIDC discovery remain healthy.

## Evidence

Static lint, typecheck, build, and unit tests pass. The real database-backed
identity flow is not yet proven because local PostgreSQL credentials failed.
Each checkbox must be closed with a command/result and linked test or review;
never infer completion from placeholder or empty data.

Related decision: [[ADR-0037 Shared Better Auth Identity Provider]]
