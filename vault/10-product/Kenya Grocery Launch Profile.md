---
id: PROFILE-KE-GROCERY-0001
type: launch-profile
status: selected-pending-compliance-validation
date: 2026-08-07
owners:
  - product
  - compliance
  - architecture
requirements:
  - REQ-KE-ETIMS-001
  - REQ-KE-OFFLINE-INVOICE-001
  - REQ-KE-MPESA-001
  - REQ-KE-PRIVACY-001
  - REQ-KE-GROCERY-001
adrs:
  - ADR-0001
modules:
  - MOD-TENANCY
  - MOD-CATALOGUE
  - MOD-PRICING
  - MOD-INVENTORY
  - MOD-SALES
  - MOD-PAYMENTS
  - MOD-RECEIPTS
  - MOD-SYNC
  - MOD-AUDIT
risks:
  - RISK-KE-LEGAL-001
  - RISK-KE-ETIMS-001
  - RISK-KE-PAYMENTS-001
tests:
  - TEST-KE-ETIMS-CONTRACT-001
  - TEST-KE-OFFLINE-INVOICE-001
  - TEST-KE-MPESA-RECON-001
  - TEST-KE-PRIVACY-001
  - TEST-KE-GROCERY-E2E-001
---

# Kenya Grocery Launch Profile

## Decision

NOVA's first launch profile targets small grocery and convenience retailers in Kenya. The operating currency is Kenyan shillings (KES). The MVP accepts cash and M-Pesa merchant payments, supports barcode and manual product lookup, and produces KRA eTIMS-compatible electronic tax invoices and receipt renderings.

This profile is selected for product planning. Kenyan counsel and KRA integration review remain mandatory Gate 0 exit evidence.

## Why this is the best first proving ground

- Grocery checkout makes scan-to-cart latency, stock accuracy, tax classification, receipts, cash control, mobile payments, and offline operation daily—not edge—concerns.
- The vertical is broad enough to validate NOVA's reusable retail core but narrower than pharmacy, fuel, or hospitality.
- Kenya's eTIMS rules provide an explicit jurisdiction adapter contract: every sale, invoice fields, transmission, stock records, outage recovery, authentication, integrity, and audit logging are testable obligations.
- Cash and mobile-money support reflects the launch environment while keeping raw card data outside the MVP.

## MVP boundaries

Included:

- single-branch and small multi-branch grocery/convenience operations
- packaged goods, simple fractional/weighted quantities, mixed VAT categories, discounts, returns, and stock counts
- keyboard-wedge scanners, camera scanning, and manual lookup
- cash and M-Pesa merchant-payment tender and reconciliation
- on-screen, printable, and shareable digital receipts
- seven-day essential offline trading target, subject to the eTIMS outage/recovery design below

Excluded from the first release:

- pharmacy prescriptions and controlled products
- fuel-pump and forecourt control
- restaurant tables, kitchen routing, and hospitality service charges
- full purchasing, supplier accounting, payroll, and general ledger
- NOVA-handled raw card PAN/CVV
- multi-country or multi-currency operation within one branch

## Compliance-derived requirements

