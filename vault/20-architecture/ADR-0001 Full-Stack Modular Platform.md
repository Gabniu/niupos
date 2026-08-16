---
id: ADR-0001
type: decision
status: accepted
date: 2026-08-07
owners:
  - architecture
requirements:
  - REQ-KE-ETIMS-001
  - REQ-KE-OFFLINE-INVOICE-001
  - REQ-KE-MPESA-001
  - REQ-KE-PRIVACY-001
  - REQ-KE-GROCERY-001
modules:
  - MOD-PLATFORM
risks:
  - RISK-KE-LEGAL-001
  - RISK-KE-ETIMS-001
  - RISK-KE-PAYMENTS-001
supersedes: null
superseded_by: null
---

# ADR-0001 — Full-stack modular platform

## Context

NOVA must preserve the selected stack while delivering an MVP whose financial and inventory behavior remains understandable, testable, and recoverable. The stack contains multiple distributed components, but distributing domain ownership prematurely would multiply transaction and failure modes.

## Decision drivers

- Offline-first checkout and deterministic synchronization.
- Strong transactional boundaries for sales, payments, shifts, and inventory.
- Multi-tenant isolation.
- Independent web, mobile, worker, and integration evolution.
- Search and messaging capabilities without making them sources of financial truth.
- Kubernetes deployment from the beginning, with operational proof rather than assumed reliability.
- A path to later service extraction without requiring it for the MVP.

## Decision

Use a Laravel modular monolith as the authoritative domain system and transactional API. Deploy API and worker processes independently, but build them from the same versioned application and module packages.

Use PostgreSQL as the authoritative system of record. Redis, RabbitMQ, and Elasticsearch are mandatory platform components with deliberately non-authoritative roles.

Use Next.js for the web back office and installable cashier PWA. Use Flutter for owner/mobile operations and as the native-hardware path where browser capabilities are insufficient. Both consume versioned contracts generated from the same API and event schemas.

Deploy application workloads to Kubernetes in every shared environment. Local development uses containerized dependencies and may use a lightweight local Kubernetes profile for infrastructure validation.

Accepted on 2026-08-07 as the Gate 1 architecture baseline. Acceptance does not close Gate 1: framework versions, module fitness rules, tenancy proof, Kubernetes manifests, and failure-isolation exercises remain required evidence.

## Component responsibility rules

### Laravel

- Owns domain commands, invariants, authorization decisions, transactions, APIs, event production, projections, scheduled work, and integration adapters.
- Modules expose application contracts; other modules cannot reach into internal models or tables directly.
- Domain code does not depend on HTTP controllers, queue transports, search clients, or framework facades without adapters.

### PostgreSQL

- Authoritative store for tenants, identity references, catalogue, pricing, taxes, inventory movements, sales, payments, shifts, audit records, synchronization state, outbox, inbox, and reconciliation.
- Database constraints enforce invariants that must survive application bugs.
- Tenant IDs participate in keys and indexes where necessary; Row-Level Security is defense in depth, not the sole isolation mechanism.
- Partitioning is introduced only after measured table size and access patterns justify it.

### Redis

- Cache, rate limiting, ephemeral locks, session/token coordination where selected, and short-lived realtime state.
- Never the sole store for completed sales, payments, stock movements, audit evidence, or synchronization progress.
- Every cache has ownership, key version, TTL, invalidation mechanism, maximum staleness, and cold-start behavior.

### RabbitMQ

- Carries integration events and asynchronous work after authoritative database commit.
- Events originate through a transactional outbox.
- Publishers use confirms; consumers use manual acknowledgements, stable message IDs, inbox/deduplication, bounded retries, and dead-letter handling.
- Delivery is treated as at least once. All handlers must be idempotent.

### Elasticsearch

- Provides product discovery, advanced filtering, audit discovery, and analytics/search projections where near-real-time consistency is acceptable.
- Receives versioned projections through RabbitMQ consumers.
- Never determines sale totals, permissions, stock truth, payment status, or financial reports.
- Index aliases support rebuild and atomic cutover. Index lag is observable and replayable from authoritative data.

### Next.js

- Provides cashier PWA and back-office web experiences.
- Checkout-critical screens are client-capable and operate against a local repository during network loss.
- Server Components may support connected back-office pages, but offline cashier flows must not depend on server rendering.
- Browser scanner and printing capabilities live behind adapters.

### Flutter

- Provides mobile owner/manager experiences and later native cashier/hardware capabilities.
- Uses repository-based offline-first architecture and the same synchronization contracts as the PWA.
- Platform channels isolate device-specific camera, Bluetooth, serial, printing, and secure-storage behavior.

### Kubernetes

- Runs stateless Laravel API, workers, scheduler, Next.js, ingress, and supporting operational workloads.
- Stateful platform components may be operator-managed or externally managed; the selected mode requires explicit backup, restore, upgrade, and failure ownership.
- Readiness, liveness, startup probes, disruption budgets, topology spread, resource requests/limits, autoscaling, and graceful termination are tested.

## Module structure

Each Laravel module contains domain, application, infrastructure, interface, contracts, database migrations, factories, policies, tests, and documentation. Cross-module calls use application services or published events. A dependency map and architecture tests enforce allowed directions.

## Data and event consistency

The database transaction writes domain state and an outbox record atomically. A relay publishes the outbox event to RabbitMQ and records confirmation. Consumers apply idempotent projections and acknowledge only after their durable work commits. Search and notifications may lag without invalidating the sale.

## Consequences

### Positive

- Stronger correctness and simpler transactional reasoning.
- Full selected stack remains present with explicit value.
- Search and messaging failures are isolated.
- Independent scaling of API, workers, search consumers, and web application.
- Clear extraction seams if a module later needs a separate service.

### Costs

- Kubernetes and distributed-component operations are required earlier.
- Contract, outbox, deduplication, replay, and projection tooling must be built before feature breadth.
- Both web and Flutter clients require synchronization conformance tests.
- The team must actively prevent module-boundary erosion.

## Rejected options

- Independent microservice per module: too many distributed transactions and deployment surfaces before domain boundaries are proven.
- PostgreSQL-only platform: rejected because the selected target stack explicitly includes dedicated cache, messaging, and search capabilities.
- Redis or Elasticsearch as checkout truth: rejected because eviction and eventual consistency cannot define financial outcomes.
- Separate sync protocols per client: rejected because conflict behavior would diverge.

## Validation and tests

- Architecture dependency tests for every module.
- Transactional outbox crash-window and replay tests.
- RabbitMQ duplicate, reorder, nack, reconnect, and dead-letter tests.
- Redis flush and outage tests during critical workflows.
- Elasticsearch outage, lag, rebuild, and alias-cutover tests.
- Cross-tenant API, worker, export, cache-key, and search-filter tests.
- Kubernetes rollout, eviction, node-loss, secret-rotation, and restore exercises.
- Web/Flutter synchronization protocol conformance suite.

## Reversal or migration plan

If a module requires independent scaling, release cadence, regulatory isolation, or failure containment, extract it behind its existing application and event contracts. PostgreSQL ownership is separated only after dual-read validation, backfill reconciliation, cutover rehearsal, and rollback proof.
