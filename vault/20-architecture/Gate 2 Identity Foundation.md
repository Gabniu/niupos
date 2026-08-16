---
id: GATE-2-IAM-0001
type: implementation-evidence
status: in-progress
date: 2026-08-08
owners: [identity, security]
requirements: [REQ-G2-IAM-MEMBERSHIP-001, REQ-G2-IAM-PERMISSION-001, REQ-G2-IAM-SESSION-001, REQ-G2-AUDIT-SECURITY-001, REQ-G2-IAM-ADMIN-001, REQ-G2-AUDIT-TENANT-001]
adrs: [ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0007, ADR-0008]
modules: [MOD-IAM, MOD-TENANCY, MOD-AUDIT]
risks: [RISK-IAM-BOOTSTRAP-001, RISK-IAM-ROLE-DRIFT-001, RISK-IAM-TOKEN-001, RISK-IAM-BRUTE-FORCE-001, RISK-AUDIT-TAMPER-001, RISK-AUDIT-SECRET-001, RISK-IAM-PRIVILEGE-001, RISK-AUDIT-TENANT-LEAK-001]
tests: [TEST-G2-IAM-MEMBERSHIP-001, TEST-G2-IAM-PERMISSION-001, TEST-G2-IAM-SESSION-001, TEST-G2-IAM-HTTP-AUTH-001, TEST-G2-AUDIT-SECURITY-001, TEST-G2-IAM-ADMIN-001, TEST-G2-IAM-ADMIN-HTTP-001, TEST-G2-TENANT-AUDIT-RLS-001, TEST-G1-TENANT-BOUNDARIES-001]
---

# Gate 2 Identity Foundation

## Implemented slice

| Requirement | Implementation | Acceptance criteria | Tests | Risk | ADR |
|---|---|---|---|---|---|
| REQ-G2-IAM-MEMBERSHIP-001 — Admit a tenant scope only for an authenticated user with an active exact-tenant membership. | MOD-IAM owns the UUID user model, tenant-membership model and migration, and database authorization adapter. MOD-TENANCY owns the application contract and request-scope lifecycle. | Active exact membership allows admission. Unauthenticated, absent, inactive, malformed, or mismatched membership denies without establishing tenant context. Membership admission does not imply a role or business permission. | TEST-G2-IAM-MEMBERSHIP-001; TEST-G1-TENANT-BOUNDARIES-001 | RISK-IAM-BOOTSTRAP-001 | ADR-0004 |
| REQ-G2-IAM-PERMISSION-001 — Grant business capabilities only through an explicit permission on the member's active-tenant role. | MOD-IAM owns stable permission keys, the global permission catalogue, tenant roles, tenant role-permission assignments, and the scoped database authorizer. | Assigned permission allows only inside the matching tenant scope. Missing scope, unassigned permission, malformed key, inactive membership, and another tenant's role deny. | TEST-G2-IAM-PERMISSION-001; TEST-G1-TENANT-BOUNDARIES-001 | RISK-IAM-ROLE-DRIFT-001 | ADR-0004 |
| REQ-G2-IAM-SESSION-001 — Authenticate API users with expiring, revocable credentials without storing reusable bearer secrets. | MOD-IAM owns opaque session issuance, SHA-256 token digests, expiry/last-use/revocation state, authentication, and user-owned single/all-session revocation. | Raw token is returned once and never persisted. Unknown, expired, or revoked tokens deny. Revocation affects only the intended user's session records. Tenant membership and permissions remain separately evaluated. | TEST-G2-IAM-SESSION-001 | RISK-IAM-TOKEN-001 | ADR-0005 |
| REQ-G2-IAM-HTTP-AUTH-001 — Expose the session lifecycle through a versioned, throttled HTTP contract without revealing whether an account exists. | MOD-IAM owns `/api/v1/auth/login`, `/logout`, `/logout-all`, the Bearer-session middleware, generic errors, and normalized email/IP throttling. | Valid credentials issue a session. Invalid credentials are indistinguishable. The sixth matching attempt in one minute is throttled. Logout invalidates the current or all user sessions immediately. | TEST-G2-IAM-HTTP-AUTH-001 | RISK-IAM-BRUTE-FORCE-001; RISK-IAM-TOKEN-001 | ADR-0005 |
| REQ-G2-AUDIT-SECURITY-001 — Preserve immutable, secret-safe evidence for authentication and session-revocation outcomes. | MOD-AUDIT owns the recorder contract, append-only event model, persistence adapter, migration, and mutation-prevention triggers. MOD-IAM supplies allow-listed outcome metadata through the application contract. | Login success/failure and logout current/all create evidence. No raw email, password, bearer token, or reusable secret is persisted. Successful session mutation and evidence are atomic. Updates and deletes fail at the database. | TEST-G2-AUDIT-SECURITY-001; TEST-G2-IAM-HTTP-AUTH-001 | RISK-AUDIT-TAMPER-001; RISK-AUDIT-SECRET-001 | ADR-0006 |
| REQ-G2-IAM-ADMIN-001 / REQ-G2-AUDIT-TENANT-001 — Restrict tenant IAM changes to authorized active-tenant actors and preserve isolated evidence. | MOD-IAM owns the administration contract/service, `/api/v1/iam` transport, ordered authentication/tenant middleware, and exact-tenant mutations. MOD-AUDIT owns the separate RLS-protected append-only tenant evidence stream. | Required management permission is checked before mutation. Roles and memberships cannot cross tenants. Successful mutation and evidence are atomic. Unauthorized attempts produce no mutation or success evidence. Tenant audit rows are isolated by real PostgreSQL RLS. | TEST-G2-IAM-ADMIN-001; TEST-G2-IAM-ADMIN-HTTP-001; TEST-G2-TENANT-AUDIT-RLS-001 | RISK-IAM-PRIVILEGE-001; RISK-AUDIT-TENANT-LEAK-001 | ADR-0007 |

