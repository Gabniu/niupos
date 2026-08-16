---
id: PLAN-MVP-0001
type: master-plan
status: active
version: 0.1.0
owners:
  - product
  - architecture
requirements:
  - REQ-KE-ETIMS-001
  - REQ-KE-OFFLINE-INVOICE-001
  - REQ-KE-MPESA-001
  - REQ-KE-PRIVACY-001
  - REQ-KE-GROCERY-001
  - REQ-G1-REPO-001
  - REQ-G1-LOCAL-INFRA-001
  - REQ-NIU-ONBOARD-001
  - REQ-NIU-CHANNEL-001
  - REQ-NIU-CUSTOMER-ID-001
  - REQ-NIU-AUTOMATION-001
  - REQ-NIU-CUSTOMIZATION-001
adrs:
  - ADR-0001
  - ADR-0062
risks: []
tests:
  - TEST-STRATEGY-0001
  - TEST-NIU-ONBOARD-001
  - TEST-NIU-CHANNEL-001
  - TEST-NIU-CUSTOMER-ID-001
  - TEST-NIU-AUTOMATION-001
  - TEST-NIU-CUSTOMIZATION-001
---

# NOVA MVP Master Plan

## Planning objective

Produce an implementation-ready, test-first plan for an offline-first, multi-tenant retail POS using the complete target stack: Laravel, PostgreSQL, Redis, RabbitMQ, Elasticsearch, Kubernetes, Flutter, and Next.js.

No listed technology is removed. Each component must have a bounded responsibility, an activation milestone, failure behavior, operational owner, and tests proving that its presence improves rather than weakens the system.

## Definition of planning complete

Planning is complete only when all of the following exist and agree:

- Product boundaries, target users, workflows, terminology, and measurable outcomes.
- Jurisdiction-neutral core rules plus a selected launch-country compliance profile.
- Domain modules, ownership boundaries, invariants, state machines, and permissions.
- Logical and physical data models, retention, tenancy, auditing, and migrations.
- REST APIs, asynchronous events, error contracts, idempotency, and versioning.
- Offline data scope, local storage, synchronization protocol, conflict matrix, and recovery UX.
- Web, mobile, scanner, printing, payment, and accessibility behavior.
- Security threat model, privacy model, secrets, encryption, abuse controls, and compliance mapping.
- Deployment topology, Kubernetes resources, environments, CI/CD, observability, backup, restore, disaster recovery, and incident response.
- Test cases and quality gates attached to every requirement and delivery milestone.
- Rollout, migration, support, training, telemetry, and post-launch validation plans.
- A reviewed risk register with owners, triggers, mitigations, contingency plans, and residual risk.
- Bidirectional traceability from vision through production evidence.

## Source-of-truth hierarchy

1. Ratified ADRs and approved requirements.
2. Domain rules, contracts, and data definitions.
3. Test specifications and executable tests.
4. Implementation and deployment configuration.
5. Generated Graphify analysis.

Generated graph artifacts never override human-approved requirements or ADRs. They identify relationships, omissions, and drift for review.

## Delivery model

NOVA is planned as vertical slices. A slice is complete only when UI, API, domain rules, persistence, events, offline behavior, security, observability, deployment, documentation, and tests are complete together.

### Gate 0 — Product and compliance foundation

- Launch market and retail segment selected: [[Kenya Grocery Launch Profile]].
- Tax, fiscal receipt, payment, privacy, retention, and accessibility obligations recorded.
- MVP boundaries and explicit non-goals approved.
- Core vocabulary and personas approved.
- Success metrics have baselines and measurement plans.

### Gate 1 — Architecture runway

- Repository boundaries, coding baseline, and local Compose model established; CI and dependency policies remain open. See [[Gate 1 Architecture Runway]].
- Laravel modular boundaries and architectural fitness tests established.
- PostgreSQL tenancy and migration strategy proven.
- Redis, RabbitMQ, and Elasticsearch failure-isolation patterns proven.
- Kubernetes development and non-production deployment proven.
- Next.js and Flutter shared contract generation proven.
- Observability, secrets, feature flags, and test-data mechanisms proven.

