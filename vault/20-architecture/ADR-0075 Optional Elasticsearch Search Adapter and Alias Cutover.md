---
id: ADR-0075
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [search, platform]
requirements: [REQ-G7-SEARCH-003]
modules: [MOD-SEARCH, MOD-TENANCY]
tests: [TEST-G7-SEARCH-003]
risks: [RISK-G7-SEARCH-003]
---

# ADR-0075 Optional Elasticsearch Search Adapter and Alias Cutover

## Decision

MOD-SEARCH now includes an optional Elasticsearch implementation of the existing `SearchProjection` contract. It is disabled by default (`SEARCH_DRIVER=database`). When enabled, every tenant receives a separate alias, writes stay tenant-specific, and a rebuild indexes into a fresh versioned index before atomically switching the alias. Failed rebuilds attempt to remove only the new index and leave the current alias untouched.

The database projection remains the default and correctness fallback. Elasticsearch is a disposable read model: it never participates in checkout, payment, inventory, fiscal, or receipt truth. Bulk item errors fail the rebuild instead of silently publishing a partial alias.

## Traceability

- Requirement: `REQ-G7-SEARCH-003` — dedicated search can be enabled without changing the provider-neutral projection contract or tenant isolation.
- Acceptance test: `TEST-G7-SEARCH-003` — tenant-specific URLs, bulk rebuild, and alias cutover are exercised through the adapter contract.
- Risk: `RISK-G7-SEARCH-003` — network outage, lag, stale aliases, and schema incompatibility can make dedicated search unavailable; callers must retain the PostgreSQL fallback until production resilience evidence exists.

## Consequences

- Development defaults remain dependency-free and safe when Elasticsearch is unavailable.
- Production must configure authentication, TLS, resource limits, index lifecycle, lag telemetry, and an operational rollback procedure before enabling the driver.
- Real Elasticsearch outage, lag, alias rollback, and resource-pressure tests remain open.
