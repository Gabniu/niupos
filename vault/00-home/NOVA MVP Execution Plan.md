---
id: PLAN-NOVA-MVP-EXECUTION-0001
type: delivery-plan
status: active
date: 2026-08-08
owners: [delivery, architecture, product]
modules:
  [
    MOD-TENANCY,
    MOD-IAM,
    MOD-REGISTER,
    MOD-SHIFTS,
    MOD-CATALOGUE,
    MOD-PRICING,
    MOD-INVENTORY,
    MOD-SALES,
    MOD-PAYMENTS,
    MOD-RECEIPTS,
    MOD-SYNC,
    MOD-REPORTS,
    MOD-SEARCH,
    MOD-AUDIT,
    MOD-ONBOARDING,
    MOD-CHANNELS,
  ]
---

# NOVA MVP Execution Plan

## Objective

Deliver the Kenya grocery MVP through dependency-ordered vertical foundations, preserving tenant isolation, integer money arithmetic, immutable operational evidence, offline-safe identities, and bidirectional requirement/test/ADR traceability. A batch closes only when implementation, tests, PostgreSQL evidence, authored notes, Graphify, and generated Obsidian agree.

## Operating model

- Run at most three implementation lanes concurrently and retain one integration owner.
- Give each lane exclusive module and ADR ownership; only the integration owner edits shared providers, gate notes, master plans, PostgreSQL proof, Graphify, and generated Obsidian.
- Freeze application contracts before downstream modules consume them. Cross-module Domain or Infrastructure imports are forbidden.
- Merge only after focused tests, then run the full Laravel, architecture, web, infrastructure, RLS, and knowledge suites.
- Do not claim cluster, provider, fiscal, payment, or offline behavior until exercised in the applicable external environment.

## Completed foundation

1. Repository/runtime/Compose boundaries, tenant context, PostgreSQL RLS, and module fitness tests.
2. IAM membership/permissions, opaque sessions, audit evidence, owner safety, TOTP MFA, and session elevation.
3. Company/branch/warehouse hierarchy, register/device enrollment identity, and catalogue/barcode identity.
4. Price books, tax categories, effective product prices, deterministic tax rounding, CI/dev-container configuration, and Kubernetes/observability configuration baseline.

## Critical path and delivery batches

### Batch 3 — Operational transaction prerequisites (complete)

- MOD-INVENTORY: append-only stock movement ledger, balances, adjustments, idempotency, and tenant/location/catalogue boundaries.
- MOD-SHIFTS: register shift lifecycle, opening float, cash movements, counted close, and deterministic variance.
- MOD-CATALOGUE scanner boundary: normalized scan input, symbology/weighted-code policy, duplicate/unknown outcomes, and versioned HTTP contracts where safe.

Exit: inventory and shifts expose stable application contracts; scan resolution can identify an active variant without pricing or sale mutation; all tenant tables have real PostgreSQL RLS evidence.

### Batch 4 — Checkout kernel (complete)

- MOD-SALES cart and immutable line snapshots.
- Pricing/tax quote contract consumed without importing internals.
- Inventory reservation/finalization intent contract.
- Register/shift authorization and sale idempotency key.

Exit: one server-side cash checkout finalizes exactly once with immutable totals and inventory intent.

### Batch 5 — Tender and receipt completion (application and authenticated transport complete)

- MOD-PAYMENTS cash tender plus provider-neutral attempt/allocation lifecycle.
- Kenya M-Pesa adapter contract and reconciliation boundary; no provider secrets in domain records.
- MOD-RECEIPTS immutable receipt render model, numbering, print/delivery attempts, void/refund coordination.

Exit: cash and simulated asynchronous payment outcomes reconcile to immutable sales and receipts.

### Batch 6 — Offline and client vertical slice

- MOD-SYNC device cursor, change feed, outbox intake, deduplication, conflict records, and retry semantics.
- Installable Next.js POS shell using generated contracts and local projection.
- Flutter application bootstrap using the same contracts and synchronization protocol.

Exit: a device can download catalogue/pricing, create one offline checkout command, reconnect, and resolve duplicate delivery deterministically.

Status (2026-08-12): the shared v1 schema, tenant/device-scoped server change feed
and command inbox, authenticated HTTP transport, web IndexedDB repository and
network adapter, and Flutter-ready repository core are implemented. The server
now executes one `sales.finalize.v1` command and the web shell has a conservative
installable service-worker fallback. Batch 6 is not closed: catalogue/pricing
hard-delete semantics and large-tenant bootstrap transfer, persistent encrypted
mobile storage, corrupt-state recovery, clock-skew/prolonged-partition tests, and
the full reconnect/duplicate-delivery vertical acceptance test remain open. See
[[Gate 6 Offline Synchronization Foundation]], [[ADR-0031 Offline Synchronization Protocol Foundation]],
[[ADR-0032 Web Offline Repository and Sync Transport]], and
[[ADR-0033 Flutter Offline Repository Boundary]].

### Frontend design direction — authentication and shared entry screens

- Shared identity is implemented as the independently deployable Better Auth
  service in `apps/auth`; see [[ADR-0037 Shared Better Auth Identity Provider]].
  It owns authentication, identity sessions, organizations, and OAuth/OIDC client
  registration for NOVA and future applications. Each consumer remains
  authoritative for its own domain roles and permissions.