### Gate 2 — Identity, organization, and register foundation

- Active exact-tenant membership admission is implemented as the first fail-closed IAM slice. See [[Gate 2 Identity Foundation]] and [[ADR-0004 IAM Tenant Membership Authorization]].
- Opaque expiring API sessions and immediate revocation are implemented as transport-neutral IAM services. See [[ADR-0005 Opaque API Sessions and Revocation]].
- Append-only, secret-safe authentication evidence is implemented through MOD-AUDIT. See [[ADR-0006 Append-Only Security Audit Evidence]].
- Permission-gated tenant role and membership administration with RLS-isolated evidence is implemented as an application service. See [[ADR-0007 Privileged Tenant IAM Administration]].
- Versioned tenant IAM administration endpoints enforce authentication, tenant admission, permissions, and atomic evidence in that order.
- One-time initial-owner bootstrap and last-active-owner protection prevent unowned or administratively locked tenants. See [[ADR-0008 Initial Tenant Owner Bootstrap]].
- Initial ownership is invoked only through the attributed, confirmation-gated operator Artisan command.
- Normal ownership transfer is atomic and audited; its HTTP transport now requires exact-tenant admission and a five-minute session-scoped MFA elevation.
- Encrypted confirmation-gated TOTP is implemented as the MFA foundation. See [[ADR-0009 TOTP MFA Foundation]].
- Authenticated TOTP enrollment, accepted-step replay prevention, session elevation, and MFA-gated ownership transfer are implemented. See [[ADR-0010 Session-Scoped MFA Elevation]].
- Tenant-owned company, branch, and warehouse hierarchy primitives are implemented with composite tenant constraints and forced RLS. See [[ADR-0011 Organization Location Primitives]].
- Tenant-scoped register and one-time device enrollment identities are implemented with digest-only tokens and forced RLS. See [[ADR-0014 Register and Device Enrollment Foundation]].
- Register shifts now implement one-open-per-register lifecycle, opening float, append-only cash movements, counted close, and deterministic integer variance. See [[ADR-0018 Register Shift and Cash Control Foundation]].
- Tenant, company, branch, warehouse, register, device, user, role, permission, and shift primitives implemented.
- Authentication, authorization, session revocation, device registration, and audit evidence tested.
- Cross-tenant penetration tests pass.

### Gate 3 — Catalogue, pricing, tax, and scanning

- The first catalogue slice implements tenant-owned categories, units, products, default variants, normalized SKUs, and barcode resolution. See [[Gate 3 Catalogue Foundation]] and [[ADR-0012 Tenant Catalogue and Barcode Identity]].
- The first pricing slice implements tax categories, price books, effective product prices, overlap rejection, and integer half-up inclusive/exclusive tax calculation. See [[ADR-0015 Pricing Tax and Rounding Foundation]].
- The scanner boundary implements normalized barcode, manual, keyboard-wedge, and camera input, generic tenant-safe outcomes, weighted-EAN metadata, permission enforcement, and throttling. See [[ADR-0019-scanner-resolution-boundary]].
- Products, variants, units, categories, barcodes, price books, taxes, and local catalogue projection implemented.
- Keyboard-wedge, camera, manual, and adapter contracts implemented.
- Search, scan, unknown-code, weighted-item, duplicate-scan, and degraded-mode tests pass.

### Gate 4 — Inventory foundation

- The first inventory slice implements an append-only receipt/adjustment ledger, locked balance projection, tenant-scoped idempotency, default-deny negative stock, and forced RLS. See [[Gate 4 Inventory and Shift Foundation]] and [[ADR-0017 Inventory Ledger and Balance Foundation]].
- Locations, reservations, transfers, counts, receiving workflows, and sale-driven movements remain to be implemented.
- Ledger invariants, concurrency, negative-stock policy, offline oversell, and reconciliation tests pass.

### Gate 5 — Checkout and payments

