---
id: ADR-0006
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-AUDIT-SECURITY-001]
modules: [MOD-AUDIT, MOD-IAM]
risks: [RISK-AUDIT-TAMPER-001, RISK-AUDIT-SECRET-001]
tests: [TEST-G2-AUDIT-SECURITY-001, TEST-G2-IAM-HTTP-AUTH-001]
---

# ADR-0006 — Append-only security audit evidence

## Decision

MOD-AUDIT owns security audit persistence and exposes an application-level `SecurityAuditRecorder` contract. MOD-IAM records login success, login failure, current-session logout, and account-wide logout through that contract without importing MOD-AUDIT domain or infrastructure internals.

Audit rows are append-only. Database triggers reject updates and deletes in both PostgreSQL and the SQLite test environment. Actor identifiers are retained as immutable evidence rather than foreign keys that could rewrite or remove history during user deletion.

Authentication evidence contains event type, occurrence time, optional actor identifier, session identifier or revoked count where applicable, and SHA-256 fingerprints of normalized principal, source IP, and user agent. It never contains passwords, raw email addresses, bearer tokens, or reusable secrets.

Successful session issuance and revocation are committed atomically with their corresponding audit event. If evidence persistence fails, the session mutation fails with it.

## Traceability

- REQ-G2-AUDIT-SECURITY-001: security-sensitive identity outcomes must produce immutable, secret-safe evidence owned by MOD-AUDIT.
- TEST-G2-AUDIT-SECURITY-001: integration tests prove outcome recording, secret exclusion, actor/session correlation, and database-enforced append-only behavior.
- RISK-AUDIT-TAMPER-001: an attacker or defect could rewrite evidence. Control: database mutation triggers, restricted module API, and future separate database privileges and integrity chaining.
- RISK-AUDIT-SECRET-001: audit metadata could become a credential or personal-data leak. Control: allow-listed metadata, one-way fingerprints, tests excluding raw credentials and tokens, retention policy, and controlled access.

## Deferred scope

Tenant-bearing authorization events, privileged role/membership administration events, audit search/export permissions, retention, cryptographic integrity chains, separate database roles, and external evidence archival remain future slices.
