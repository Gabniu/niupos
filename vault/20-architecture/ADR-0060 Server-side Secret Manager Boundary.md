# ADR-0060 Server-side Secret Manager Boundary

Status: Accepted for staged rollout (private stack deployed; Resend read cutover verified; provider hardening pending)

## Decision

NOVA Auth remains the only regular administrative user interface. A private
self-hosted Infisical instance stores raw provider credentials such as Google
OAuth client secrets and Resend API keys. The browser sends a replacement
secret only to the authenticated NOVA Auth backend; it never receives a secret
from Infisical and never calls Infisical directly.

The control plane continues to store only typed secret references. When
`AUTH_SECRET_STORE=env`, references resolve from the deployment environment for
backward-compatible migration. When `AUTH_SECRET_STORE=infisical`, the Auth
backend authenticates with a narrowly scoped Infisical machine identity and
reads the referenced key from the configured project, environment, and path.
Infisical Universal Auth tokens are short-lived and cached only in process
memory. The application must never use an Infisical root token.

The write endpoint is disabled by default and requires an explicit
`AUTH_SECRET_STORE_WRITE_ENABLED=true` deployment setting. It accepts only
known secret-reference settings, requires an administrator session, a matching
trusted `Origin`, and JSON content. Raw values are sent to the vault and are
never written to PostgreSQL, the browser response, logs, Graphify, or audit
payloads. Audit evidence stores only the setting key and a SHA-256 fingerprint.

## Required production controls before enabling writes

- Keep Infisical private to the server/Docker network with TLS on any remote
  connection; do not publish its database or admin API publicly.
- Use separate project/environment/path scopes for staging and production.
- Give the Auth machine identity access only to `nova-auth/production` secrets.
- Enable administrator MFA and require the implemented session-scoped step-up
  confirmation at `/api/control/secrets/step-up` before every write. Verify
  this flow in the production deployment before enabling the write flag.
- Protect Infisical encryption/unseal material and encrypted backups separately;
  test restoration and a vault-outage recovery procedure.
- Version and test provider rotations, retain a last-known-good rollback path,
  and keep the environment fallback until a production-like migration has
  passed.
- Pin and patch the Infisical image, restrict egress, and monitor authentication
  and secret-access audit events.

## Consequences

This removes raw provider values from the Auth `.env` over time and gives one
central place for rotation and audit. It also creates a new high-value
dependency: the machine identity is the secret zero, and a compromised vault
could expose multiple application credentials. The default-disabled write path,
least-privilege policy, private network, redaction, backups, and staged fallback
are therefore mandatory rather than optional hardening.

## Traceability

- Requirement: `REQ-AUTH-SECRET-MANAGER-001` — raw provider secrets are stored
  and rotated server-side without browser retrieval.
- Tests: `TEST-AUTH-SECRET-MANAGER-001` — resolver authentication/token reuse,
  path scoping, write-only behavior, invalid reference rejection, and safe
  failure.
- Related: [[ADR-0037 Shared Better Auth Identity Provider]],
  [[ADR-0058 Resend Transactional Email Provider]], and
  [[ADR-0059 Google Upstream Sign-in Broker]].