- The checkout kernel finalizes immutable, tenant-scoped sales with complete pricing/tax snapshots, shift locking, reservation-backed exact-once inventory effects, command fingerprints, audit evidence, and forced RLS. See [[Gate 5 Sales Checkout Kernel]] and [[ADR-0023 Immutable Idempotent Sales Checkout Kernel]].
- The tender/receipt foundation adds exact-full cash and M-Pesa attempt lifecycles, immutable allocations, settlement-gated register receipt numbering/render snapshots, delivery-attempt evidence, and atomic cash drawer completion. See [[ADR-0025 Provider-Neutral Immutable Payment Attempts and Allocations]], [[ADR-0026 Immutable Receipt Snapshots and Register Numbering]], and [[ADR-0027 Atomic Cash Tender and Receipt Completion]].
- Versioned authenticated transports now expose sale finalization, atomic cash completion, payment initiation, privileged terminal-result operations, immutable receipt reads, and closed-schema delivery evidence. See [[ADR-0028 Sales Checkout HTTP Transport]], [[ADR-0029 Authenticated Payment Operations Transport]], and [[ADR-0030 Receipt Retrieval and Delivery Evidence HTTP Boundary]].
- Cart, totals, discounts, sale finalization, payments, receipts, voids, refunds, and shift settlement implemented.
- Money, tax, rounding, idempotency, partial failure, and immutable-record tests pass.

### Gate 6 — Offline and synchronization

- Installable web POS and Flutter repository synchronization operate from the same protocol.
- Local encryption decision, device bootstrap, data download, outbox, retries, conflict review, and recovery implemented.
- Network partition, clock skew, duplicate delivery, interrupted upgrade, corrupt local state, and prolonged-offline tests pass.

### Gate 7 — Reporting and search

- Operational reports read authoritative PostgreSQL projections.
- Elasticsearch powers discoverability and analytics where eventual consistency is acceptable.
- Rebuild, lag, stale index, reconciliation, and authorization tests pass.

### Gate 8 — Production readiness

- Load, soak, failover, restore, security, accessibility, compatibility, and disaster exercises pass.
- Runbooks, SLOs, alerts, dashboards, support procedures, rollback, and launch checklist approved.
- Pilot acceptance and data reconciliation completed.

### Cross-gate adaptive onboarding and channel provisioning

The platform now has a proposed plan for organizations that need POS only,
web ecommerce, mobile ecommerce, or both: [[NIU Adaptive Onboarding and Channel Provisioning Plan]].
This is deliberately cross-gate. Identity and tenant admission remain Gate 2
truth; catalogue, pricing, inventory, and customer consent remain owned by
their modules; web/mobile clients consume contracts through Gate 6; and
production approvals/observability belong in Gate 8. [[ADR-0062 Adaptive Onboarding Channel Provisioning and Customer Identity]]
records the proposed boundary.

## Planning workstreams

- Product and UX
- Domain and data
- APIs and events
- Offline synchronization
- Web and scanner hardware
- Flutter mobile
- Security, privacy, and compliance
- Infrastructure and Kubernetes
- Quality engineering
- Observability and operations
- Delivery, rollout, and support

## Immediate planning sequence

1. Validate the selected Kenya grocery profile with Kenyan tax/privacy counsel and confirm the KRA eTIMS integration and outage-recovery path.
2. Ratify `ADR-0001` and its component responsibility rules.
3. Complete domain vocabulary and module context maps.
4. Specify the sale, inventory, payment, shift, refund, and synchronization state machines.
5. Produce the initial logical data model and tenant-isolation matrix.
6. Produce API and event catalogues.
7. Produce per-module requirements and test catalogues.
8. Threat-model all trust boundaries.
9. Design deployment and recovery topology.
10. Run architecture, security, product, operations, and adversarial critique reviews.
11. Continue the adaptive onboarding/channel plan, define the provisioning worker,
    and complete MOD-ONBOARDING and
    MOD-CHANNELS contracts, and add branch, identity, automation, and
    customization acceptance tests before implementing the wizard.
