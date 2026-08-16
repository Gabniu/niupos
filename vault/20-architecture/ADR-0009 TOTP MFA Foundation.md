---
id: ADR-0009
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-MFA-001]
modules: [MOD-IAM, MOD-AUDIT]
risks: [RISK-IAM-MFA-SECRET-001, RISK-IAM-MFA-REPLAY-001]
tests: [TEST-G2-IAM-TOTP-001]
---

# ADR-0009 — TOTP MFA foundation

## Decision

MOD-IAM supports RFC-style TOTP enrollment using a random 160-bit Base32 secret, SHA-1 HMAC, six digits, and 30-second steps. Enrollment writes an encrypted pending secret; the active factor is unchanged until a valid pending-secret code confirms enrollment. Verification accepts the current step and one adjacent step on either side for bounded clock skew.

Pending and active secrets use Laravel encrypted casts and are hidden from serialization. The raw secret and `otpauth` URI are returned only when enrollment begins. Confirmation records secret-free `identity.mfa.totp_enabled` evidence.

## Traceability

- REQ-G2-IAM-MFA-001: TOTP secrets must be high entropy, encrypted at rest, confirmed before activation, bounded for clock skew, and absent from logs/audit evidence.
- TEST-G2-IAM-TOTP-001 proves encrypted storage, URI issuance, invalid-code denial, confirmation, current/adjacent-step verification, and secret-safe evidence.
- RISK-IAM-MFA-SECRET-001 is controlled by encryption, hidden serialization, and secret-exclusion tests.
- RISK-IAM-MFA-REPLAY-001 is controlled by atomic accepted-step consumption and session-scoped elevation under [[ADR-0010 Session-Scoped MFA Elevation]].

## Deferred scope

Recovery codes and factor replacement/removal with step-up remain future slices. Authenticated enrollment, replay prevention, short-lived session elevation, and MFA-protected ownership-transfer transport are implemented under [[ADR-0010 Session-Scoped MFA Elevation]].
