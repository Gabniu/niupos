# NOVA Auth pending work

This note is the explicit handoff for the shared Better Auth identity service.
The implemented frontend, control-plane routes, bootstrap flow, deployment
scaffolding, and fail-closed Laravel federation boundary are complete enough
for continued POS work. The items below remain open and must be closed before
calling the identity service production-ready.

## Recent UX hardening

The first-run administrator flow now disables the form while the request is in
flight, shows a visible progress state, presents a dedicated success screen,
redirects to sign-in, and treats a follow-up `409 Bootstrap is already complete`
as a successful-completion recovery path rather than an unexplained error. This
improves operator feedback but does not replace the required real database E2E
bootstrap evidence below.

The sign-in page now builds correctly under Next production prerendering by
isolating its query-string reader behind a Suspense boundary. Auth lint,
TypeScript, tests (16/16), and production build pass with inert build-only
configuration values; runtime secrets remain deployment-managed.

Application administration now guards registration against duplicate submits,
shows immediate pending/error/success states, and reveals a confidential
client's one-time secret with copy support. Administrators can select multiple
clients and delete them through the authenticated provider endpoint after an
explicit confirmation. Confidential client secrets can also be rotated after
MFA; the old value is invalidated and the replacement is shown once. Desktop
admin rails are viewport-bounded so only the content region scrolls.

## Database-backed verification

- [x] Provision the dedicated PostgreSQL role/database with valid credentials,
  generate/apply the Better Auth schema, and apply
  `migrations/0001_auth_control_plane.sql` on the deployed identity stack.
- [x] Execute the real first-admin bootstrap, remove the bootstrap token, and
  disable temporary signup. The production bootstrap API now fails closed.
- [x] Run database-backed end-to-end checks for login, logout, TOTP, OAuth
  authorization/consent/PKCE token exchange, userinfo, and admin control-plane
  writes. Disposable PostgreSQL E2E proves bootstrap, password sign-in, session
  retrieval, logout, post-logout denial, generic bad-credential denial, TOTP
  enable/verification/disable, an audited admin setting write, and a temporary
  confidential OAuth client through `/oauth2/authorize` → consent →
  `/oauth2/token` → `/oauth2/userinfo` against the deployed Auth image. The
  temporary database, client, user, tokens, and network are removed after each
  run.
- [ ] Complete a real password-recovery E2E after a secure mail provider/webhook
  is configured. Until then delivery intentionally fails closed and no recovery
  link is issued.

Resend support is implemented behind the typed `delivery.provider` setting and
the `delivery.resendApiKey` secret reference. The deployed Auth container now
uses the `resend` provider and reads its key from Infisical; the verified sender
is configured server-side. A real password-recovery delivery assertion is
still open.

Google upstream sign-in is implemented but disabled by default. It requires
server-side `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` plus the exact
production callback documented in `README.md`; the control-plane UI stores only
the secret reference. Password recovery remains open until a real mail
transport is configured and exercised end to end.

## Self-hosted secret manager rollout

- [x] Deploy the private Infisical backend, PostgreSQL, and Redis stack on the
  server with no public port; the one-time bootstrap UI is reachable only
  through an SSH tunnel on `127.0.0.1:3005`.
- [x] Add the Auth Infisical adapter, short-lived Universal Auth token cache,
  write-only secret route, trusted-origin checks, and administrator TOTP
  step-up confirmation.
- [x] Add encrypted age backup/restore scripts for the Infisical database and
  encryption configuration; keep the age identity offline.
- [x] Create the Infisical administrator, `NOVA Auth` production project/path,
  and least-privilege `nova-auth-production` machine identity. The operator
  completed this through the private Infisical UI; Auth reports the required
  machine configuration without exposing credential values.
- [x] Configure the Auth machine credentials server-side, copy the available
  Resend provider value into the `prod`/`/nova-auth` vault scope without
  printing it, and switch the deployed Auth read path to
  `AUTH_SECRET_STORE=infisical`. Google remains disabled until its optional
  client secret is configured.
- [ ] Verify provider rotation, rollback, backup restore, and vault-outage
  recovery in a production-like run before enabling additional providers.
- [ ] Enable `AUTH_SECRET_STORE_WRITE_ENABLED` only after production MFA
  step-up, secret replacement, audit fingerprint, and recovery E2E evidence
  are recorded.

## Federation and consumer readiness

- [x] Add the reviewed POS OIDC/JWKS verifier boundary. It enforces issuer,
  audience, EdDSA/Ed25519 allow-list, signature, expiry, clock tolerance,
  nonce, and JWKS key selection/cache behavior. Production federation remains
  disabled until deployment evidence is accepted.
- [x] Add exact local subject mapping and active-tenant admission. It never
  auto-links by email or creates users; the callback issues an opaque POS
  session only after verification and audit.
- [ ] Complete NOVA dual-session migration tests: provider revocation,
  disabled/revoked local identities, tenant re-authorization during an active
  session, rollback, and generic cross-tenant failures.
- [ ] Register each consuming app with exact production/staging redirect URIs,
  PKCE settings, scopes, and server-side confidential secrets where applicable.
- [ ] Add any remaining Better Auth plugins/providers only through the typed
  settings catalog, capability inventory, migrations, admin UI, tests, and ADR.

## Production operations

- [x] Deploy `apps/auth` at `https://novaauth.niuautomations.com` behind the
  reviewed reverse proxy and managed TLS certificate.
- [ ] Verify secure cookies, trusted origins, forwarded headers, rate limits,
  health checks, structured safe logs, backups, restore, monitoring, and alerts.
- [ ] Run the production-like rollback test without unexpectedly invalidating
  valid sessions.
- [x] Run the focused PHP/Composer/Docker Laravel federation suite in an
  isolated server-side PHP 8.5.3 container: 11 tests / 32 assertions passed;
  Pint passed on the touched files. The complete API suite now passes 153
  tests / 846 assertions on the provisioned host; production containers were
  not modified.

- [x] Verify the deployed identity endpoint: HTTPS redirects, Let's Encrypt
  certificate validity, HSTS, clickjacking/content-type protections, healthy
  auth/PostgreSQL containers, and OIDC discovery all pass on
  `novaauth.niuautomations.com`.
- [x] Remove `AUTH_BOOTSTRAP_TOKEN` from the production secret environment and
  restart the auth container. The identity database contains one user, the
  bootstrap API returns 404, and sign-in/OIDC discovery remain healthy.

## Evidence rule

Do not mark an item complete from a static build or an empty database. Attach
the command, environment, result, and relevant test/ADR link to this note (or
to a linked review note) when closing it.

Obsidian review note: [[Auth Pending Tasks]]. Related architecture decision:
[[ADR-0037 Shared Better Auth Identity Provider]].
