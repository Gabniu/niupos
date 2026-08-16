# ADR-0044 POS Inventory Visibility Boundary

## Status

Accepted — 2026-08-11.

## Context

Operators need to inspect stock without confusing a missing balance with fabricated zero data. Inventory balances already belong to the Inventory module and are tenant/warehouse/variant scoped, with active reservations and sale-intent transitions handled by the module's application contracts.

## Decision

Expose `GET /api/v1/inventory/balances` behind the authenticated session, tenant context, and `inventory.stock.read` permission. The optional `warehouseId` query filter is resolved only within the current tenant; joins remain tenant-qualified and include only active warehouses, products, and variants. The response is capped at 500 rows and returns the persisted on-hand quantity.

The web `/inventory/` page reads this boundary using the selected workspace warehouse from local browser context. It renders loading, error, and explicit empty states; it never manufactures stock counts or reservations. A narrow viewport receives a horizontally scrollable data region rather than clipped columns. Search and compact 12-row pagination operate on the returned tenant-scoped balances without inventing rows or counts.

## Traceability

- Requirement: tenant-safe inventory visibility with no placeholder values.
- Implementation: `apps/api/app/Modules/Inventory/Application/Http/InventoryController.php`; `apps/api/app/Modules/Inventory/Routes/api.php`; `apps/api/app/Modules/Inventory/InventoryServiceProvider.php`; `apps/web/src/app/inventory/page.tsx`; `apps/web/src/app/dashboard/page.tsx`.
- Existing invariants: `ADR-0017 Inventory Ledger and Balance Boundary`; `ADR-0021 Inventory Sale Intent and Reservation Boundary`.
- Verification: web lint/typecheck/build, repository architecture suite, and PHP/PostgreSQL CI for database-backed route behavior.

## Risks and follow-up

- Permission provisioning must include `inventory.stock.read` for roles that should inspect balances.
- The first read model intentionally excludes valuation, reorder thresholds, and reservation detail; those require explicit product decisions and contracts.
- PostgreSQL query/index tuning should be revisited when a tenant's catalogue exceeds the bounded read size; client pagination is presentation-only and does not replace a future server-side cursor boundary.
