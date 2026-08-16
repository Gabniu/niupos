# ADR-0043 POS Catalogue Workspace Boundary

## Status

Accepted — 2026-08-11.

## Context

The POS dashboard needs a compact catalogue surface that is useful before a sale is started. It must show the authenticated tenant's active products and variants without introducing client-side fixtures or a second source of truth. The existing scanner boundary already resolves active variants; the workspace needs a bounded listing and search capability for product discovery.

## Decision

Add a tenant-scoped `GET /api/v1/catalogue/products` boundary. The API joins active products, active variants, and the optional unit of measure using tenant-qualified joins, supports an optional search term over product name, variant name, and SKU, and caps results at 100 rows. The web `/products/` page consumes this endpoint through the authenticated API helper and renders loading, error, empty, and populated states. It does not seed or fabricate catalogue data.

The dashboard's Products navigation links to this page. The client keeps the
server-backed result set real while presenting it in compact pages of 20 rows;
changing pages never fabricates or reorders catalogue records. Inventory,
reports, and settings remain separate workspace surfaces with their own
contracts; catalogue discovery does not infer metrics from them.

## Traceability

- Requirement: tenant-safe product discovery with no placeholder data.
- Implementation: `apps/api/app/Modules/Catalogue/Application/Http/ProductController.php`; `apps/api/app/Modules/Catalogue/Routes/scan.php`; `apps/web/src/app/products/page.tsx`; `apps/web/src/app/dashboard/page.tsx`.
- Existing boundary: `ADR-0012 Tenant Catalogue and Barcode Identity`.
- Verification: web lint, TypeScript, production build, and repository architecture suite; database-backed API verification remains part of the PHP/PostgreSQL CI profile. Product search and pagination use only the returned tenant-scoped API rows.

## Risks and follow-up

- Search currently uses the database's `LIKE` semantics; production PostgreSQL should add the appropriate indexes when catalogue volume requires it.
- The endpoint requires `catalogue.products.read`; role/permission provisioning must include that key for operators who should browse the catalogue.
- Inventory availability, price, and stock movements must be added through their own contracts rather than inferred in this read model.
