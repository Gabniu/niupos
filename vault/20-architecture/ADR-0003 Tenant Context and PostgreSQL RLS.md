---
id: ADR-0003
type: decision
status: accepted
date: 2026-08-07
owners:
  - architecture
  - security
requirements:
  - REQ-G1-TENANT-ISOLATION-001
modules:
  - MOD-TENANCY
risks:
  - RISK-TENANT-CONTEXT-001
tests:
  - TEST-G1-TENANT-CONTEXT-001
  - TEST-G1-POSTGRES-RLS-001
---

# ADR-0003 — Tenant context and PostgreSQL RLS

## Decision

Every tenant-owned request, job, command, query, cache key, event, search document, export, and audit record must carry an explicit tenant identifier. A scoped `TenantContext` is immutable within one execution scope. Tenant-owned tables include `tenant_id` in keys and indexes where appropriate.

An HTTP tenant identifier is a requested scope, never proof of authorization. The tenancy middleware must call a `TenantAccessAuthorizer`; its default adapter denies every request until MOD-IAM supplies an authenticated membership decision.

PostgreSQL Row-Level Security is mandatory defense in depth for tenant-owned tables. Policies compare `tenant_id` with transaction-local `app.tenant_id`. Application authorization and query scoping remain mandatory; RLS is not the only control.

Database owners and roles with `BYPASSRLS` are forbidden for ordinary application traffic. Migrations and privileged maintenance use separate audited roles.

## Evidence

- TEST-G1-TENANT-CONTEXT-001 proves invalid IDs, missing context, and tenant switching fail closed.
- TEST-G1-POSTGRES-RLS-001 runs against real PostgreSQL, proves only the active tenant row is visible, proves a cross-tenant insert is rejected, and rolls the proof transaction back.

## Risk

RISK-TENANT-CONTEXT-001: a worker, scheduled task, or connection-pool reuse could retain or omit tenant context. Controls: scoped application lifetime, transaction-local PostgreSQL setting, explicit clearing, job middleware, fail-closed access, and cross-tenant tests for every adapter.
