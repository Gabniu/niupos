---
id: ADR-0065
title: Durable Encrypted Mobile Sync State
status: accepted
date: 2026-08-18
requirements:
  - REQ-G6-MOB-001
tests:
  - TEST-G6-MOB-001
risks:
  - RISK-G6-MOB-001
modules:
  - MOD-MOBILE
---

# ADR-0065 — Durable Encrypted Mobile Sync State

## Decision

Mobile synchronization state is persisted per tenant/device partition through
the platform-neutral `SyncSecureStorage` port. The Dart repository writes an
opaque serialized envelope containing the server cursor, outbox commands, and
read projections. The platform implementation must provide encryption through
the operating system secure store (Keychain, Keystore, or an equivalent
hardware-backed service); the repository does not claim that JSON itself is
encrypted.

State corruption fails closed with `corrupt_sync_state` and is never silently
discarded. Recovery is explicit: clear only the affected partition with
`resetPartition`, then bootstrap from the server. If a secure write fails, the
repository forgets the uncommitted in-memory mutation so the next operation
reloads the last durable value.

## Traceability

- `REQ-G6-MOB-001`: preserve offline commands and projections across restarts
  without crossing tenant/device boundaries.
- `TEST-G6-MOB-001`: durable tests cover round-trip persistence, corruption and
  explicit reset, failed-write rollback, interrupted-send recovery, and replay
  fingerprint protection.
- `RISK-G6-MOB-001`: Dart/Flutter and native secure-storage tests are pending in
  this environment; production rollout requires real-device crash, migration,
  backup/restore, and device-lock evidence.

## Consequences

The sync core can be tested without Flutter plugins and does not receive raw
encryption keys. Each app supplies a small Keychain/Keystore adapter. A corrupt
partition can be repaired without deleting another tenant/device's local work,
but the user must be guided through a fresh bootstrap after that reset.
