---
id: ADR-0012
title: Tenant Catalogue and Barcode Identity
status: accepted
date: 2026-08-08
requirements:
  - REQ-G3-CAT-001
tests:
  - TEST-G3-CAT-001
risks:
  - RISK-CAT-TENANT-001
  - RISK-CAT-IDENTITY-001
---

# ADR-0012: Tenant Catalogue and Barcode Identity

## Context

Gate 3 needs stable product and barcode identities before pricing, inventory, sales, or scanner integrations can depend on them. Catalogue records must never resolve across tenant boundaries, and visually different whitespace or SKU casing must not create ambiguous identities.

## Decision

`REQ-G3-CAT-001` defines tenant-owned categories, units of measure, products, product variants, and barcodes. Products have a default variant at creation. SKU identity is trimmed, whitespace-free, and uppercase; barcode identity is trimmed and whitespace-free. Normalized SKU and barcode values are unique within a tenant. A barcode identifies one product variant.

All catalogue tables carry `tenant_id`, PostgreSQL RLS is enabled and forced, and composite foreign keys require referenced categories, units, products, variants, and barcodes to belong to the same tenant. Application operations require an active `TenantContext`. Inactive units and categories cannot be selected for new products, and inactive barcodes or variants do not resolve.

This foundation deliberately excludes pricing, inventory balances, scanner hardware, HTTP endpoints, and product search.

## Consequences

Pricing and inventory can reference stable variant IDs without redefining product identity. Tenant-local uniqueness allows different tenants to use the same supplier SKU or barcode independently. Changing normalization rules later requires a collision audit and controlled migration.

## Acceptance and verification

`TEST-G3-CAT-001` verifies normalized creation and resolution, unknown barcode behavior, tenant-exact resolution, duplicate normalized SKU and barcode rejection, application rejection of cross-tenant category/unit references, and database rejection of a cross-tenant barcode/variant relationship.

## Risks

- `RISK-CAT-TENANT-001`: a missing tenant predicate could expose another tenant's catalogue. Mitigated by scoped application queries, composite foreign keys, and forced PostgreSQL RLS.
- `RISK-CAT-IDENTITY-001`: inconsistent normalization could create duplicate or unresolvable identities. Mitigated by centralized normalization and tenant-local unique constraints; broader GS1 validation remains future work.

## Traceability

- Requirement `REQ-G3-CAT-001` is owned by the Catalogue module and implemented by `CatalogueManager`, `DatabaseCatalogueManager`, the five catalogue models, and the catalogue migration.
- Acceptance test `TEST-G3-CAT-001` is implemented by `CatalogueManagerTest`.
- Risks `RISK-CAT-TENANT-001` and `RISK-CAT-IDENTITY-001` are enforced by the same service tests and database constraints.
