---
id: ADR-0063
title: Web Reconnect Coordinator
status: accepted
date: 2026-08-18
requirements: [REQ-G6-WEB-001]
tests: [TEST-G6-WEB-001]
risks: [RISK-OUTBOX-DUPLICATE-001, RISK-CURSOR-PARTIAL-APPLY-001]
modules: [MOD-WEB-OFFLINE, MOD-SYNC]
---

# Decision

MOD-WEB-OFFLINE exposes a transport-neutral `SyncCoordinator` for one
reconnect pass. It recovers interrupted `sending` entries, drains the server
change feed until `hasMore` is false, applies each page through the repository's
atomic cursor boundary, submits queued commands with their original command
ids, and performs one final pull so command-generated changes are visible.

The coordinator treats `retry_pending` as a safe deferred outcome: the command
is returned to `pending` and the pass stops draining the queue instead of
spinning on a busy server. Any transport interruption recovers all entries
claimed by the pass so a later pass can retry them. Pull loops have bounded page
and page-size limits and reject a server that claims more pages without cursor
progress. It owns orchestration only; authentication, HTTP classification,
storage durability, and domain conflict policy remain in their existing
boundaries.

## Traceability

- Requirement: REQ-G6-WEB-001
- Acceptance: reconnect is resumable after interruption; cursor and projection
  updates remain atomic; commands are submitted once per claimed attempt;
  retry-pending and duplicate command ids do not create a local retry loop
- Tests: `apps/web/src/offline/sync-coordinator.test.ts`, existing web offline
  repository and HTTP adapter tests, ESLint, and Next production build
- Related: ADR-0031, ADR-0032, ADR-0034, ADR-0049, Gate 6
