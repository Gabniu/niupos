---
id: ADR-0068
title: Mobile Sync Reconnect Coordinator Boundary
status: accepted
date: 2026-08-18
requirements:
  - REQ-G6-MOB-001
tests:
  - TEST-G6-MOB-002
risks:
  - RISK-G6-MOB-001
modules:
  - MOD-MOBILE
  - MOD-SYNC
---

# ADR-0068 - Mobile Sync Reconnect Coordinator Boundary

## Decision

The mobile client owns a transport-neutral `SyncTransport` port and a bounded
`SyncCoordinator`. The coordinator recovers interrupted sends, pulls gapped
change pages, applies each page through the durable repository, submits pending
commands with stable identifiers, maps `retry_pending` back to pending, and
performs a final pull after successful submissions.

HTTP, authentication, backoff, and platform networking remain outside the Dart
core. Native adapters can implement the port without changing repository or
domain code. A failed submission reopens the claimed command before the error
is returned, so a later reconnect can safely retry it.

## Traceability

- `REQ-G6-MOB-001`: mobile reconnect behavior is deterministic and partition
  scoped while preserving the frozen v1 envelope and receipt vocabulary.
- `TEST-G6-MOB-002`: coordinator tests cover pull/submit/final-pull ordering,
  retry-pending recovery, interrupted transport recovery, and stalled pages.
- `RISK-G6-MOB-001`: Dart/Flutter and native secure-storage execution remain
  external evidence; this core does not claim device-level encryption.

## Consequences

The Flutter shell can compose authentication and a native HTTP client around a
stable application boundary. Concrete network and Keychain/Keystore packages
remain replaceable and must be tested in the target platform before Gate 6
closes.
