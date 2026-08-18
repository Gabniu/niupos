---
id: ADR-0064
title: Paged Sync Bootstrap Transfer
status: accepted
date: 2026-08-18
requirements: [REQ-G6-SYNC-001, REQ-G6-WEB-001]
tests: [TEST-G6-SYNC-HTTP-001, TEST-G6-WEB-001]
risks: [RISK-G6-SYNC-001, RISK-CURSOR-PARTIAL-APPLY-001]
modules: [MOD-SYNC, MOD-WEB-OFFLINE]
---

# Decision

The sync bootstrap endpoint keeps its existing complete snapshot for small
tenants and adds bounded collection pages for large transfers. A page selects
one catalogue or pricing collection and advances by the collection's ordered
UUID `after_id`. Each page includes `nextAfterId`, `hasMore`, and the tenant
change cursor used for the transfer.

Clients echo `snapshot_cursor` on every continuation request. If the server's
current cursor has changed, it returns a conflict and the client restarts the
bootstrap into a clean staging area. This prevents a large local catalogue from
combining rows read before and after a server mutation. Page size is bounded to
500, unknown query fields are rejected, and tenant/device admission remains the
same as the unpaged endpoint.

The web adapter validates page metadata before persistence and exposes the
optional request parameters without changing the frozen v1 envelope names.

## Traceability

- Requirements: REQ-G6-SYNC-001, REQ-G6-WEB-001
- Acceptance: unpaged clients remain compatible; page requests are bounded and
  tenant-scoped; cursor changes fail closed; page metadata is validated before
  local staging
- Tests: `apps/api/tests/Feature/Modules/Sync/SyncHttpTest.php`,
  `apps/web/src/offline/sync-http-adapter.test.ts`, and shared schema conformance
- Related: ADR-0031, ADR-0032, ADR-0034, Gate 6
