---
id: ADR-0004
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, security]
requirements: [REQ-G2-IAM-MEMBERSHIP-001]
modules: [MOD-IAM, MOD-TENANCY]
risks: [RISK-IAM-BOOTSTRAP-001]
tests: [TEST-G2-IAM-MEMBERSHIP-001, TEST-G2-IAM-PERMISSION-001, TEST-G1-TENANT-BOUNDARIES-001]
---

# ADR-0004 — IAM tenant membership authorization

## Decision

MOD-IAM owns the authenticated `User` and `TenantMembership` records. An HTTP tenant header identifies only a requested tenant scope. Before MOD-TENANCY establishes `TenantScope`, its application-level `TenantAccessAuthorizer` contract asks the MOD-IAM infrastructure adapter whether the current authenticated user has an active membership for that exact tenant identifier.

Authorization fails closed for unauthenticated actors, absent memberships, inactive memberships, malformed tenant identifiers, and mismatched users or tenants. Provider ordering replaces the tenancy module's deny-all adapter only when MOD-IAM is installed.

`tenant_memberships` is an authorization-bootstrap table: the exact membership lookup must happen before tenant database context exists, so this table is not protected by tenant RLS. It may be accessed only through MOD-IAM's authorization path, using an exact `(tenant_id, user_id)` predicate. General listing, cross-tenant browsing, and access by other modules are forbidden. Tenant-owned business tables remain subject to ADR-0003 RLS.

## Traceability

- REQ-G2-IAM-MEMBERSHIP-001: only an authenticated user with an active exact-tenant membership may enter a tenant scope.
- TEST-G2-IAM-MEMBERSHIP-001: Laravel integration tests prove active membership allows and absent or inactive membership denies.
- TEST-G1-TENANT-BOUNDARIES-001: architecture tests prevent MOD-IAM from importing MOD-TENANCY domain or infrastructure internals.
- RISK-IAM-BOOTSTRAP-001: a pre-scope authorization table could become a tenant-discovery channel. Controls are exact-pair lookup, fail-closed outcomes, module ownership, no general repository API, and security tests for indistinguishable denial.

## Deferred scope

Authentication mechanisms, session revocation, MFA policy, privileged role administration, device identity, and audit evidence remain Gate 2 work. Active membership proves tenant admission only; it grants no business permission.

## Role and permission extension

MOD-IAM evaluates business permissions only after `TenantScope` is active. Permission keys use stable lowercase dotted notation. The permission catalogue is global metadata; roles and role-permission assignments are tenant-owned, constrained to the same tenant, and protected by PostgreSQL RLS. Authorization joins the active membership, its exact tenant role, and the requested permission and otherwise returns false.

- REQ-G2-IAM-PERMISSION-001: an active tenant member may exercise only permissions explicitly assigned to that member's role in the active tenant.
- TEST-G2-IAM-PERMISSION-001: unit and database integration tests prove key validation, assigned/unassigned outcomes, missing-scope denial, and cross-tenant non-inheritance.
- RISK-IAM-ROLE-DRIFT-001: role configuration could grant unintended access. Controls are stable permission keys, tenant-consistent foreign keys, tenant RLS, default denial, explicit administration, and auditable changes.
