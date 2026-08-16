---
id: ADR-0033
title: Flutter Offline Repository Boundary
status: accepted
date: 2026-08-08
requirements:
  - REQ-G6-MOB-001
tests:
  - TEST-G6-MOB-001
risks:
  - RISK-G6-MOB-001
modules:
  - MOD-MOBILE
---

# ADR-0033 — Flutter Offline Repository Boundary

## Decision

The mobile client uses a versioned, transport-neutral local synchronization
repository partitioned by tenant and device. It stores a monotonic server cursor,
durable commands, and read projections. Command states are `pending`, `sending`,
`applied`, `rejected`, `conflict`, or `retry_pending`; an interrupted `sending` command returns to
`pending` without changing its command identifier.

Projection batches require strictly increasing opaque cursors and apply atomically;
gaps are valid because the global server sequence may contain other tenants' rows.
Unsupported protocol versions, cursor rollback, illegal state transitions, and replayed command
identifiers with different canonical payloads fail closed.

This slice supplies an in-memory reference implementation and conformance tests.
A persistent SQLite/Drift adapter, encryption-at-rest policy, Flutter UI, and
network transport remain follow-up work after the server v1 schema is frozen.

## Traceability

- `REQ-G6-MOB-001`: preserve offline commands and projections across a defined
  tenant/device boundary without silent loss.
- `TEST-G6-MOB-001`: repository tests cover isolation, atomic cursor application,
  replay fingerprint mismatch, and interrupted-send recovery.
- `RISK-G6-MOB-001`: the repository has no Dart/Flutter SDK in the current build
  environment, so mobile analysis and test execution remain CI/toolchain pending.

## Consequences

The core is testable without Flutter plugins and can later back onto a transactional
database. It is not yet a production-durable store and must not be represented as
one until a persistent adapter passes crash and migration tests.
