---
status: accepted
date: 2026-08-13
tags:
  - architecture
  - deployment
  - security
requirements:
  - REQ-DEPLOY-TEST-ENV-001
tests:
  - TEST-G1-POSTGRES-RLS-001
supersedes: []
relates-to:
  - "[[ADR-0002 Runtime and Dependency Policy]]"
  - "[[ADR-0003 Tenant Context and PostgreSQL RLS]]"
  - "[[ADR-0013 Containerized CI Verification]]"
---

# ADR-0048 — Containerized public API deployment

## Context

NOVA POS needed a PHP environment reachable over the internet so the API can be
exercised outside a development machine. The platform shares a host with the
Voice Platform, which runs live telephony, so the environment had to be added
without disturbing it.

Before this, only `apps/auth` had a deployable artifact
(`infra/docker/auth/Dockerfile`, `infra/compose-auth.yaml`). `apps/api` had no
production image, and `infra/compose.yaml` published PostgreSQL, Redis,
RabbitMQ and Elasticsearch on `0.0.0.0` — which on a host with a public address
exposes an unauthenticated Redis and a security-disabled Elasticsearch to the
internet.

## Decision

**A test environment is deployed with production security posture, differing
from production only in durability.** A test environment that is more
permissive than production tests the wrong system, and it holds real
credentials on a public hostname regardless of what the data is worth.

1. **One image containing nginx, php-fpm and the application**
   (`infra/docker/api/Dockerfile`), supervised by supervisord. `php artisan
   serve` is single-threaded and unsuitable for anything internet-facing.
   Splitting nginx and php-fpm into separate containers would require the
   application code to live in a shared volume, so the code would no longer be
   part of the immutable image.

2. **PHP pinned to the exact CI tag** (`php:8.5.3`), per
   [[ADR-0013 Containerized CI Verification]]. An environment on a different
   patch release than CI can pass CI and fail in the browser.

3. **Loopback bind plus host reverse proxy**, matching `compose-auth.yaml`. The
   container publishes `127.0.0.1:3010`; nginx on the host owns TLS and the
   public hostname. `infra/compose.yaml`'s services were rebound to
   `127.0.0.1` by default. Elasticsearch and RabbitMQ were **kept** — POS work
   will use them — but they are no longer published beyond the host.

4. **Two database roles, and the application uses the restricted one.**
   `nova_owner` owns the schema and runs migrations; `nova_app` owns nothing
   and is never granted `BYPASSRLS`.

   This is what makes [[ADR-0003 Tenant Context and PostgreSQL RLS]] real
   rather than nominal. **A table's owner bypasses that table's RLS policies by
   default**, silently — so an application connecting as the owner has every
   policy inert, with no error and no warning, and
   `TEST-G1-POSTGRES-RLS-001` would pass while exercising policies that were
   never enforced on that connection.

5. **Debug mode is refused outside `local`.** The container entrypoint fails to
   start on `APP_DEBUG=true` with a non-local `APP_ENV` unless
   `NOVA_ALLOW_PUBLIC_DEBUG=true` is set deliberately. Laravel's debug error
   page renders the resolved environment — `DB_PASSWORD`, `APP_KEY`, mail
   credentials — to anyone able to trigger an exception.

6. **HTTP basic auth in front of the environment.** Certificate issuance
   publishes the hostname to public Certificate Transparency logs within
   minutes, so an unlisted name is not a control.

## Consequences

### Found by building it

Three defects were invisible until the application met a real PostgreSQL. All
three are recorded because their common cause is one thing: **the test suite
runs on SQLite in-memory** (`phpunit.xml`), while ADR-0003 mandates PostgreSQL.

1. **Migrations could not run on PostgreSQL at all.** Every module used the
   timestamp `2026_08_08_000001`, so Laravel's filename ordering resolved
   cross-module foreign keys alphabetically by accident — `payment_tables`
   before `sales_tables`, `inventory_ledger_tables` before the Tenancy
   migration creating `warehouses`. SQLite resolves foreign keys lazily and
   accepts a reference to a table that does not exist yet; PostgreSQL validates
   immediately. Module migrations were renumbered `000001`–`000018` in
   dependency order.

2. **Trigger functions were not re-runnable.** Seven `CREATE FUNCTION`
   statements are now `CREATE OR REPLACE`. `migrate:fresh` drops tables but not
   functions, and does not call `down()`, so the second run aborted on
   "function already exists".

3. **A circular dependency in the service container** aborts four module test
   suites with a stack overflow, on SQLite and PostgreSQL alike:

   ```
   SyncProtocol → SyncCommandHandler → SalesCheckout
                → CheckoutQuoteProvider (DatabasePricingManager) → SyncProtocol
   ```

   `scoped()` caches only after construction completes, so re-entering
   resolution mid-construction recurses without limit. Catalogue, Inventory,
   Pricing and Sync are affected; the other eight modules pass (83 tests, 477
   assertions). **Left unfixed deliberately** — breaking the cycle is a design
   decision about whether pricing should publish sync changes directly or emit
   an event, and that belongs in its own ADR.

### Accepted costs

- Migrations are run explicitly with owner credentials via `compose run`, not
  automatically on boot. `config:cache` bakes credentials at container start,
  so `compose exec -e DB_USERNAME=…` does **not** override them — a one-off
  container is required.
- The environment is one shared basic-auth credential, not per-user access.
- **There are no database backups yet.** `api-postgres-data` and `api-storage`
  are named volumes, so the data survives restarts and redeploys, but nothing
  copies it off the host.

### As deployed

`pos.niuautomations.com`, certificate valid to 2026-11-11, HTTP redirected to
HTTPS, basic auth in front of everything except `/up`. Verified: unauthenticated
request 401, authenticated 200, `/.env` 403, `connect.niuautomations.com`
unaffected.

Two decisions changed between the design and the deployment:

- **`storage/` is a named volume, not tmpfs.** `FILESYSTEM_DISK=local` writes
  uploads there and losing them on restart would be data loss rather than a
  cleared cache. Only `bootstrap/cache` remains tmpfs, which is correct — the
  entrypoint rebuilds it on every start.
- **The hostname carries no `-test` suffix.** Renaming later means reissuing
  the certificate and updating `APP_URL` and every integration, so the name it
  will keep was chosen up front.

A `.dockerignore` was also added at the repository root, and it is load-bearing
rather than an optimisation: without it `bootstrap/cache/packages.php` from a
dev-mode `composer install` is copied into the image, and `package:discover`
then aborts the build with `Class "Laravel\Pail\PailServiceProvider" not found`
— a message that names the package rather than the stale file. It also stops a
local `.env` being baked into an image layer.

## Open questions

- Whether the container image should also serve `apps/web`, or whether that
  gets its own image and hostname.
- Whether the test environment should track `main` automatically once CI
  exists, and if so what stops a red build from deploying.
