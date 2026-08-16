---
id: ADR-0049
title: Deferred Sync Change Publication Boundary
status: accepted
date: 2026-08-13
requirements:
  - REQ-G6-SYNC-BOOTSTRAP-001
  - REQ-G6-SYNC-COMMAND-001
tests:
  - apps/api/tests/Feature/Modules/Catalogue/CatalogueManagerTest.php
  - apps/api/tests/Feature/Modules/Pricing/PricingManagerTest.php
  - apps/api/tests/Feature/Modules/Sync/SyncProtocolTest.php
risks:
  - RISK-G6-SYNC-CYCLE-001
modules:
  - MOD-SYNC
  - MOD-CATALOGUE
  - MOD-PRICING
related:
  - "[[ADR-0031 Offline Sync Protocol Foundation]]"
  - "[[ADR-0034 Sync Catalogue and Pricing Bootstrap Snapshot]]"
  - "[[ADR-0048 Containerized Public API Deployment]]"
---

# ADR-0049 — Deferred Sync Change Publication Boundary

## Context

The sync command path owns `SalesSyncCommandHandler`, which owns the sales
checkout boundary. Checkout pricing is provided by the pricing manager, while
catalogue and pricing mutations publish their projections through
`SyncProtocol`. Resolving these services eagerly created a construction cycle:

`SyncProtocol → SyncCommandHandler → SalesCheckout → CheckoutQuoteProvider → DatabasePricingManager → SyncProtocol`.

Because Laravel caches scoped bindings only after construction completes, the
cycle caused a stack overflow in catalogue, pricing, inventory, and sync tests.

## Decision

Catalogue and pricing depend on the narrow `SyncChangePublisher` application
port. Its registered implementation, `DeferredSyncChangePublisher`, stores the
container and resolves `SyncProtocol` only when a mutation publishes a change.
The existing sync protocol, payloads, transaction boundaries, and change-feed
schema remain unchanged. Read and checkout paths never resolve the publisher.

## Safety and traceability

Mutation publication still occurs inside the existing database transaction, so
the sync row commits or rolls back with the catalogue/pricing mutation. The
deferred implementation is an internal composition concern; callers cannot
select a tenant or bypass `TenantContext`. Existing catalogue, pricing, and
sync tests remain the acceptance evidence, with the full containerized API
suite required before production deployment.

## Risks and follow-up

- `RISK-G6-SYNC-CYCLE-001`: a future producer must not inject `SyncProtocol`
  directly when it is reachable from `SalesCheckout`; use the narrow publisher
  port or an equivalent transactional outbox boundary.
- PostgreSQL migration and concurrent transaction verification remain required
  in the containerized suite because the local host has no PHP/Docker runtime.
