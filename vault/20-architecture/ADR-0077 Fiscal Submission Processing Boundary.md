---
id: ADR-0077
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [fiscal, platform]
requirements: [REQ-G7-FISCAL-PROCESS-001]
modules: [MOD-FISCAL, MOD-TENANCY]
tests: [TEST-G7-FISCAL-PROCESS-001]
risks: [RISK-G7-FISCAL-PROCESS-001]
---

# ADR-0077 Fiscal Submission Processing Boundary

## Decision

Fiscal submissions can be claimed by a bounded, tenant-scoped processor and passed to a provider-neutral `FiscalGateway`. Claims move queued work to `sending` under a guarded update; provider results move it to `submitted`, `rejected`, or `retry_pending`. Transport failures are converted to a generic retryable result and use bounded exponential backoff. Provider references and result codes are stored, but raw provider payloads and secrets are not.

No gateway is selected or enabled by this decision. A real OSCU/VSCU adapter must be injected only after the KRA profile, credentials, certification, sandbox, and production evidence are approved. Until then, processing remains an explicit composition boundary rather than a fake successful integration.

## Traceability

- Requirement: `REQ-G7-FISCAL-PROCESS-001` — fiscal queue work is claimed once per attempt and can retry without duplicating the sale or invoice intent.
- Acceptance test: `TEST-G7-FISCAL-PROCESS-001` — a due submission is claimed and a provider result is recorded.
- Risk: `RISK-G7-FISCAL-PROCESS-001` — worker crashes and external retries can reorder attempts; guarded status transitions, idempotency, and provider references preserve one local submission record.

## Consequences

- Queue processing is ready for a verified adapter without coupling the core ledger to KRA payloads.
- A rejected submission remains visible for operator handling; a retryable failure is scheduled with a bounded delay.
- End-to-end sandbox/certification, dead-letter handling, and operator remediation remain open.
