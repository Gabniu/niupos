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

The shared core supplies both an in-memory reference implementation and a
durable repository. `DurableSyncRepository` writes one partition envelope at a
time through the `SyncSecureStorage` port. The platform adapter must use an
OS-backed encrypted store (Keychain, Keystore, or an equivalent hardware-backed
provider); JSON is only serialization and is never treated as encryption.

Corrupt data fails closed with `corrupt_sync_state` and is not silently deleted.
Recovery is an explicit `resetPartition` operation for only the affected
tenant/device partition, followed by a fresh server bootstrap. A failed secure
storage write invalidates the in-memory mutation so the next operation reloads
the last durable state.

The native Keychain/Keystore adapter, crash/migration tests on real devices,
Flutter UI, and network transport remain follow-up work.

## Traceability

- `REQ-G6-MOB-001`: preserve offline commands and projections across a defined
  tenant/device boundary without silent loss.
- `TEST-G6-MOB-001`: repository tests cover isolation, atomic cursor application,
  durable round trips, explicit corrupt-state reset, failed-write rollback,
  replay fingerprint mismatch, and interrupted-send recovery.
- `RISK-G6-MOB-001`: the repository has no Dart/Flutter SDK in the current build
  environment, so mobile analysis and test execution remain CI/toolchain pending;
  the platform secure-storage adapter must be verified on real devices before
  production use.

## Consequences

The core is testable without Flutter plugins and keeps platform secrets outside
the Dart layer. It is not yet a production-complete mobile store until a native
adapter passes crash, migration, backup/restore, and device-lock tests.
