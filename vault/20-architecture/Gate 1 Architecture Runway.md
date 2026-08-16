---
id: GATE-1-RUNWAY-0001
type: implementation-evidence
status: in-progress
date: 2026-08-07
owners:
  - architecture
  - platform
requirements:
  - REQ-G1-REPO-001
  - REQ-G1-LOCAL-INFRA-001
  - REQ-G1-RUNTIME-001
  - REQ-G1-TENANT-ISOLATION-001
adrs:
  - ADR-0001
  - ADR-0002
  - ADR-0003
modules:
  - MOD-PLATFORM
risks:
  - RISK-G1-TOOLCHAIN-001
  - RISK-G1-IMAGE-SUPPLY-001
tests:
  - TEST-G1-ARCH-001
  - TEST-G1-COMPOSE-001
  - TEST-G1-RUNTIME-001
  - TEST-G1-API-001
  - TEST-G1-WEB-001
  - TEST-G1-TENANT-CONTEXT-001
  - TEST-G1-POSTGRES-RLS-001
  - TEST-G1-TENANT-BOUNDARIES-001
---

# Gate 1 Architecture Runway

## Implemented evidence

| Requirement | Implementation | Acceptance evidence | Tests | Risks | ADR |
|---|---|---|---|---|---|
| REQ-G1-REPO-001 — Establish explicit API, web, mobile, shared-contract, infrastructure, and architecture-test boundaries before feature code. | `apps/api`, `apps/web`, `apps/mobile`, `packages/contracts`, `infra`, `tests/architecture`; root workspace and coding-policy files. | Repository test locates every boundary; ADR-0001 is accepted; generated knowledge remains excluded from authored input. | TEST-G1-ARCH-001 (`npm test`) | RISK-G1-TOOLCHAIN-001 | ADR-0001 |
| REQ-G1-LOCAL-INFRA-001 — Provide a reproducible local topology for every mandatory non-client platform component without requiring host PHP or Flutter. | `infra/compose.yaml` defines health-checked PostgreSQL, Redis, RabbitMQ, and Elasticsearch services. | `docker compose config --quiet` passes; starting services and failure exercises remain pending. | TEST-G1-COMPOSE-001 (`npm run infra:config`) | RISK-G1-IMAGE-SUPPLY-001 | ADR-0001 |
| REQ-G1-RUNTIME-001 — Use supported, pinned runtime and framework lines with committed lockfiles. | Laravel 13 API with `composer.lock`; Next.js 16.3 web application with root `package-lock.json`; ADR-0002 records PHP, Node, TypeScript, Flutter, and upgrade policy. | Strict Composer validation passes; Laravel tests pass; Next.js lint and production build pass; architecture tests assert exact framework lines and lockfiles. | TEST-G1-RUNTIME-001, TEST-G1-API-001, TEST-G1-WEB-001 | RISK-G1-DEPENDENCY-001 | ADR-0002 |
| REQ-G1-TENANT-ISOLATION-001 — Tenant identity is explicit and immutable per execution scope, tenant tables carry scoped keys, and PostgreSQL RLS fails closed for cross-tenant access. | `app/Modules/Tenancy` owns TenantId, TenantContext, TenantScope, HTTP/job middleware, tenant cache keys, event envelopes, models and migration; organization RLS uses transaction-local `app.tenant_id`. A tenant header is denied until the IAM authorization adapter approves membership. | Laravel tests prove context cleanup across HTTP/jobs, default-deny authorization, tenant-prefixed cache keys, tenant-bearing events, and fail-closed behavior; real PostgreSQL proves cross-tenant rows are hidden and inserts rejected; repository fitness tests protect module internals. | TEST-G1-TENANT-CONTEXT-001, TEST-G1-POSTGRES-RLS-001, TEST-G1-TENANT-BOUNDARIES-001, TEST-G1-ARCH-001 | RISK-TENANT-CONTEXT-001 | ADR-0003 |

## Decisions

- Host PHP/Composer and Flutter are currently unavailable, so the runway is container-first.
- Laravel and Next.js applications are generated and runnable through the accepted toolchains. Flutter remains pending its application bootstrap.
- Local service versions are explicit and must be reviewed through the dependency policy before production use.

## Risks

- RISK-G1-TOOLCHAIN-001 — Missing host PHP/Composer and Flutter could hide platform-specific developer friction. Control: add version-pinned development containers and exercise them in CI and on a second workstation before Gate 1 closes.
- RISK-G1-IMAGE-SUPPLY-001 — Mutable container tags or unavailable registries could break reproducibility. Control: verify pulls, record resolved digests, scan images, and establish an approved upgrade cadence before shared environments.

## Remaining Gate 1 work

The pinned PHP 8.5 CI image, dev container, and GitHub Actions verification contract are defined under [[ADR-0013 Containerized CI Verification]]. Static configuration checks pass; the first remote CI execution remains the acceptance evidence for image build and the complete containerized suite.

REQ-G1-K8S-OBS-001 and TEST-G1-K8S-OBS-001 define a Kustomize base/development overlay, restricted workloads, resource bounds, probes, disruption budgets, default-deny networking, opt-in Prometheus discovery, and target-availability alerting under [[ADR-0016 Kubernetes Development Observability Baseline]]. Rendering is proven locally; cluster admission, rollout, network enforcement, and metric semantics remain environment acceptance work.

- Implement and continuously verify ADR-0002 runtime and lockfile constraints.
- Generate real Laravel, Next.js, and Flutter applications through version-pinned toolchains.
- Extend the proven module-boundary and tenancy/RLS rules to jobs, HTTP middleware, caches, events, search, exports, and every new tenant-owned table.
- Start the local topology and execute Redis loss, RabbitMQ redelivery, and Elasticsearch outage smoke tests.
- Add contract generation, secret delivery, test-data, and recovery evidence; execute and retain the first green remote CI run and a development-cluster deployment/rollback exercise.
