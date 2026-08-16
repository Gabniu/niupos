---
id: RESEARCH-BASELINE-0001
type: research
status: active
captured_at: 2026-08-07
owners:
  - architecture
sources:
  - https://www.postgresql.org/docs/current/ddl-rowsecurity.html
  - https://www.postgresql.org/docs/current/ddl-partitioning.html
  - https://www.rabbitmq.com/docs/reliability
  - https://www.elastic.co/docs/manage-data/data-store/near-real-time-search
  - https://kubernetes.io/docs/concepts/workloads/pods/disruptions/
  - https://docs.flutter.dev/app-architecture/design-patterns/offline-first
  - https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API/Using_IndexedDB
  - https://owasp.org/www-project-application-security-verification-standard/
  - https://www.pcisecuritystandards.org/document_library/?class=pcidss&doc=pci_dss
  - https://ref.gs1.org/standards/
  - https://www.w3.org/TR/WCAG22/
---

# Authoritative Research Baseline

## Architecture findings adopted

- PostgreSQL Row-Level Security provides per-row policies and default-deny behavior when enabled without a matching policy. Owners and bypass roles require special care, so NOVA uses application scoping, database constraints, RLS defense in depth, and explicit isolation tests.
- PostgreSQL recommends partitioning when tables become very large and access patterns justify it. NOVA will not partition every tenant table speculatively; it will retain partition-ready keys and measure first.
- RabbitMQ acknowledgements provide at-least-once delivery, and network uncertainty can create duplicates. NOVA therefore requires publisher confirms, manual consumer acknowledgements, stable message IDs, inbox deduplication, and idempotent consumers.
- Elasticsearch is near-real-time rather than immediately consistent. Search is a rebuildable projection and is excluded from correctness decisions.
- Kubernetes disruption budgets reduce some voluntary disruption risk but do not cover every deletion or application failure. NOVA requires replicas, topology distribution, graceful termination, readiness, and tested recovery in addition to PDBs.
- Flutter's offline-first guidance places local/remote coordination behind repositories. NOVA applies a repository boundary and shared synchronization protocol.
- IndexedDB supports substantial structured offline data, but browser quota, eviction, upgrade, multi-tab, shutdown, and corruption behavior require explicit supported-device testing.

## Standards baseline

- OWASP ASVS 5.0.0 is the application-security verification baseline; requirements will be referenced with versioned identifiers.
- PCI DSS 4.0.1 is the payment-card security baseline. The design minimizes scope by using hosted/tokenized provider flows and storing no PAN or CVV.
- GS1 General Specifications 26.0.0 is the barcode semantics baseline. The scan model must support GTINs, application identifiers, variable measure, batches, expiry data, and coexistence of linear and 2D retail codes.
- WCAG 2.2 AA is the target accessibility baseline for supported web and mobile workflows.

## Version policy

- Pin exact runtime, container, SDK, and dependency versions in lockfiles and deployment manifests.
- Select only versions within upstream security support at implementation start.
- Record an upgrade owner and supported-until date for every runtime and stateful dependency.
- Automated dependency updates must pass the complete applicable test suite; major upgrades require an ADR or upgrade record and rollback plan.
- Research is time-sensitive and must be revalidated before implementation and production release.

## Remaining research tracks

- Launch-country fiscalization, tax, privacy, employment, receipt, and retention rules.
- Selected payment provider API, webhook, dispute, reversal, settlement, and sandbox behavior.
- Receipt printer and cash-drawer protocols and supported device matrix.
- Browser camera/scanner behavior across managed device models.
- Kubernetes provider/operator choices and stateful-service ownership.
- Laravel/Next.js/Flutter version selection at repository bootstrap.
- OpenTelemetry, metrics, logging, alerting, and SLO toolchain.
- Backup, PITR, object storage, key management, secret management, and regional disaster recovery.

