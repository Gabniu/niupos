---
id: ADR-0069
title: Tenant Reporting Timezone Boundary
status: accepted
date: 2026-08-18
requirements:
  - REQ-G7-REPORT-TZ-001
tests:
  - TEST-G7-REPORT-TZ-001
risks:
  - RISK-G7-REPORT-TZ-001
modules:
  - MOD-TENANCY
  - MOD-REPORTS
---

# ADR-0069 - Tenant Reporting Timezone Boundary

## Decision

Each tenant may configure an IANA reporting timezone through the existing
workspace preferences boundary. Reports use that timezone when interpreting
date-only bounds and when constructing default month/day periods, then convert
the bounds to UTC for database comparison. The response returns the timezone so
clients can explain the period without guessing.

The default remains UTC until an organization chooses another value. The
Reports module reads the setting through the Tenancy application contract and
does not query Tenancy domain models or mutate preferences.

## Traceability

- `REQ-G7-REPORT-TZ-001`: daily and monthly reports use the organization's
  configured business day rather than the API host timezone.
- `TEST-G7-REPORT-TZ-001`: date-only bounds for Africa/Nairobi are asserted at
  their exact UTC boundaries.
- `RISK-G7-REPORT-TZ-001`: changing a timezone changes future period grouping;
  persisted facts remain immutable and explicit timestamp bounds remain honored.

## Consequences

The web client can later load the preference and render period labels in the
same business timezone. Historical reports should retain explicit bounds when
reproducibility across a timezone change is required.
