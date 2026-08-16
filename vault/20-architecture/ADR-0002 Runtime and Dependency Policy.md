---
id: ADR-0002
type: decision
status: accepted
date: 2026-08-07
owners:
  - architecture
  - platform
requirements:
  - REQ-G1-RUNTIME-001
modules:
  - MOD-PLATFORM
risks:
  - RISK-G1-DEPENDENCY-001
tests:
  - TEST-G1-RUNTIME-001
supersedes: null
superseded_by: null
---

# ADR-0002 — Runtime and dependency policy

## Decision

Use the following Gate 1 baselines:

| Surface | Baseline | Constraint |
|---|---|---|
| PHP | 8.5 | Pin the minor in development/CI images; lock patch releases through image digests. |
| Laravel | 13.8+ within 13.x | Composer constraint `^13.8`; commit `composer.lock`. |
| Node.js | 24 LTS line | Root engine `>=24`; pin exact CI/container image and commit npm lockfiles. |
| Next.js | 16.3.x | Use App Router, TypeScript strict mode, ESLint, and Turbopack defaults. |
| React | 19.2.x | Follow the Next.js-supported React line. |
| TypeScript | 7.0.x | Strict mode; generated contracts must type-check without handwritten divergence. |
| Flutter | 3.44.9 stable | Pin with Dart 3.12.2 and commit `pubspec.lock` for applications. |

REQ-G1-RUNTIME-001 requires every runnable application and CI job to reject unsupported runtime majors and to use committed dependency lockfiles. TEST-G1-RUNTIME-001 verifies manifests, lockfiles, and runtime declarations.

## Rationale

These are current supported stable lines on 2026-08-07. Laravel 13 supports PHP 8.3–8.5; PHP 8.5 has active support through 2027 and security support through 2029. Next.js 16 requires Node 20.9 or later, so Node 24 provides an LTS-aligned runway. Flutter's official release manifest identifies 3.44.9 with Dart 3.12.2 as stable.

## Upgrade policy

- Security patches are expedited after automated tests and image scans pass.
- Minor upgrades use dependency-update pull requests with contract, migration, and rollback evidence.
- Major upgrades require an ADR amendment or replacement.
- Floating `latest` tags are forbidden in CI and shared environments.
- Production images use immutable digests; local Compose may use explicit version tags until the first verified pull records digests.

## Risk

RISK-G1-DEPENDENCY-001: newly released major lines may expose ecosystem incompatibilities. Mitigation: keep framework-generated changes minimal, isolate adapters, run locked builds in CI, and maintain a documented downgrade path until Gate 1 closes.

## Sources reviewed

- [Laravel 13 release and support policy](https://laravel.com/docs/13.x/releases)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Next.js 16 upgrade requirements](https://nextjs.org/docs/app/guides/upgrading/version-16)
- [Flutter official release manifest](https://storage.googleapis.com/flutter_infra_release/releases/releases_windows.json)
