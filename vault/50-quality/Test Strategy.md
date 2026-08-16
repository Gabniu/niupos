---
id: TEST-STRATEGY-0001
type: test-strategy
status: draft
owners:
  - quality
  - architecture
requirements:
  - REQ-KE-ETIMS-001
  - REQ-KE-OFFLINE-INVOICE-001
  - REQ-KE-MPESA-001
  - REQ-KE-PRIVACY-001
  - REQ-KE-GROCERY-001
adrs:
  - ADR-0001
risks: []
---

# Test Strategy

## Quality principle

Tests are planned with requirements and implemented with each vertical slice. A milestone cannot defer correctness, security, offline, or recovery testing to a later stabilization phase.

## Test layers

| Layer | Purpose | Typical scope | Gate behavior |
|---|---|---|---|
| Static analysis | Reject unsafe or inconsistent code early | PHPStan/Larastan, Pint, ESLint, TypeScript strict mode, Dart analyzer, dependency/security scans | Every change |
| Architecture tests | Enforce module and dependency rules | Laravel module imports, forbidden infrastructure access, contract ownership | Every change |
| MFA HTTP and replay tests | Prove enrollment and privilege elevation boundaries | Authentication, confirmation, encrypted factor lifecycle, one-use TOTP steps, five-minute session elevation, throttling, secret-safe audit evidence | Every IAM change |
| Ownership-transfer HTTP test | Prove remote owner changes require step-up | Tenant admission, active-owner invariant, current-session MFA elevation, atomic audited transfer | Every IAM change |
| Organization hierarchy tests | Prove location ownership and parent integrity | Missing context, tenant-qualified company/branch/warehouse references, isolated reads, PostgreSQL RLS | Every tenancy/location change |
| Catalogue identity tests | Prove product and barcode identity boundaries | Normalized tenant-local SKU/barcode uniqueness, cross-tenant reference rejection, unknown/inactive resolution, PostgreSQL RLS | Every catalogue change |
| Containerized CI verification | Reproduce accepted toolchains on every change | Architecture tests, Composer validation, Laravel suite, Next.js lint/build, Compose validation | Every pull request |
| Register/device tests | Prove register ownership and enrollment credential lifecycle | Tenant-qualified branch/register references, digest-only token storage, expiry, replay denial, immutable public identity, PostgreSQL RLS | Every register/device change |
| Pricing/tax tests | Prove deterministic price selection and money arithmetic | Tenant isolation, active catalogue identity, half-open windows, overlap rejection, integer half-up inclusive/exclusive tax, PostgreSQL RLS | Every pricing change |
| Kubernetes baseline validation | Detect invalid non-production deployment configuration | Kustomize rendering, YAML structure, probes, restricted security contexts, resources, disruption budgets, network policy, secret references | Every infrastructure change |
| Unit tests | Prove deterministic domain rules | Money, tax, rounding, discounts, state transitions, conflict decisions | Every change |
| Property-based tests | Explore large invariant spaces | Totals, rounding conservation, inventory conservation, idempotency | Every change for critical rules |
| Database integration tests | Prove constraints and transaction behavior | PostgreSQL constraints, locks, RLS, migrations, concurrency | Every change touching persistence |
| Component integration tests | Prove real dependency behavior | Redis loss, Rabbit confirms/redelivery, Elasticsearch mappings/aliases | Merge and nightly suites |
| API contract tests | Prevent client/server drift | OpenAPI schema, error format, compatibility, idempotency headers | Every API change |
| Event contract tests | Prevent producer/consumer drift | AsyncAPI/schema fixtures, version compatibility, replay | Every event change |
| Client repository tests | Prove offline/local behavior | IndexedDB/Flutter DB migrations, outbox, queries, encryption adapters | Every client change |
| UI component tests | Prove states and accessibility | Keyboard use, screen reader semantics, error/loading/offline states | Every UI change |
| End-to-end tests | Prove user journeys | Open shift through receipt/refund/close shift | Merge and release suites |
| Hardware compatibility tests | Prove real device behavior | Scanners, cameras, printers, drawers, Bluetooth/native bridges | Device-lab matrix |
| Performance tests | Prove SLOs and capacity | Scan-to-cart, search, checkout, sync backlog, reports | Baseline, nightly smoke, release load/soak |
| Security tests | Prove controls and abuse resistance | Tenant escape, authz, injection, secrets, rate limits, ASVS mapping | Continuous plus release assessment |
| Resilience tests | Prove bounded degradation | Kill Redis/Rabbit/search, network partitions, worker crashes, pod eviction | Scheduled and release gates |
| Backup/restore tests | Prove recoverability | PostgreSQL PITR, search rebuild, configuration restore | Scheduled exercise |
| UAT/pilot tests | Prove operational fitness | Real cashier workflows and reconciliation | Pilot and release sign-off |

## Critical invariant suite

