# NOVA POS

NOVA is an offline-first, multi-tenant POS platform. The first launch profile targets small grocery and convenience retailers in Kenya.

## Deployed

| | URL | |
|---|---|---|
| POS (web + API) | https://pos.niuautomations.com | live, behind HTTP basic auth |
| NOVA Identity | https://novaauth.niuautomations.com | live, public |

**Read [`DEPLOYMENT.md`](DEPLOYMENT.md) before deploying or debugging anything on
the server.** It covers how to sign in (POS has **no user account yet** — one
must be created by hand), how to ship a change, and the traps that cost real
time the first time: migration ordering on PostgreSQL, why `migrate` must use
`compose run` rather than `compose exec`, and why the SQLite test suite cannot
catch either.

Architecture rationale for the deployment: `vault/20-architecture/ADR-0048 Containerized Public API Deployment.md`.

## Repository map

- `apps/api` — Laravel authoritative domain API and workers
- `apps/web` — Next.js cashier PWA and back office
- `apps/mobile` — Flutter mobile and native hardware client
- `packages/contracts` — versioned API, event, and synchronization contracts
- `infra` — local dependency topology and later Kubernetes manifests
- `tests/architecture` — repository and dependency-boundary fitness tests
- `vault` — human-authored planning and architecture system
- `graphify-out` — generated machine-queryable project graph

## Current runnable checks

```powershell
npm test
npm run infra:config
npm run lint --workspace @nova/web
npm run build --workspace @nova/web
```

Start local dependencies with `npm run infra:up`. No host PHP or Composer installation is required: `scripts/Bootstrap-Api.ps1` installs the locked Laravel dependencies through the accepted Composer container. Flutter application generation remains pending.

## Source of truth

Ratified ADRs and requirements lead. Generated Graphify artifacts expose relationships and drift but do not override approved source documents.
