---
id: ADR-0030
title: Receipt Retrieval and Delivery Evidence HTTP Boundary
status: accepted
---

# ADR-0030 Receipt Retrieval and Delivery Evidence HTTP Boundary

## Decision

MOD-RECEIPTS exposes authenticated, tenant-admitted endpoints beneath `/api/v1/receipts`. Retrieval requires `receipts.read` and returns an immutable header plus lines ordered by their snapshotted line number through the `ReceiptReader` application contract. Delivery evidence requires `receipts.delivery.record` and accepts only channel, outcome, attempted timestamp, and a bounded machine-safe error code. It never accepts destinations, rendered bodies, credentials, or provider secrets.

Both endpoints preserve the middleware order `api.session`, `tenant`, permission, throttle. Missing and cross-tenant identifiers have the same not-found response. This transport does not issue or recalculate receipts; cash completion remains the issuance owner.

## Traceability

- Requirement: REQ-G5-RECEIPT-HTTP-001
- Module: MOD-RECEIPTS
- Tests: TEST-G5-RECEIPT-HTTP-001
- Risks: RISK-RECEIPT-TENANT-LEAK-001, RISK-DELIVERY-SECRET-INGESTION-001
- Related: ADR-0027, Gate 5 Sales Checkout Kernel
