# NOVA Identity

Reusable authentication and application-access service built with Better Auth.

## Responsibilities

- Authenticate people and maintain their identity sessions.
- Provide email/password, verified-email recovery, and TOTP second factor.
- Manage identity-platform administrators, users, and organizations.
- Act as an OAuth 2.1 / OpenID Connect provider for NOVA and future apps.
- Register public and confidential OAuth clients with exact redirect URIs.

Application-specific authorization remains in each consuming application. NOVA
continues to own tenant admission, store access, roles, permissions, and audit
requirements.

## Local setup

1. Copy `.env.example` to `.env.local` and set a strong `BETTER_AUTH_SECRET`.
2. Create the PostgreSQL database referenced by `AUTH_DATABASE_URL`.
3. Run `npm --workspace apps/auth run auth:migrate`.
4. Run `npm --workspace apps/auth run dev`.
5. Open `http://127.0.0.1:3004`.

### First administrator

Create a high-entropy temporary `AUTH_BOOTSTRAP_TOKEN` (at least 32
characters), restart the service, and open `/setup`. Submit the token and the
first administrator details over HTTPS. The setup route is available only while
the token is configured and only when the Better Auth `user` table is empty.
After success, remove `AUTH_BOOTSTRAP_TOKEN`, restart the service, and verify
that `/sign-in` works. Never leave the bootstrap token configured in a shared
or production environment.

## Production container and hostname

The production image is built with `infra/docker/auth/Dockerfile` and can be
run with `infra/compose-auth.yaml`. It binds only to `127.0.0.1:3004` on the
host. The example reverse-proxy configuration is
`infra/reverse-proxy/novaauth.nginx.example.conf`; it terminates HTTPS for
`novaauth.niuautomations.com` and forwards internally to port 3004. Copy
`.env.production.example` to a deployment-only environment file and replace
every `REPLACE_ME` value through the server's secret manager.

Public registration is disabled unless `AUTH_ALLOW_PUBLIC_SIGN_UP=true`.
Password reset and verification delivery fail closed until email delivery is
configured. The default transport is the existing authenticated webhook. Resend
can be enabled with `AUTH_MAIL_PROVIDER=resend`, a verified sender in
`AUTH_MAIL_FROM`, and a server-only `AUTH_RESEND_API_KEY`. The control-plane
settings can instead reference those values through `delivery.provider`,
`delivery.from`, and `delivery.resendApiKey`; the API key is never stored in the
database. Resend requests use the HTTPS `/emails` API with a bearer key and a
bounded plain-text message payload.

### Self-hosted secret storage

NOVA Auth can resolve those secret references from a private self-hosted
Infisical project instead of the container environment. Set
`AUTH_SECRET_STORE=env` during migration. Switch to `infisical` only after the
server has a private Infisical endpoint, a narrowly scoped machine identity,
encrypted backups, a tested restore, and an outage runbook. Configure
`INFISICAL_API_URL`, `INFISICAL_PROJECT_ID`, `INFISICAL_ENVIRONMENT`,
`INFISICAL_SECRET_PATH`, `INFISICAL_CLIENT_ID`, and
`INFISICAL_CLIENT_SECRET` in the deployment secret environment. The Auth
backend obtains a short-lived machine token and reads only the configured
project/path; it never uses an Infisical root token.

Infisical's default environment display name `Production` uses the slug `prod`;
configure the slug, not the display label, in `INFISICAL_ENVIRONMENT`.

The administrator still logs in only to NOVA Auth. The browser never calls
Infisical and never receives raw values. Secret replacement is write-only at
`/admin/settings` and is disabled unless `AUTH_SECRET_STORE_WRITE_ENABLED=true`.
The write path now requires session-scoped MFA step-up through the
`/api/control/secrets/step-up` route. Keep the flag disabled until this flow is
verified in the production deployment. Audit evidence contains only the setting key and a
SHA-256 fingerprint, never the secret value. See [[ADR-0060 Server-side Secret
Manager Boundary]].

### Google sign-in

Google is an optional upstream sign-in provider inside NOVA Auth. Configure it
with `AUTH_GOOGLE_ENABLED=true`, the Google web OAuth client ID in
`GOOGLE_CLIENT_ID`, and the client secret in the server-only
`GOOGLE_CLIENT_SECRET` environment variable. The redirect URI registered in
Google Cloud must be exactly:

```text
https://novaauth.niuautomations.com/api/auth/callback/google
```

For local development use the matching local Auth origin. The admin control
plane exposes the same values as `social.google.*` settings, but stores only a
secret-reference name for the client secret. Customers can use Google to
authenticate into NOVA Auth, after which consuming apps still use NOVA OAuth
and local tenant authorization; Google credentials are never given to them.

### Account recovery

Use `/forgot-password` with the administrator's sign-in email. The page always
returns a generic response, and a reset message is issued only when a configured
mail transport can deliver it. If the administrator is locked out before mail
delivery is enabled, do not edit password hashes or identity rows by hand; use
an approved server-side recovery runbook and record the operator evidence. The
emergency owner-recovery workflow remains intentionally deferred.

## Consumer integration

Consumers use OAuth 2.1 Authorization Code + PKCE and discover metadata from the
service's OpenID Connect discovery endpoint. Confidential clients keep their
secret server-side; browser and mobile clients are registered as public clients
and never receive a client secret.

### Recommended connection pattern

Do not give every application an authentication API key. Human sign-in should
use the Authorization Code flow with PKCE:

1. Register the application in the Identity admin console with exact redirect
   URIs and a client type (public or confidential).
2. Use the issuer's discovery document at
   `/.well-known/openid-configuration/api/auth` to obtain authorization,
   token, userinfo, and JWKS endpoints.
3. Redirect the user to authorization with `state`, `nonce`, and PKCE
   `code_challenge` values.
4. Exchange the code server-side for confidential clients, or from the public
   client for browser/mobile apps, then validate issuer, audience, signature,
   expiry, nonce, and PKCE results.
5. Map the stable identity subject to the consuming application's own user,
   tenant, store, roles, and permissions. Never treat an identity token as a
   tenant authorization decision.

Machine-to-machine credentials are a separate concern. When a future service
needs them, add a scoped Better Auth API-key/resource-server capability and
manage those credentials as write-only values with rotation and revocation;
do not reuse browser client secrets or human sessions.
