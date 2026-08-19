---
id: ADR-0076
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [catalogue, search]
requirements: [REQ-G7-SEARCH-004]
modules: [MOD-CATALOGUE, MOD-SEARCH, MOD-TENANCY]
tests: [TEST-G7-SEARCH-004]
risks: [RISK-G7-SEARCH-004]
---

# ADR-0076 Catalogue Search Projection Fallback

## Decision

The catalogue product lookup may use the optional Search projection only to order already-authoritative PostgreSQL results. The controller still queries active tenant-scoped products, variants, and units from PostgreSQL, applies the current search predicate, and caps the result at 100. Projection failures, empty projections, stale document IDs, and disabled Elasticsearch leave the database result unchanged.

This prevents eventual search state from introducing inactive, deleted, or cross-tenant rows into the catalogue response. Elasticsearch improves ordering/discovery when healthy; it never defines catalogue correctness.

## Traceability

- Requirement: `REQ-G7-SEARCH-004` — catalogue lookup can benefit from dedicated search while preserving authoritative PostgreSQL filtering and fallback.
- Acceptance test: `TEST-G7-SEARCH-004` — a projection ordering is applied only to current database rows.
- Risk: `RISK-G7-SEARCH-004` — projection lag can reduce ordering quality, but cannot expand the returned data set or bypass tenant/status filters.

## Consequences

- Existing clients retain the same response shape and empty/error behavior.
- Enabling Elasticsearch does not create a new correctness dependency for scanning or checkout.
- Projection ranking, relevance tuning, and complete-result pagination remain future search work.