- A requested tenant scope is admitted only when the authenticated user has an active membership for that exact tenant; membership alone grants no business permission.
- API bearer credentials are stored only as irreversible digests; expiry and revocation take effect before tenant admission or permission evaluation.
- Login failures do not disclose account existence and repeated attempts are throttled by normalized principal and source address.
- Authentication and revocation outcomes produce append-only evidence without raw credentials, bearer tokens, email addresses, or network identifiers.
- Privileged IAM changes require an active-tenant management permission and commit with append-only tenant evidence; other tenants' roles and evidence remain inaccessible.
- HTTP IAM administration tests exercise the full authentication → tenant admission → management permission → mutation → evidence chain.
- Tenant bootstrap is single-use and no operation may revoke or reassign the last active owner.
- Operator bootstrap automation must provide attribution and an explicit force flag; replay must fail without replacing evidence.
- Ownership transfer locks both memberships, promotes the active target before demoting the source, and commits one tenant audit event atomically.
- TOTP tests prove encrypted secret storage, confirmation-before-activation, bounded time-step skew, and secret-free evidence.

- Sum of sale line totals, taxes, discounts, and rounding equals the sale total under the declared rounding policy.
- Payment allocations never exceed or understate the amount due except in explicit credit/overpayment states.
- Retrying completion with the same idempotency key returns the same finalized sale.
- A refund cannot exceed refundable quantity or amount after prior refunds.
- Posted inventory movements balance their declared quantity effect exactly once.
- A stock balance can be rebuilt from movements and equals the materialized balance.
- Shift expected cash equals opening float plus/minus posted cash-affecting movements.
- Cross-tenant identifiers never reveal existence, data, timing distinctions, cached data, search hits, exports, or events.
- An event may be delivered repeatedly without changing the result after its first successful application.
- Search may lag or disappear without changing authoritative results.
- Offline synchronization never silently drops an accepted local transaction.
- An eTIMS submission, retry, rejection, credit note, or debit note never creates or conceals a second financial outcome.
- An M-Pesa callback, requery, reversal, or settlement replay changes a payment attempt at most once under its provider reference and idempotency key.

## Kenya grocery launch suites

| Test ID | Scope | Requirement | Evidence |
|---|---|---|---|
| TEST-KE-ETIMS-CONTRACT-001 | Contract, integration, golden fixtures | REQ-KE-ETIMS-001 | Required invoice fields, tax categories, item/unit codes, identifiers, QR data, and original-invoice references for credit/debit notes match the approved KRA contract. |
| TEST-KE-OFFLINE-INVOICE-001 | Resilience, recovery, audit | REQ-KE-OFFLINE-INVOICE-001 | Partition and recovery exercises prove durable pending sales, visible compliance status, idempotent retransmission, reconciliation, and the approved outage ledger/notification workflow. |
| TEST-KE-MPESA-RECON-001 | Provider contract, security, reconciliation | REQ-KE-MPESA-001 | Signed callbacks, duplicates, reorder, timeout, requery, reversal, and settlement mismatch fixtures cannot duplicate tender allocation. |
| TEST-KE-PRIVACY-001 | Privacy, security, tenant isolation | REQ-KE-PRIVACY-001 | Data inventory, purpose/retention checks, data-subject workflows, access controls, processor boundaries, and cross-border safeguards pass. |
| TEST-KE-GROCERY-E2E-001 | End-to-end, performance, offline | REQ-KE-GROCERY-001 | Open shift → mixed-tax scan/cart → cash or M-Pesa → eTIMS/receipt → inventory movement → shift/payment/invoice reconciliation succeeds in approved modes. |

## Test data

- Deterministic factories use explicit tenant, branch, register, currency, timezone, tax, and clock.
- Synthetic data only in automated environments; no production personal/payment data is copied into test systems.
- Golden fixtures cover tax-inclusive/exclusive prices, zero/negative/large quantities, fractional units, daylight-saving boundaries, leap dates, Unicode, long names, duplicate barcodes, and malformed scans.
- Each bug fix begins with a failing regression test at the lowest useful layer.

## Time and nondeterminism

- Domain time comes through an injected clock.
- IDs come through an injected monotonic/unique ID provider where determinism matters.
- Queue tests control acknowledgement, reordering, duplication, and delay explicitly.
- Eventually consistent assertions poll bounded observable conditions; arbitrary sleeps are prohibited.

## Environments

- Unit/static tests run without external services.
- Integration tests run real version-pinned PostgreSQL, Redis, RabbitMQ, and Elasticsearch containers.
- Kubernetes tests use an ephemeral namespace or local cluster with production-equivalent manifests.
- Staging uses isolated synthetic tenants and provider sandboxes.
- Production verification is read-only or uses dedicated canary tenants/registers.

## Release evidence

Every release records:

- requirement and test traceability
- exact dependency and container versions
- migration dry-run and rollback/forward-fix result
- security and license scan result
- test suite result and flaky-test status
- performance comparison to baseline
- backup/restore freshness and last exercise
- known risks and approved exceptions
- deployment, smoke, reconciliation, and rollback evidence

## Flaky-test policy

Flaky tests are defects. A flaky critical-path test blocks release. Quarantine requires an owner, issue, expiry date, retained signal replacement, and documented risk acceptance.
