---
id: ADR-0078
type: architecture-decision
status: accepted
date: 2026-08-19
owners: [fiscal, reports]
requirements: [REQ-G7-FISCAL-OPS-001]
modules: [MOD-FISCAL, MOD-REPORTS, MOD-TENANCY]
tests: [TEST-G7-FISCAL-OPS-001]
risks: [RISK-G7-FISCAL-OPS-001]
---

# ADR-0078 Fiscal Submission Operational Status

## Decision

Reports exposes a tenant-scoped fiscal submission summary at `GET /api/v1/reports/fiscal-submissions`. It returns counts for queued, sending, submitted, rejected, and retry-pending work, plus the oldest pending timestamp and next retry time. It never returns invoice payloads, provider credentials, raw provider responses, or stored error text.

The endpoint is operational visibility only. A queued or submitted local record is not a statement of KRA acceptance; the provider adapter and certification evidence remain separate.

## Traceability

- Requirement: `REQ-G7-FISCAL-OPS-001` — operators can see tenant-scoped fiscal queue health without exposing secrets or raw provider data.
- Acceptance test: `TEST-G7-FISCAL-OPS-001` — a new tenant receives explicit zero counts and null pending timestamps.
- Risk: `RISK-G7-FISCAL-OPS-001` — aggregate status can lag a worker transaction; it is diagnostic evidence, not fiscal authority evidence.

## Consequences

- POS/admin surfaces can show actionable queue health before the verified KRA adapter is enabled.
- Detailed invoice inspection remains a separately authorized workflow.
