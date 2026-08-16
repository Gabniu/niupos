---
id: MOD-CATALOGUE-0001
type: module-catalogue
status: draft
owners:
  - architecture
  - product
adrs:
  - ADR-0001
requirements:
  - REQ-KE-ETIMS-001
  - REQ-KE-OFFLINE-INVOICE-001
  - REQ-KE-MPESA-001
  - REQ-KE-PRIVACY-001
  - REQ-KE-GROCERY-001
---

# MVP Module Catalogue

## Delivery classification

- **Operational MVP:** implemented for launch workflows.
- **Foundation MVP:** required technical/domain foundation even if its UI is minimal.
- **Contract planned:** boundaries and data ownership planned now; feature implementation follows the MVP.

| Module ID | Module | Classification | Owns | Must not own |
|---|---|---|---|---|
| MOD-TENANCY | Tenancy and organization | Foundation MVP | Tenant, company, branch, feature entitlement, jurisdiction profile | User credentials, product data |
| MOD-IAM | Identity and access | Foundation MVP | Users, authentication bindings, roles, permissions, sessions, MFA policy | Employee payroll, sale authorization outcomes |
| MOD-REGISTER | Register and device | Foundation MVP | Register, device enrollment, capability profile, sequence allocation | Sales or payments |
| MOD-SHIFTS | Shifts and cash control | Operational MVP | Shift lifecycle, float, cash movements, expected/counted totals, variance | General ledger accounting |
| MOD-CATALOGUE | Catalogue | Operational MVP | Products, variants, units, categories, barcodes, attributes | Stock balances, sale price snapshots |
| MOD-PRICING | Pricing and tax | Operational MVP | Price books, price rules, tax categories, rounding policy | Payment settlement, historical sale mutation |
| MOD-INVENTORY | Inventory | Operational MVP | Locations, movements, balances, reservations, counts, adjustments | Product descriptive master data |
| MOD-SALES | Cart and sales | Operational MVP | Cart, sale state, line snapshots, discounts, totals, void/refund coordination | Provider secrets, search index |
| MOD-PAYMENTS | Payments | Operational MVP | Tenders, payment attempts, provider references, status, allocation, reconciliation | Raw PAN/CVV, sale line pricing |
| MOD-RECEIPTS | Receipts | Operational MVP | Receipt numbering, immutable render model, delivery/print attempts | Sale calculation |
| MOD-SYNC | Device synchronization | Foundation MVP | Device cursor, change feed, outbox intake, deduplication, conflict records | Domain-specific conflict decisions |
| MOD-REPORTS | Reporting | Operational MVP | Operational projections and report definitions | Authoritative transactional mutation |
| MOD-SEARCH | Search projection | Foundation MVP | Elasticsearch documents, index versions, rebuild state | Source business truth |
| MOD-AUDIT | Audit and compliance evidence | Foundation MVP | Append-only audit events, access evidence, export evidence | Application logs or analytics telemetry |
| MOD-NOTIFY | Notifications | Contract planned | Templates, delivery requests, preferences, delivery status | Business decisions about when to notify |
| MOD-CUSTOMERS | Customers and loyalty | Contract planned | Customer profiles, consent, loyalty accounts | Sales history ownership |
| MOD-SUPPLIERS | Suppliers and purchasing | Contract planned | Suppliers, purchase orders, receipts, supplier obligations | Inventory truth outside posted movements |
| MOD-EMPLOYEES | Workforce | Contract planned | Employee profile, attendance, commission policy | Authentication credentials |
| MOD-FINANCE | Finance and accounting | Contract planned | Expense, accounting export, journal integration | Rewriting sales/payment history |
| MOD-ENTERPRISE | Enterprise administration | Contract planned | Multi-branch policy, integration governance, branding | Duplicated module data |
| MOD-ONBOARDING | Adaptive onboarding and provisioning | Draft, channel, dry-run provisioning, worker, and timeline contracts implemented; verified external executors pending | Versioned blueprints, resumable drafts, capability selection, approval-gated provisioning runs, setup timeline | Tenant ledger truth, identity secrets, autonomous payment/legal decisions |
| MOD-CHANNELS | Commerce channel configuration | Registration contract implemented; publication worker pending | Tenant-scoped web/mobile channel metadata, approval state, publication/build state, domain and fulfillment configuration | Customer credentials, payment secrets, catalogue/sale truth |

## Dependency principles

- Catalogue does not query Inventory to define a product.
- Sales requests prices and taxes through Pricing contracts and stores immutable snapshots.
- Sales finalization creates inventory intent; Inventory owns the resulting movements.
- Payments reference sales but maintain their own attempt and reconciliation lifecycle.
- Reports consume committed facts and projections; they never mutate operational modules.
- Search consumes versioned events and can be rebuilt completely.
- Sync transports commands and changes; domain modules decide whether a command is valid.
- Audit observes outcomes through explicit audit records; it is not reconstructed only from logs.

## Required specification per module

Before implementation, every Operational or Foundation MVP module must have:

- glossary and bounded-context definition
- actors and permissions
- use cases and non-goals
- commands, queries, events, and APIs
- aggregates/entities/value objects
- invariants and database constraints
- state machines
- tenant and branch scoping
- offline behavior and conflict policy
- security/privacy classification
- error catalogue
- observability and audit events
- migrations and seed/test-data plan
- unit, property, integration, contract, E2E, performance, security, recovery, and acceptance tests as applicable
- rollout, feature flag, compatibility, rollback, and data-reconciliation plan
