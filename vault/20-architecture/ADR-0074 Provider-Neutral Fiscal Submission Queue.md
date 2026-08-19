---
id: ADR-0074
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [fiscal, receipts, sales]
requirements: [REQ-G7-FISCAL-QUEUE-001]
modules: [MOD-FISCAL, MOD-SALES, MOD-RECEIPTS, MOD-TENANCY]
tests: [TEST-G7-FISCAL-QUEUE-001]
risks: [RISK-G7-FISCAL-QUEUE-001]
---

# ADR-0074 Provider-Neutral Fiscal Submission Queue

## Context

KRA describes system-to-system eTIMS integration through an API using OSCU or VSCU, and says taxpayers or third-party integrators must complete the applicable onboarding and certification process. The queue therefore cannot claim that a local receipt is a KRA invoice or hard-code an unverified provider payload.

## Decision

Add a Fiscal module with a tenant-scoped, idempotent offline submission queue. A finalized sale can be represented by a provider-neutral `FiscalInvoice`, persisted as an append-only payload snapshot with a SHA-256 fingerprint. Repeating the same sale and payload returns the existing queued submission; a conflicting invoice for the same sale is rejected.

The queue is deliberately not an eTIMS client. Provider credentials, endpoint URLs, OSCU/VSCU payload mapping, certification evidence, retries, acknowledgements, and rejection semantics belong behind a future `FiscalGateway` adapter after the selected KRA profile is verified. The queue is a durable hand-off point, not fiscal acceptance evidence.

## Traceability

- Requirement: `REQ-G7-FISCAL-QUEUE-001` — preserve tenant-safe, replay-safe fiscal submission intent while offline or before a provider adapter is available.
- Acceptance test: `TEST-G7-FISCAL-QUEUE-001` — enqueue replay is idempotent, conflicting sale payloads fail, and another tenant cannot read the submission.
- Risk: `RISK-G7-FISCAL-QUEUE-001` — local queue state must never be presented as fiscal authority acceptance; provider certification and end-to-end sandbox evidence remain open.

## Consequences

- Sales and receipts remain authoritative and provider-neutral.
- Offline capture can survive network loss without duplicating a sale or invoice intent.
- A later OSCU/VSCU adapter can claim queued work only after verified KRA technical and certification requirements are recorded.

References: [KRA system-to-system integration](https://www.kra.go.ke/business/etims-electronic-tax-invoice-management-system/learn-about-etims/etims-system-to-system-integration); [KRA eTIMS taxpayer guidelines](https://www.kra.go.ke/images/publications/eTIMS-Taxpayer-Guidelines_2024.pdf).
