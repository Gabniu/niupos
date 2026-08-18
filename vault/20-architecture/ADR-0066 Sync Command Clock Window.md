---
id: ADR-0066
title: Sync Command Clock Window
status: accepted
date: 2026-08-18
requirements:
  - REQ-G6-SYNC-001
  - REQ-G6-MOB-001
tests:
  - TEST-G6-SYNC-001
  - TEST-G6-SYNC-HTTP-001
risks:
  - RISK-G6-SYNC-001
modules:
  - MOD-SYNC
---

# ADR-0066 — Sync Command Clock Window

## Decision

The server validates the client `occurredAt` timestamp before creating a sync
inbox row. Commands may be up to 30 days old, which supports prolonged offline
work, but may be no more than 15 minutes in the future. Both limits are
configuration values (`SYNC_COMMAND_MAX_AGE_SECONDS` and
`SYNC_COMMAND_MAX_FUTURE_SECONDS`) so deployments can adapt to their operating
policy without changing the protocol vocabulary.

An out-of-window command fails with HTTP 422 and the stable code
`SYNC_CLOCK_SKEW`. It is rejected before persistence, so retrying the same
command after the device clock is corrected remains safe. The server's clock is
authoritative; clients must not rewrite the command timestamp during retry.

## Traceability

- `REQ-G6-SYNC-001`: preserve idempotent command identity while bounding invalid
  chronology.
- `REQ-G6-MOB-001`: permit offline mobile work for a defined period without
  silently accepting corrupt device time.
- `TEST-G6-SYNC-001` and `TEST-G6-SYNC-HTTP-001`: cover old and future timestamps,
  stable error mapping, and the no-row persistence invariant.
- `RISK-G6-SYNC-001`: deployments must monitor rejected commands and set the
  window deliberately; a shorter age window can require an explicit recovery
  workflow for exceptionally long outages.

## Consequences

Clock errors are visible and actionable instead of becoming misleading sales
history. The policy does not prevent normal multi-week offline operation, and
the limit is adjustable per deployment. A future device-clock correction may
require the operator to fix time and retry the unchanged command.
