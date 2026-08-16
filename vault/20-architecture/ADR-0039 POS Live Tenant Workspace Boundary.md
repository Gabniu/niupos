---
id: ADR-0039
title: POS Live Tenant Workspace Boundary
status: accepted
date: 2026-08-11
requirements:
  - REQ-POS-WEB-STORE-001
tests:
  - TEST-POS-WEB-STORE-001
risks:
  - RISK-POS-WEB-STORE-001
---

# ADR-0039 POS Live Tenant Workspace Boundary

## Decision

The POS web client uses the existing API session login endpoint. A successful
login stores only the opaque bearer token and expiry in browser storage, then
loads admitted tenants from `GET /api/v1/auth/tenants`. Selecting one stores
the tenant identifier as local workspace context and sends it only as
`X-Tenant-Id` on tenant-scoped requests.

The dashboard is not seeded with sample metrics. `GET
/api/v1/dashboard/overview` is protected by `api.session` and `tenant`
middleware and reports live active company, branch, and warehouse counts from
the selected tenant. Empty activity is rendered as an explicit empty state.
Tenant membership and current database state remain authoritative; a browser
tenant identifier never grants access by itself.

Before a sale can begin, `GET /api/v1/workspace/locations` returns the active
branch hierarchy with active warehouses and registers. The web client requires
one of each, persists their identifiers as the local work context, and only
then opens the dashboard. Branches without a usable warehouse or register are
shown as a real configuration gap, never filled with example rows.

The web app proxies `/api/v1/*` to the configured API origin in development and
deployment, keeping the API origin out of page markup while permitting an
explicit `NEXT_PUBLIC_API_BASE_URL` when a direct origin is required.

## Acceptance and verification

- Login rejects invalid credentials generically and routes a valid session to
  store selection.
- Store selection renders loading, error, empty, and real admitted-tenant
  states; selecting a row persists the tenant context and opens the dashboard.
- Dashboard requests include the bearer token and selected tenant header and
  render only API data.
- Work-location selection renders real branch/warehouse/register data and does
  not enable continuation until both operational resources are selected.
- Web lint, typecheck, production build, web tests, repository architecture
  tests, and `git diff --check` pass.
- API controller coverage asserts counts cannot include another tenant.

## Risks

The local environment cannot currently run the PHP/Composer database suite.
The API controller test is committed for the provisioned CI/database runner;
production acceptance still requires authenticated HTTP tests against real
PostgreSQL and the shared Better Auth federation path.

Related rules: [[ADR-0036 NOVA Frontend Design Rules]] and [[Auth Pending Tasks]].
