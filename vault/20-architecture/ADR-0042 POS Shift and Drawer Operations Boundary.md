---
id: ADR-0042
title: POS Shift and Drawer Operations Boundary
status: accepted
date: 2026-08-11
requirements:
  - REQ-POS-SHIFT-WEB-001
tests:
  - TEST-POS-SHIFT-WEB-001
risks:
  - RISK-POS-SHIFT-WEB-001
---

# ADR-0042 POS Shift and Drawer Operations Boundary

## Decision

Register shift opening, pay-in/pay-out movements, current drawer state, and
shift closing are exposed through the Shifts module's authenticated HTTP
boundary. The UI sends bounded idempotency keys for opening and movement
commands and renders the server's expected cash, status, currency, and closing
variance. It never derives drawer balances from browser state.

Sales remains subject to the open-shift eligibility boundary. Closing a shift
is explicit and records the counted cash server-side; a closed shift cannot be
used for checkout or further cash movement.

## Acceptance

- The shift page has real loading, no-register, no-open-shift, open, error, and
  closed states.
- Opening, cash movement, and closing use the existing `ShiftCashManager`
  contract and tenant context.
- Dashboard links to shift operations and the sales workspace does not claim
  payment success when shift eligibility fails.
- Web lint, typecheck, build, tests, repository architecture tests, and
  `git diff --check` pass. PostgreSQL-backed controller evidence remains part
  of the provisioned API test run.

Related decision: [[ADR-0041 POS Checkout Completion and Receipt Boundary]].
