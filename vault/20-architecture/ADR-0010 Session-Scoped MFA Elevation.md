---
id: ADR-0010
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-MFA-002, REQ-G2-IAM-OWNER-TRANSFER-002]
modules: [MOD-IAM, MOD-AUDIT]
risks: [RISK-IAM-MFA-REPLAY-001, RISK-IAM-PRIVILEGE-001]
tests: [TEST-G2-IAM-MFA-HTTP-001, TEST-G2-IAM-MFA-REPLAY-001, TEST-G2-IAM-OWNER-HTTP-001]
---

# ADR-0010 — Session-scoped MFA elevation

## Decision

Authenticated users may begin and confirm initial TOTP enrollment through MOD-IAM HTTP endpoints. An enabled factor cannot be replaced through this enrollment flow. Confirmation and elevation attempts are throttled per authenticated session and client IP.

A valid TOTP code elevates only the current opaque API session for five minutes. Verification and accepted-step consumption execute atomically under a user-row lock. The accepted counter is stored on the user factor, so one TOTP time step cannot elevate multiple sessions even though verification permits bounded clock skew. Successful and failed elevation attempts create secret-free global security evidence.

The ownership-transfer HTTP route requires normal authentication, exact-tenant admission, an active-owner application invariant, and current session MFA elevation. The underlying transfer remains atomic and tenant-audited.

## Traceability

- REQ-G2-IAM-MFA-002: enrollment requires authentication; elevation is session-scoped, expires after five minutes, consumes each accepted TOTP step once, and emits secret-free evidence.
- REQ-G2-IAM-OWNER-TRANSFER-002: remote ownership transfer must require a current MFA elevation in addition to tenant and owner authorization.
- TEST-G2-IAM-MFA-HTTP-001 proves authenticated enrollment, confirmation, elevation persistence, audit evidence, and replacement denial.
- TEST-G2-IAM-MFA-REPLAY-001 proves the same TOTP time step cannot elevate two API sessions.
- TEST-G2-IAM-OWNER-HTTP-001 proves ownership transfer fails without elevation and succeeds after current-session elevation.

## Deferred scope

Recovery codes, factor replacement/removal with step-up, password recovery, device binding, and emergency owner recovery remain future slices.
