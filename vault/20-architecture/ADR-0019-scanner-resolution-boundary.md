---
id: ADR-0019
title: Scanner Resolution Boundary
status: accepted
date: 2026-08-08
requirements:
  - REQ-CAT-SCAN-001
  - REQ-CAT-SCAN-002
tests:
  - apps/api/tests/Feature/Modules/Catalogue/ScannerHttpTest.php
risks:
  - RISK-SCAN-TENANT-LEAKAGE
  - RISK-WEIGHTED-EAN-SEMANTICS
---

# ADR-0019: Scanner Resolution Boundary

## Context

The [NOVA MVP Execution Plan](../00-home/NOVA%20MVP%20Execution%20Plan.md) schedules scanner resolution before checkout so barcode input has a stable, tenant-safe contract independent of carts, prices, devices, and inventory mutations.

## Decision

Catalogue owns a `ScannerResolver` application contract and the versioned `POST /api/v1/catalogue/scan` endpoint. The endpoint authenticates the API session, establishes tenant membership, requires `catalogue.products.read`, validates the input, and then resolves an active tenant barcode to an active tenant product variant. All misses return the same `unknown` outcome and never reveal whether another tenant owns the identifier.

The supported input modes are `barcode`, `manual`, `keyboard_wedge`, and `camera`. They share deterministic normalization: trim the input and remove whitespace. Input modes record acquisition intent; they do not change lookup semantics.

EAN-13 values beginning with the reserved in-store range `20` through `29` are interpreted as two prefix digits, five item-reference digits, five weight digits in grams, and one check digit. Resolution uses only the five-digit item reference. The boundary returns parsed metadata but does not validate scale hardware, calculate a price, mutate a cart or inventory, or create an offline projection. Check-digit enforcement is deferred until jurisdiction/device profiles define it.

The endpoint is limited to 120 requests per minute per authenticated session and IP.

## Consequences

- Checkout can consume a small found/unknown contract without importing Catalogue persistence.
- Weighted input remains descriptive and side-effect free.
- A future device/jurisdiction profile may tighten check-digit and weight-unit rules without changing tenant isolation.

## Verification and traceability

- `REQ-CAT-SCAN-001`: normalized input modes and exact active-variant resolution; verified by `ScannerHttpTest::normalized_supported_inputs_return_found_or_generic_unknown_outcomes`.
- `REQ-CAT-SCAN-002`: weighted EAN metadata parsing; verified by `ScannerHttpTest::weighted_ean_exposes_parsed_weight_metadata_and_resolves_only_its_item_reference`.
- `RISK-SCAN-TENANT-LEAKAGE`: controlled by middleware ordering, explicit tenant predicates, and generic misses; verified by authentication/permission and cross-tenant tests.
- `RISK-WEIGHTED-EAN-SEMANTICS`: controlled by documenting the fixed initial gram-based policy and prohibiting price/cart/inventory side effects.