## Remaining Gate 2 work

- Implement password recovery, recovery codes, factor replacement/removal with step-up, layered edge throttling, emergency owner recovery, deployment RBAC/runbooks, retention, and evidence export controls.

REQ-G2-ORG-LOCATION-001 and TEST-G2-ORG-LOCATION-001 establish tenant-owned company, branch, and warehouse hierarchy primitives, tenant-qualified parent constraints, fail-closed application access, and PostgreSQL RLS under [[ADR-0011 Organization Location Primitives]]. Shift primitives remain open.

REQ-G2-REGISTER-DEVICE-001 and TEST-G2-REGISTER-DEVICE-001 establish tenant-scoped registers, digest-only one-time device enrollment, immutable public device identifiers, expiry/replay rejection, and active-device resolution under [[ADR-0014 Register and Device Enrollment Foundation]].

## MFA foundation

REQ-G2-IAM-MFA-001 and TEST-G2-IAM-TOTP-001 establish encrypted pending/active TOTP secrets, confirmation-before-activation, bounded clock skew, and secret-safe evidence under ADR-0009.

REQ-G2-IAM-MFA-002, TEST-G2-IAM-MFA-HTTP-001, and TEST-G2-IAM-MFA-REPLAY-001 add authenticated enrollment, per-session five-minute elevation, atomic accepted-step consumption across sessions, throttling, and success/failure evidence under [[ADR-0010 Session-Scoped MFA Elevation]].

## Owner safety extension

REQ-G2-IAM-OWNER-001 is implemented by the one-time tenant owner bootstrap and last-active-owner protection described in ADR-0008 and proven by TEST-G2-IAM-OWNER-001.

The operator-only Artisan adapter requires explicit attribution and confirmation or `--force`; TEST-G2-IAM-OWNER-CLI-001 proves success, attribution evidence, and replay denial.

Application-level ownership transfer requires an active owner, an active target member, deterministic row locks, and atomic tenant evidence; TEST-G2-IAM-OWNER-TRANSFER-001 proves the invariant.

REQ-G2-IAM-OWNER-TRANSFER-002 and TEST-G2-IAM-OWNER-HTTP-001 expose ownership transfer over HTTP only after authentication, exact-tenant admission, and current-session MFA elevation.
- Implement shift primitives, register sequence allocation, device capability profiles, and authenticated device transport.
- Add cross-tenant HTTP penetration tests and indistinguishable denial contracts.
- Add privileged membership administration with audit records; do not expose a general membership-listing repository.
