# ADR-0046 Workspace Presentation and Checkout Density

## Status

Accepted — 2026-08-12.

## Decisions

- The default POS rail is compact (208px) and keeps header heights aligned. The
  Sales workspace uses a 56px icon rail so scanning and checkout get the width;
  icon-only links carry accessible labels.
- Sales keeps the summary at the viewport edge on desktop with a sticky,
  viewport-bounded panel. On narrow screens it returns to normal flow so touch
  controls and the mobile keyboard are not obstructed. Browser/device kiosk
  lockdown remains a deployment concern.
- Tenant workspace preferences are persisted in `tenant_workspace_preferences`
  and exposed through the tenant-scoped workspace preferences API. Operators
  with the organisation settings permission can hide the navigation rail or
  enable kiosk presentation. Kiosk mode forces the rail off in the web shell;
  it does not bypass authentication or claim to lock the browser.
- Inventory follows the supplied elegant inventory composition (search,
  compact summary, responsive cards, stock status) while using Hanken Grotesk,
  NOVA emerald/deep-green tokens, and only persisted tenant balances.

## Traceability

- UI: `apps/web/src/components/PosShell.tsx`, `apps/web/src/app/sale/page.tsx`,
  `apps/web/src/app/inventory/page.tsx`, `apps/web/src/app/settings/page.tsx`.
- API/schema: workspace preferences controller, route, and migration under
  `apps/api/app/Modules/Tenancy`.
- Related: ADR-0036, ADR-0045, ADR-0044.

## Verification

Web lint, TypeScript, production build, repository architecture tests, and
Graphify incremental refresh are required. PHP/PostgreSQL route and migration
coverage remains part of the CI-backed API verification profile.
