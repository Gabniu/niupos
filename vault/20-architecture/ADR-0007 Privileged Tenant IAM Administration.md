---
id: ADR-0007
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-ADMIN-001, REQ-G2-AUDIT-TENANT-001]
modules: [MOD-IAM, MOD-AUDIT, MOD-TENANCY]
risks: [RISK-IAM-PRIVILEGE-001, RISK-AUDIT-TENANT-LEAK-001]
tests: [TEST-G2-IAM-ADMIN-001, TEST-G2-IAM-ADMIN-HTTP-001, TEST-G2-TENANT-AUDIT-RLS-001, TEST-G1-TENANT-BOUNDARIES-001]
---

# ADR-0007 — Privileged tenant IAM administration

## Decision

Role creation, role-permission replacement, and membership role/status assignment run only inside an immutable `TenantScope`. The authenticated actor must hold `iam.roles.manage` or `iam.memberships.manage` in that same tenant. Absence of scope or permission fails closed before mutation.

Every target role is selected with the active tenant identifier. Composite tenant/role foreign keys prevent cross-tenant assignments at the database. Permission assignments accept only stable keys already present in the global permission catalogue.

Successful administration mutations and their evidence commit atomically. Evidence contains the active tenant, actor, affected identifiers, status, counts, and a deterministic permission-set hash. Permission lists are not duplicated into evidence.

Tenant administration evidence is stored separately from global authentication evidence. `tenant_audit_events` is append-only and protected by PostgreSQL RLS using transaction-local `app.tenant_id`.

## Traceability

- REQ-G2-IAM-ADMIN-001: only an explicitly authorized active-tenant actor may manage roles, permission sets, and memberships.
- REQ-G2-AUDIT-TENANT-001: privileged tenant changes must create append-only evidence visible only in the active tenant.
- TEST-G2-IAM-ADMIN-001 proves authorized changes and evidence, plus unauthorized no-mutation/no-evidence behavior.
- TEST-G2-TENANT-AUDIT-RLS-001 extends the real PostgreSQL proof to tenant audit evidence.
- RISK-IAM-PRIVILEGE-001: excessive or accidental grants can compromise a tenant. Controls are explicit management permissions, exact-tenant constraints, catalogue validation, atomic audit evidence, and future approval/MFA policy.
- RISK-AUDIT-TENANT-LEAK-001: audit evidence could reveal another tenant's administration. Controls are a separate tenant table, explicit tenant keys, PostgreSQL RLS, and cross-tenant proof.

## Deferred scope

Initial tenant-owner bootstrap, last-owner protection, approval workflows, MFA elevation, role deletion, permission-catalogue deployment, and audit export remain future slices.

## HTTP transport extension

Versioned `/api/v1/iam` endpoints expose role creation, complete permission-set replacement, and membership assignment. Their middleware order is fixed: `api.session` establishes the actor, `tenant` admits the requested tenant and opens `TenantScope`, and the administration service checks the operation-specific permission before mutation.

- TEST-G2-IAM-ADMIN-HTTP-001 proves the complete authenticated, tenant-admitted, permission-gated, audited workflow and verifies unauthenticated or unprivileged requests fail without mutation or success evidence.
