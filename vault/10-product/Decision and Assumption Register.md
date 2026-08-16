---
id: DEC-REGISTER-0001
type: decision-register
status: active
owners:
  - product
  - architecture
requirements: []
adrs:
  - ADR-0001
risks:
  - RISK-KE-LEGAL-001
  - RISK-KE-ETIMS-001
  - RISK-KE-PAYMENTS-001
tests:
  - TEST-KE-ETIMS-CONTRACT-001
  - TEST-KE-OFFLINE-INVOICE-001
  - TEST-KE-MPESA-RECON-001
---

# Decision and Assumption Register

This register prevents unanswered questions from silently becoming architecture.

## Blocking decisions

| ID | Decision | Why it matters | Temporary planning assumption | Owner | Needed by |
|---|---|---|---|---|---|
| DEC-001 | Launch country | Taxes, fiscal receipts, privacy, payments, retention, currency | **Selected 2026-08-07: Kenya**, subject to compliance-counsel validation before Gate 0 closes | Product | Gate 0 validation |
| DEC-002 | First retail vertical | Units, weighted goods, expiry, batches, prescriptions, table service | **Selected 2026-08-07: small grocery and convenience retail**; exclude pharmacy, fuel, hospitality, and regulated goods workflows from MVP | Product | Workflow sign-off |
| DEC-003 | Initial payment methods | PCI scope, reconciliation, refunds, settlement | **Selected for MVP: cash and M-Pesa merchant payments** behind a provider adapter; card contracts planned but card acceptance is not a launch dependency; NOVA stores no PAN | Product/Finance | Payment requirements |
| DEC-004 | Supported receipt channels | Hardware integration and legal evidence | **Selected for MVP: on-screen and printable compliant receipt, plus shareable digital receipt**; eTIMS identifiers and QR data must survive every rendering channel | Product/Compliance | Receipt contract |
| DEC-005 | Negative stock policy | Offline sales and inventory correctness | Configurable per tenant; warnings by default, hard block optional | Product | Before inventory state machine |
| DEC-006 | Offline duration target | Local data volume, credential expiry, conflict risk | Seven days of essential trading without server contact | Product/Operations | Before sync sizing |
| DEC-007 | Hosting region and provider | Data residency, Kubernetes services, backups, latency | Provider-neutral Kubernetes design | Operations/Compliance | Before infrastructure ADR |
| DEC-008 | Browser and device support | Scanner, camera, PWA, IndexedDB, printing | Current managed Chrome/Edge for fixed registers; defined mobile baseline later | Product/QA | Before compatibility matrix |
| DEC-009 | Multi-currency behavior | Money model and reporting | One operating currency per branch; reporting currency at organization level | Finance | Before money rules |
| DEC-010 | Costing method | Profit and inventory valuation | Weighted-average costing initially; schema supports alternatives | Finance | Before inventory valuation |

## Ratified launch direction

The initial product profile is [[Kenya Grocery Launch Profile]]. Kenya provides a concrete compliance adapter boundary through KRA eTIMS, a mature mobile-money environment, and a large small-retail problem space. Small grocery and convenience retail exercises NOVA's core catalogue, barcode, mixed-tax, stock, receipt, cash, mobile-payment, and offline capabilities without importing pharmacy, fuel, or hospitality-specific regulation into the first release.

This is a product decision, not a legal conclusion. `RISK-KE-LEGAL-001` remains open until Kenyan tax and privacy counsel validates the compliance matrix and KRA confirms the production integration path.

## Architecture assumptions requiring proof

| ID | Assumption | Proof required | Failure response |
|---|---|---|---|
| ASM-001 | Laravel module boundaries can remain enforceable in one deployment | Architecture tests fail on forbidden dependencies | Split package boundary before adding features |
| ASM-002 | PostgreSQL can remain transactional source of truth at expected MVP scale | Load test with representative tenant skew and reports | Add read replicas/projections; do not move correctness to cache/search |
| ASM-003 | Redis loss must not lose committed business data | Kill/flush Redis during checkout and worker tests | Rebuild cache; continue authoritative operations with bounded degradation |
| ASM-004 | RabbitMQ duplicates are expected and safe | Redelivery and publisher uncertainty tests | Require inbox deduplication and idempotent handlers |
| ASM-005 | Elasticsearch may be stale or unavailable | Disable index and introduce refresh lag | Fall back to bounded PostgreSQL search for critical checkout lookup |
| ASM-006 | IndexedDB supports the required PWA working set | Quota, eviction, migration, and corruption tests on supported devices | Reduce projection, require managed storage, or use native Flutter register mode |
| ASM-007 | A shared sync protocol can serve Next.js PWA and Flutter | Contract and conformance suite against both clients | Version protocol; isolate client-specific adapters |
| ASM-008 | Kubernetes complexity is controllable for the team | Restore, rollout, secret rotation, and on-call exercises | Use managed services/operators while retaining Kubernetes app deployment |

## Non-negotiable invariants

- Tenant data must never cross tenant boundaries.
- A finalized sale cannot be edited; correction uses void/refund/compensating records.
- Monetary arithmetic never uses binary floating point.
- Retrying a command cannot create a second sale, payment, refund, or stock movement.
- Inventory truth is explainable through movements and reconciliations.
- Cache, queue, and search outages cannot redefine financial truth.
- Every privileged or financially material action produces immutable audit evidence.
- Offline conflicts are surfaced and reconciled; no valid local transaction is silently discarded.
- Sensitive payment credentials and raw primary account numbers are not stored by NOVA.