- Use the supplied Stitch login page as the visual reference for Login, Register,
  Forgot Password, OTP/MFA, reset-password, and access-request screens.
- Preserve its compact split composition: automatic image carousel on the left
  and focused authentication form on the right; adapt spacing and content for
  smaller screens rather than reproducing the reference at full size.
- The initial carousel assets are the supplied suit, grocery, jeans, and bakery
  images. Super Admin must be able to add, remove, reorder, activate, and preview
  carousel images without a deployment.
- Every frontend page built from a reference must be reviewed against the source
  before acceptance. Add missing NOVA-specific states, accessibility, tenant
  context, validation, loading, error, offline, and responsive behavior only when
  needed; document deliberate deviations in the page review note.
- Login is the first web page implementation. Its reusable auth layout becomes
  the shared shell for Register, Forgot Password, OTP/MFA, and reset-password.
- After authentication, the multi-tenant Store Selection page is the next
  shared entry screen. It must show only stores/branches admitted to the user,
  make online/offline terminal state clear, and provide a safe return to Login.
- All frontend work follows [[ADR-0036 NOVA Frontend Design Rules]]: Hanken
  Grotesk only, compact centered rails, reference comparison, explicit responsive
  behavior, and database-backed states without invented data.
- The Owner Dashboard follows the supplied dashboard reference as a compact,
  card-first shell; all metrics, activity, and store context must come from the
  authenticated dashboard endpoint and render explicit empty/loading/error states.

### Batch 7 — Reporting, search, and fiscal integration

- Operational PostgreSQL projections and authorization-safe reports.
- Rebuildable Elasticsearch catalogue/sales projections with lag/reconciliation evidence.
- Kenya fiscal/eTIMS adapter and offline invoice queue according to the approved compliance profile.

Exit: reports reconcile to authoritative facts, search rebuilds safely, and fiscal submission/retry evidence is retained.

Status (2026-08-12): the first read-only reporting slice is implemented. The
tenant-scoped `reports.summary` endpoint and responsive web report page read
finalized sales and immutable sale-line facts, preserve separate currencies,
render explicit empty/error states, and now support Today, trailing seven-day,
and current-month request bounds. Batch 7 remains open: organisation reporting
timezone profiles and reconciliation evidence, search projections/rebuilds, and
fiscal integration are not yet implemented.

### Batch 8 — Production readiness and pilot

- CI execution evidence, image digests, cluster rollout/rollback, secrets delivery, backup/restore, SLOs, alerts, incident and support runbooks.
- Load, soak, failure, accessibility, compatibility, security, recovery, and prolonged-offline exercises.
- Pilot migration, training, acceptance, telemetry, and reconciliation.

Exit: Gate 8 acceptance checklist and residual-risk review approved.

### Adaptive onboarding and commerce channels — active cross-gate workstream

The active product workstream is [[NIU Adaptive Onboarding and Channel Provisioning Plan]]
and its accepted decision record [[ADR-0062 Adaptive Onboarding Channel Provisioning and Customer Identity]].
It sits across Gate 2 identity/tenancy, Gate 3 catalogue, Gate 6 clients, and
Gate 8 operations rather than being a fourth client-specific wizard.

- Ask every new organization for `POS only`, `web ecommerce`, `mobile ecommerce`,
  or `web + mobile` before collecting channel-specific details.
- Persist a tenant-scoped, resumable draft and a server-authoritative versioned
  blueprint. The selected branch controls questions, capability entitlements,
  channel client metadata, and publication checklists.
- Deliver POS-only organization creation first, then web draft/publication,
  mobile build/preview, and finally the combined shared-catalogue/availability
  flow. Add grocery and bakery blueprints before supermarket/regulated profiles.
- Automate deterministic scaffolding only. Tax/legal, secrets, domains,
  payments, app signing/publishing, data merges, and high-risk permissions stay
  behind explicit approval and audit evidence.
- Add MOD-ONBOARDING, MOD-CHANNELS, setup timeline/notification preferences,
  dry-run provisioning, compensation, and branch/identity/customer tests before
  enabling production automation.

This workstream is planned, not counted as completed MVP functionality. The
requirements, tests, risks, and acceptance sequence are enumerated in the linked
plan so implementation can be scheduled without weakening existing module
ownership or tenant admission rules.

## Dependency rules

- Sales starts only after inventory, shift, catalogue, pricing, register, and idempotency contracts stabilize.
- Payments and receipts may design contracts during Sales work but cannot finalize persistence against a mutable sale model.
- Sync transports commands and changes; domain modules retain validation and conflict decisions.
- Reports and search consume committed facts/events and never mutate operational truth.
- External adapters remain behind application contracts and must not leak provider payloads or secrets into core aggregates.

## Current completion estimate

Approximately 52–60% of the full operational MVP is implemented. Batch 6 now has
a frozen shared sync contract, durable server protocol and authenticated transport,
an executable `sales.finalize.v1` adapter, plus aligned web and mobile repository foundations. Live M-Pesa callback
authentication, physical delivery adapters, sync domain adapters and projection
producers, persistent mobile storage, installable web offline behavior, advanced
reporting, search, fiscal integration, and production-readiness evidence remain open.
