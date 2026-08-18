# ADR-0072 Catalogue Search Projection Rebuild Source

Status: Accepted  
Date: 2026-08-18  
Owners: Search + Catalogue

## Decision

Search rebuilds now have a tenant-scoped catalogue source. The Search module reads a small, read-only snapshot of active products, variants, and barcodes through tenant-qualified database joins, then emits provider-neutral `SearchDocument` records through `CatalogueSearchRebuilder`.

The projection is a disposable read model. Catalogue tables remain authoritative, inactive rows are excluded, and no Search application class imports Catalogue domain classes or exposes provider payloads. A future event consumer or Elasticsearch adapter may replace the read path without changing the Search contract.

## Traceability

- Requirement: `REQ-G7-SEARCH-002` — a tenant can rebuild a catalogue search projection from authoritative active catalogue facts.
- Acceptance test: `TEST-G7-SEARCH-002` — active variant and barcode search is rebuilt for one tenant and is invisible to another tenant.
- Risk: `RISK-G7-SEARCH-002` — a rebuild can be stale between catalogue mutation and the next rebuild; the PostgreSQL catalogue endpoint remains the correctness fallback until event lag and alias-cutover evidence exist.

## Consequences

- Rebuilds are deterministic for the current tenant and do not require Elasticsearch.
- Search remains replaceable and non-authoritative.
- Elasticsearch indexing, lag metrics, alias cutover, and failure/recovery evidence remain open Gate 7 work.
