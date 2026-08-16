---
id: ADR-0005
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-SESSION-001]
modules: [MOD-IAM]
risks: [RISK-IAM-TOKEN-001]
tests: [TEST-G2-IAM-SESSION-001, TEST-G2-IAM-HTTP-AUTH-001, TEST-G2-IAM-MEMBERSHIP-001, TEST-G2-IAM-PERMISSION-001]
---

# ADR-0005 — Opaque API sessions and revocation

## Decision

MOD-IAM issues first-party API sessions as cryptographically random 256-bit opaque bearer tokens. The raw token is returned only at issuance; persistence contains only its SHA-256 digest. Each session has an absolute expiry, optional last-use timestamp, and explicit revocation timestamp.

Authentication accepts only a non-empty token whose digest matches an unexpired, unrevoked session and whose user still exists. A user can revoke one owned session or all owned sessions. Session ownership is always included in revocation predicates so one user cannot revoke another user's credentials.

Authentication proves only user identity. Every tenant request must still pass ADR-0004 active-membership admission, and every business action must still pass the active-tenant permission decision. Sessions contain no embedded tenant or permission claims, preventing stale authorization from surviving membership or role changes.

## Traceability

- REQ-G2-IAM-SESSION-001: issue only opaque, high-entropy, expiring credentials; store no reusable bearer secret; support immediate single-session and account-wide revocation.
- TEST-G2-IAM-SESSION-001: database integration tests prove digest-only storage, successful authentication, expiry, unknown-token denial, isolated revocation, and account-wide revocation.
- RISK-IAM-TOKEN-001: a stolen bearer token can impersonate its user until expiry or revocation. Controls are TLS at transport, digest-only storage, bounded lifetime, explicit revocation, secret-safe logging, and future device/risk telemetry.

## Deferred scope

Credential recovery, MFA, token rotation, device binding, security-event audit records, and scheduled expired-session cleanup remain Gate 2 work.

## HTTP transport extension

Versioned `/api/v1/auth/login`, `/logout`, and `/logout-all` endpoints expose the session lifecycle. Login normalizes email only for lookup and throttling, returns the same public error for unknown users and bad passwords, and applies a five-attempt-per-minute limiter keyed by the hash of normalized email plus source IP. Protected endpoints accept only a Bearer token resolved by MOD-IAM middleware.

- TEST-G2-IAM-HTTP-AUTH-001 proves successful login/logout, digest-only storage, generic credential failures, normalized email/IP throttling, unauthenticated denial, and account-wide logout.
- RISK-IAM-BRUTE-FORCE-001: attackers may enumerate or guess credentials. Controls are generic errors, combined principal/source throttling, bounded password input, secret-safe logs, and future layered edge limits and security telemetry.
