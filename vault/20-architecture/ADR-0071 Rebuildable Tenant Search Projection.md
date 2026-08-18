---
id: ADR-0071
title: Rebuildable Tenant Search Projection
status: accepted
date: 2026-08-18
requirements:
  - REQ-G7-SEARCH-001
tests:
  - TEST-G7-SEARCH-001
risks:
  - RISK-G7-SEARCH-001
modules:
  - MOD-SEARCH
  - MOD-TENANCY
---

# ADR-0071 - Rebuildable Tenant Search Projection

## Decision

MOD-SEARCH owns a tenant-scoped projection document boundary. Documents carry
an entity type/id, searchable text, a safe display title, a JSON payload, and a
source version. The database implementation provides a bounded fallback search
and an atomic tenant rebuild; a future Elasticsearch adapter can implement the
same contract without changing catalogue or checkout correctness.

Search documents are never authoritative. Every operation derives the tenant
from `TenantContext`, uses forced PostgreSQL RLS, and limits result size. A
rebuild replaces only the current tenant's projection in one transaction; a
failed document leaves the previous projection intact.

## Traceability

- `REQ-G7-SEARCH-001`: search is tenant-safe, bounded, and fully rebuildable
  without coupling business truth to an eventual-consistency index.
- `TEST-G7-SEARCH-001`: projection tests cover tenant isolation, bounded search,
  rebuild replacement, and delete behavior.
- `RISK-G7-SEARCH-001`: stale or unavailable Elasticsearch cannot change sales;
  PostgreSQL catalogue search remains the explicit correctness fallback until
  index lag and alias-cutover evidence exists.

## Consequences

Workers may consume catalogue/sales events and feed this port later. Index
versioning, lag metrics, alias cutover, and real Elasticsearch resilience tests
remain required before dedicated search is production-default.
