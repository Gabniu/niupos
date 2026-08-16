# ADR-0047 Tenant Sales Reporting Boundary

## Status

Accepted — 2026-08-12.

## Decision

Reporting is a read-only module. `GET /api/v1/reports/summary` reads finalized
sales and immutable sale lines under the current tenant context and requires
`reports.read`. It returns currency-separated totals and a bounded top-product
projection for the report period. It never mutates sales, pricing, inventory,
payments, or receipts and does not recompute historical amounts from current
catalogue or price records.

The web `/reports/` page renders only the persisted response, with loading,
permission/error, empty-period, and populated states. It preserves separate
currency totals rather than converting or inventing an exchange rate. The first
period controls are Today, the trailing seven days, and the current calendar
month; each sends explicit ISO `from` and `to` bounds to the API rather than
filtering an already-loaded client dataset.

## Traceability

- API: `apps/api/app/Modules/Reports/Application/Http/ReportsController.php`,
  `apps/api/app/Modules/Reports/Routes/api.php`, and
  `apps/api/app/Modules/Reports/ReportsServiceProvider.php`.
- Web: `apps/web/src/app/reports/page.tsx`, `apps/web/src/components/PosShell.tsx`,
  and dashboard navigation.
- Related: MVP Module Catalogue `MOD-REPORTS`; ADR-0023 immutable sales;
  ADR-0024 finalized sale snapshot; ADR-0045 shared shell.

## Risks and follow-up

- This first read model is a bounded operational summary, not a general ledger,
  tax filing, or financial reconciliation report.
- Report period parsing is intentionally bounded to one year; timezone and
  calendar reporting profiles require a separate product decision.
- The web presets use the browser's local calendar to form the request bounds;
  an organisation timezone/reporting-calendar setting is still open.
- Provisioning `reports.read` for organisation roles and PHP/PostgreSQL route
  tests remain part of the API integration gate.