| Requirement | Owning module(s) | Acceptance criteria | Planned tests | Risks | ADR |
|---|---|---|---|---|---|
| REQ-KE-ETIMS-001 — Each finalized in-scope sale creates an immutable invoice submission record containing the fields required by the Tax Procedures (Electronic Tax Invoice) Regulations, 2024, including seller PIN, issue time, serial, buyer PIN when supplied, totals, tax, item code, description, quantity, unit, rate, unique identifiers, and QR data. | MOD-SALES, MOD-PRICING, MOD-RECEIPTS, MOD-AUDIT | A finalized sale and every credit/debit correction retain the exact submitted payload and link corrections to the original invoice; receipt renderings expose the required identifiers without recalculating financial truth. | TEST-KE-ETIMS-CONTRACT-001 | RISK-KE-LEGAL-001, RISK-KE-ETIMS-001 | ADR-0001 |
| REQ-KE-OFFLINE-INVOICE-001 — Loss of KRA connectivity never silently converts an invoice into a compliant submission. NOVA records the outage state, preserves local sales, exposes operator status, retries idempotently, and supports the regulator-approved recovery procedure. | MOD-SALES, MOD-RECEIPTS, MOD-SYNC, MOD-AUDIT | Network-loss tests prove no sale or invoice is duplicated or discarded; pending, accepted, and rejected submissions reconcile; operational alerts and an exportable outage ledger exist. The exact 24-hour notification workflow is approved before release. | TEST-KE-OFFLINE-INVOICE-001 | RISK-KE-LEGAL-001, RISK-KE-ETIMS-001 | ADR-0001 |
| REQ-KE-MPESA-001 — M-Pesa is implemented behind a payment-provider adapter with idempotent initiation/callback handling and independent settlement reconciliation. | MOD-PAYMENTS, MOD-SALES, MOD-AUDIT | Duplicate, delayed, reordered, missing, and contradictory callbacks cannot double-pay a sale; provider reference, tender allocation, callback evidence, and settlement exception are traceable. | TEST-KE-MPESA-RECON-001 | RISK-KE-PAYMENTS-001 | ADR-0001 |
| REQ-KE-PRIVACY-001 — Tenant and NOVA roles as data controller/processor are documented; personal data is minimized, purpose-bound, access-controlled, retained by policy, and protected for cross-border transfer. | MOD-TENANCY, MOD-IAM, MOD-AUDIT | Data inventory covers cashier, customer, supplier, and payment-reference data; access/erasure/correction workflows and processor contracts are tested; hosting-region choice records transfer safeguards. | TEST-KE-PRIVACY-001 | RISK-KE-LEGAL-001 | ADR-0001 |
| REQ-KE-GROCERY-001 — The launch workflow supports a cashier opening a shift, scanning or finding mixed-tax grocery items, accepting cash or M-Pesa, issuing a compliant receipt, updating inventory exactly once, and later reconciling the shift and eTIMS/payment state. | MOD-SHIFTS, MOD-CATALOGUE, MOD-PRICING, MOD-INVENTORY, MOD-SALES, MOD-PAYMENTS, MOD-RECEIPTS | The full journey works online and under each approved degraded mode; totals and stock movements are reproducible; p95 scan-to-cart and checkout targets are set during the pilot baseline. | TEST-KE-GROCERY-E2E-001 | RISK-KE-ETIMS-001, RISK-KE-PAYMENTS-001 | ADR-0001 |

## Risks and controls

| Risk | Trigger | Control and contingency | Owner | Gate |
|---|---|---|---|---|
| RISK-KE-LEGAL-001 — The compliance interpretation is incomplete or changes. | Counsel, KRA, ODPC, or amended law contradicts the profile. | Maintain a versioned jurisdiction adapter and legal-source register; obtain Kenyan tax/privacy review; feature-flag affected behavior; never encode mutable rates as domain constants. | Compliance | Gate 0 and every release |
| RISK-KE-ETIMS-001 — Production eTIMS integration, certification, availability, or outage procedure differs from planning assumptions. | KRA sandbox/production validation fails or guidance changes. | Build a contract-tested adapter, immutable submission ledger, reconciliation queue, and operator runbook; pilot with KRA-confirmed integration method before launch. | Architecture/Compliance | Gates 0, 3, 5, 8 |
| RISK-KE-PAYMENTS-001 — Provider callbacks and settlement records disagree with local state. | Duplicate/missing callback, timeout, reversal, or settlement mismatch. | Use provider idempotency/reference keys, signed callback verification, append-only attempts, polling/requery where supported, and daily exception reconciliation. | Payments/Finance | Gates 5 and 8 |

## Authoritative sources reviewed 2026-08-07

- [KRA eTIMS overview](https://www.kra.go.ke/online-services/etims)
- [Tax Procedures (Electronic Tax Invoice) Regulations, 2024](https://new.kenyalaw.org/akn/ke/act/ln/2024/64)
- [KRA VAT guidance](https://www.kra.go.ke/individual/filing-paying/types-of-taxes/value-added-tax)
- [Kenya Data Protection Act](https://new.kenyalaw.org/akn/ke/act/2019/24/eng%402022-12-31)
- [ODPC registration guidance](https://www.odpc.go.ke/faqs/)
- [CBK National Financial Inclusion Strategy 2025–2028](https://www.centralbank.go.ke/wp-content/uploads/2025/12/Kenya-National-Financial-Inclusion-Strategy-2025-2028.pdf)
- [KNBS MSME Survey catalogue](https://statistics.knbs.or.ke/nada/index.php/catalog/69/study-description)

## Open validation questions

- Which KRA integration option and approval process applies to NOVA as a multi-tenant POS provider: OSCU, VSCU, or an approved intermediary model?
- What exact workflow does KRA approve for a register that trades through a prolonged network outage, including the 24-hour notification and later entry requirements?
- Must NOVA register as a Kenyan data processor before pilot, and which customer categories remove any small-entity exemption?
- Which M-Pesa product and commercial agreement will be used for merchant collection, query, reversal, and settlement files?
- Which grocery categories and units must be in the pilot fixture set to cover zero-rated, exempt, and standard-rated treatment without hard-coding current rates?
