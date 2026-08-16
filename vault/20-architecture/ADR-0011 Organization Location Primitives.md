---
id: ADR-0011
type: decision
status: accepted
date: 2026-08-08
owners: [architecture, tenancy]
requirements: [REQ-G2-ORG-LOCATION-001]
modules: [MOD-TENANCY]
risks: [RISK-TENANT-LOCATION-LEAK-001, RISK-TENANT-HIERARCHY-001]
tests: [TEST-G2-ORG-LOCATION-001]
---

# ADR-0011 — Organization location primitives

## Decision

MOD-TENANCY owns the initial Company → Branch → Warehouse hierarchy. Every entity uses a UUID and carries an immutable owning `tenant_id`. Application callers do not supply a tenant identifier: creation and reads derive it from the established `TenantContext` and fail closed when no context exists.

Branch parents are resolved using both the current tenant and company UUID. Warehouse parents are resolved using both the current tenant and branch UUID. Composite database foreign keys repeat that invariant so a child cannot reference a parent owned by another tenant. PostgreSQL row-level-security policies apply the same fail-closed `app.tenant_id` boundary to all three tables.

Names and codes are operational identifiers, not authorization boundaries. Company names are unique within a tenant; branch codes are unique within a tenant/company; warehouse codes are unique within a tenant/branch.

## Traceability

- REQ-G2-ORG-LOCATION-001: a tenant can own companies, each company can own branches, and each branch can own warehouses without cross-tenant creation or read access.
- TEST-G2-ORG-LOCATION-001 proves missing context fails, cross-tenant company and branch references are rejected, and tenant-scoped reads do not disclose another tenant’s locations.
- RISK-TENANT-LOCATION-LEAK-001 is controlled by exact-tenant application predicates plus PostgreSQL RLS.
- RISK-TENANT-HIERARCHY-001 is controlled by tenant-qualified parent lookup and composite foreign keys.

## Deferred scope

Registers, devices, shifts, addresses, contact data, location lifecycle workflows, HTTP endpoints, permissions, and audit events remain separate slices.
