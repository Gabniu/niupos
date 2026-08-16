---
id: ADR-0008
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-OWNER-001]
modules: [MOD-IAM, MOD-TENANCY, MOD-AUDIT]
risks: [RISK-IAM-OWNER-LOCKOUT-001, RISK-IAM-BOOTSTRAP-REPLAY-001]
tests: [TEST-G2-IAM-OWNER-001]
---

# ADR-0008 — Initial tenant owner bootstrap

## Decision

Initial ownership is a one-time application service, not a normal tenant administration endpoint. It establishes `TenantScope`, locks the tenant, and proceeds only when that tenant has zero memberships. It creates the stable IAM management permissions, built-in owner role, active owner membership, and tenant audit evidence atomically.

After bootstrap, normal permission-gated administration applies. An active owner membership is explicitly marked. Reassigning or revoking the last active owner fails before mutation, preventing accidental tenant lockout.

## Traceability

- REQ-G2-IAM-OWNER-001: bootstrap exactly one initial owner and preserve at least one active owner thereafter.
- TEST-G2-IAM-OWNER-001 proves one-time bootstrap, management capability, evidence, replay denial, and last-owner protection.
- RISK-IAM-OWNER-LOCKOUT-001 is controlled by last-owner checks and future ownership-transfer workflow.
- RISK-IAM-BOOTSTRAP-REPLAY-001 is controlled by tenant locking, zero-membership precondition, atomic persistence, and no public HTTP route.

## Deferred scope

Emergency recovery, dual approval, and MFA elevation remain future work.

## Operator CLI extension

`php artisan nova:tenant:bootstrap-owner <tenant-uuid> <user-uuid> --operator=<principal-or-change> [--force]` is the sole bootstrap transport. It requires exact identifiers and an operator or approved-change reference. Interactive use requires confirmation; automation must state `--force` explicitly. Audit evidence stores only a SHA-256 hash of the operator reference. Console access remains governed by deployment RBAC and runbooks.

TEST-G2-IAM-OWNER-CLI-001 proves missing attribution is invalid, attributed invocation succeeds, its attribution hash is recorded, and replay fails.

## Ownership transfer extension

Normal transfer remains an application-only operation until MFA elevation exists. The actor must be an active owner with `iam.memberships.manage`; the target must be a different active tenant member. Both memberships are locked in deterministic order, the target is promoted before the source is demoted, and `identity.owner.transferred` evidence commits atomically.

TEST-G2-IAM-OWNER-TRANSFER-001 proves successful atomic transfer and preservation of exactly one active owner. Emergency recovery is deliberately separate from normal transfer.
