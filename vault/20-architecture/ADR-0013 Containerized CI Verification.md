---
id: ADR-0013
type: architecture-decision
status: accepted
date: 2026-08-08
owners:
  - platform
requirements:
  - REQ-G1-CI-001
  - REQ-G1-CONTAINER-001
modules:
  - MOD-PLATFORM
tests:
  - TEST-G1-CI-NODE-001
  - TEST-G1-CI-API-001
  - TEST-G1-CI-COMPOSE-001
risks:
  - RISK-G1-CI-DRIFT-001
  - RISK-G1-IMAGE-SUPPLY-001
---

# ADR-0013 Containerized CI Verification

## Context

NOVA POS is container-first because PHP and Composer cannot be assumed on every developer workstation. Gate 1 needs repeatable evidence that repository boundaries, the web application, the Laravel API, and the local service topology remain valid. A host-only API test command would leave PHP extension and runtime compatibility implicit, while unrelated commands in CI and local development would allow verification drift.

## Decision

- GitHub Actions runs two independent, least-privilege jobs: Node/Compose verification and Laravel verification.
- Node is fixed to 24.12.0. The Node job installs only the committed lockfile with `npm ci`, runs architecture tests, lints and builds Next.js, and validates `infra/compose.yaml` without starting services.
- Laravel verification builds `infra/docker/php-ci/Dockerfile`, based on `php:8.5.3-cli-bookworm` with Composer 2.9.3 and the SQLite, XML, mbstring, and ZIP extensions required by the locked test toolchain.
- `scripts/Test-CiVerification.ps1` is the shared local/CI entry point. The API source is mounted read-only and copied to a temporary filesystem before Composer installation and tests, so verification does not rewrite the working tree.
- `.devcontainer` uses the same PHP and Composer versions and adds Node 24.12.0 for a reproducible full-stack development shell.
- CI has read-only repository permission, finite timeouts, branch-aware concurrency cancellation, and no secrets or deployment authority.

## Traceability and acceptance evidence

| Requirement | Acceptance criteria | Test evidence | Risk controlled |
|---|---|---|---|
| REQ-G1-CI-001 — Every change is checked against architecture, locked dependencies, API behavior, web lint/build, and Compose syntax. | A pull request or push to `main` executes both CI jobs successfully with no deployment credentials. | TEST-G1-CI-NODE-001 (`npm run test:architecture`, web lint/build); TEST-G1-CI-API-001 (`composer validate --strict`, `php artisan test`); TEST-G1-CI-COMPOSE-001 (`docker compose ... config --quiet`). | RISK-G1-CI-DRIFT-001 |
| REQ-G1-CONTAINER-001 — API verification and development use a PHP 8.5-compatible, SQLite-capable container rather than an undocumented host runtime. | The pinned CI image builds, reports PHP 8.5, exposes `pdo_sqlite`, and completes Composer validation and Laravel tests. The development container uses the same runtime line. | TEST-G1-CI-API-001 (`scripts/Test-CiVerification.ps1 -ApiOnly`). | RISK-G1-IMAGE-SUPPLY-001 |

## Consequences

- Node and Laravel checks execute concurrently and have no shared mutable state.
- The PHP image is intentionally test-focused; it is not a production deployment image.
- Image tags and action major versions are explicit, but registry tags can still be replaced. Digest pinning, image scanning, and an approved dependency update cadence remain required before shared or production environments.
- Full local verification requires Docker, Node.js 24, and npm. API-only verification requires Docker alone.

## Risks

- RISK-G1-CI-DRIFT-001 — Local and hosted checks could diverge. Control: CI invokes the repository-owned PowerShell entry point for the complete API sequence, while the ADR records the Node/Compose commands verbatim.
- RISK-G1-IMAGE-SUPPLY-001 — An upstream tag can become unavailable or change contents. Control: exact version tags are used, pulls fail closed, and digest pinning/scanning remains a Gate 1 follow-up.

## Deferred work

- Pin approved image digests after registry and architecture coverage are finalized.
- Add dependency and container vulnerability scanning with an explicit exception policy.
- Prove the development container on a second workstation.
- Add Kubernetes, observability, recovery, and service-failure exercises as separate Gate 1 evidence.
