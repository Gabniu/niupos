# NOVA POS — deployment status and runbook

**Last verified: 2026-08-17.** Everything below was checked against the running
server, not written from intent. Where something is not done, it says so.

---

## What is live right now

| | URL | Status |
|---|---|---|
| **POS web + API** | https://pos.niuautomations.com | live, public application login |
| **NIU Auth / NIU Identity** | https://novaauth.niuautomations.com | live, public (no basic auth) |
| Booking | — | not deployed, not a git repository yet |

Host: `169.58.155.243`, Ubuntu 24.04, 8 vCPU, 23GB RAM. **Shared** — it also runs
the Voice Platform (`connect.niuautomations.com`), an unrelated product. Treat
RAM and ports as a shared budget.

### Containers

```
nova-api-web-1        127.0.0.1:3011   Next.js front end
nova-api-api-1        127.0.0.1:3010   Laravel API (nginx + php-fpm)
nova-api-postgres-1   no host port     POS database
nova-auth-auth-1      127.0.0.1:3004   NOVA Identity (Next.js)
nova-auth-postgres-1  no host port     identity database
```

**Nothing binds a public interface.** Host nginx on 443 is the only public
listener, with one vhost per application in `/etc/nginx/sites-available/`.

---

## Signing in

### NOVA Identity — first administrator

The first administrator exists. The one-time `AUTH_BOOTSTRAP_TOKEN` has been
removed from `apps/auth/.env.production`, and the auth container was recreated.
The bootstrap API now returns 404 while sign-in and OIDC discovery remain
available.

The endpoint is properly gated server-side: token must be ≥32 characters, exact
length, compared with `timingSafeEqual`, and it returns **404** rather than 403
so it does not confirm the route exists. Verified with live negative tests.

### POS — no account exists yet

POS authenticates against **its own** `users` table with email + password
(`AuthController::login`, `Hash::check`). The `identity_issuer` /
`identity_subject` columns and staged OIDC consumer boundaries exist for
federating to NOVA Identity, but local sessions remain authoritative until the
integrated callback cutover is accepted — see "Not done yet" below.

To create the first owner, run this on the server. The command asks for the
password twice without echoing it, so run it yourself rather than pasting a
password into a chat or a script:

```bash
cd /opt/nova-pos/infra

# 1. Create the user. Name and email are prompted; the password is hidden.
docker compose -f compose-pos.yaml run --rm --no-deps -it \
  -e DB_USERNAME=nova_owner -e DB_PASSWORD="$(grep NOVA_OWNER_DB_PASSWORD .env | cut -d= -f2)" \
  api php artisan nova:user:create
```

Copy the `User UUID` printed at the end; you will use it in the owner step.

```bash
# 2. Make that user the owner of the tenant.
#    Tenant UUID: fd2ce975-0500-4444-b9cb-7da0e8c721e7  (name: NiuAutomations)
docker compose -f compose-pos.yaml run --rm --no-deps \
  -e DB_USERNAME=nova_owner -e DB_PASSWORD="$(grep NOVA_OWNER_DB_PASSWORD .env | cut -d= -f2)" \
  api php artisan nova:tenant:bootstrap-owner \
    fd2ce975-0500-4444-b9cb-7da0e8c721e7 <user-uuid> --operator="initial setup" --force
```

Note the `DB_USERNAME` override: the API normally connects as `nova_app`, which
deliberately cannot write outside its granted tables.

### POS public access

The temporary nginx HTTP Basic Auth gate was removed on 2026-08-15 at the
operator's request. POS now presents its own application login at `/`; the API
routes still require an authenticated session and tenant admission. The `/up`
health endpoint remains intentionally unauthenticated and returns only health
status.

The gate provided a second perimeter while POS authentication and the shared
NIU Identity cutover were still under construction. Removing it means internet
scanners can now reach the login and public health surfaces, so rate limits,
secure cookies, trusted origins, forwarded-header handling, safe logs,
monitoring, and the eventual NIU Identity federation cutover must be verified
before treating POS as production-ready. A backup of the previous nginx site
configuration remains on the server at
`/etc/nginx/sites-available/novapos.bak-20260815191412`.

---

## Deploying a change

There is **no CI/CD yet** — deploys are manual (see "Not done yet").

```bash
# 1. Get the code onto the server (from a machine with the repo)
tar -czf - --exclude=node_modules --exclude=vendor --exclude=.next infra apps packages scripts \
  | ssh vp-server "tar -xzf - -C /opt/nova-pos"

# 2. Rebuild and restart
ssh vp-server "cd /opt/nova-pos/infra && docker compose -f compose-pos.yaml up -d --build"

# 3. Migrations, if the change includes any — as the OWNER role, in a ONE-OFF container
ssh vp-server "cd /opt/nova-pos/infra && docker compose -f compose-pos.yaml run --rm \
  -e DB_USERNAME=nova_owner -e DB_PASSWORD=\"\$(grep NOVA_OWNER_DB_PASSWORD .env | cut -d= -f2)\" \
  -T api php artisan migrate --force"
```

**Step 3 must use `run`, not `exec`.** The entrypoint runs `config:cache` at
container start, which bakes the database credentials into a cached config file;
after that `env()` returns null outside config files, so `exec -e DB_USERNAME=…`
is silently ignored and the migration runs as `nova_app`, which cannot create
tables. A one-off container re-runs `config:cache` with the overridden values.

### Verify after deploying

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://pos.niuautomations.com/up      # 200
curl -su gabriel:PASSWORD -o /dev/null -w '%{http_code}\n' https://pos.niuautomations.com/  # 200
curl -su gabriel:PASSWORD -o /dev/null -w '%{http_code}\n' https://pos.niuautomations.com/.env  # 403
```

### 2026-08-15 web workspace deployment evidence

The NIU POS web workspace was rebuilt from the verified local source and
restarted with the existing `compose-pos.yaml` stack. No migration was needed;
the API and Auth data/services were left unchanged. The server reported
`nova-api-web-1` and `nova-api-api-1` healthy, and
`https://pos.niuautomations.com/up` returned HTTP 200. The web container log
reported Next.js ready on its existing loopback port `3011`.

The same deployment now includes the black/charcoal NIU POS brand/action palette and
the optional “Continue with NIU Auth” login path. The existing local
email/password login is unchanged. SSO remains fail-closed until the POS OAuth
client is registered, server-only federation settings are configured, and each
POS user is explicitly linked to an NIU Auth subject.

---

The login layer hardening is also deployed: the right rail is an opaque isolated
content layer, the document root is forced to the light POS scheme, and the
service worker refreshes a tab after a replacement worker activates. If a tab was
already controlled by the prior shell, reload once with `Ctrl+Shift+R` (or close
and reopen the tab) so the replacement worker can claim it.

## Things that will bite you

Each of these cost real time on the first deployment. They are recorded so they
cost nobody a second one.

**Migrations must run in dependency order.** Every module originally used the
timestamp `2026_08_08_000001`, so Laravel's filename ordering resolved
cross-module foreign keys alphabetically — Payments before Sales, Inventory
before the Tenancy migration that creates `warehouses`. They are now renumbered
`000001`–`000018`. **Do not give a new migration a timestamp that sorts before
the tables it references.**

**The test suite runs on SQLite; production is PostgreSQL.** SQLite resolves
foreign keys lazily and accepts a reference to a table that does not exist yet.
PostgreSQL rejects it immediately. This is why the ordering bug above was
invisible for so long. **A green test suite does not prove the migrations run.**

**`CREATE FUNCTION` must be `CREATE OR REPLACE`.** `migrate:fresh` drops tables
but not functions, and does not call `down()`. All seven trigger functions were
fixed; keep new ones idempotent.

**Next.js freezes `rewrites()` at build time.** `apps/web/next.config.ts` has an
API rewrite whose destination is baked into the image by `next build`. Setting
`NOVA_API_ORIGIN` at runtime does nothing. nginx routes `/api/` instead.

**`next build` runs the production config check.** It sets `NODE_ENV=production`,
which trips `assertProductionAuthConfiguration()` in the auth app during page
data collection. `infra/docker/auth/Dockerfile` supplies inert placeholders;
they are safe because none is a `NEXT_PUBLIC_` variable, so real values are read
at runtime. Verified on the running container.

**`.dockerignore` is load-bearing.** Without it, a `bootstrap/cache/packages.php`
from a dev-mode `composer install` is copied into the `--no-dev` image and the
build fails with `Class "Laravel\Pail\PailServiceProvider" not found` — a message
that names the package rather than the stale file. It also stops a local `.env`
being baked into an image layer.

**`certbot --nginx` cannot bootstrap a new vhost.** The vhost names a certificate
that does not exist, nginx refuses to load it, certbot's own `nginx -t` fails,
and it reports "the nginx plugin is not working". Use a temporary HTTP-only
server block plus `certbot certonly --webroot`. Documented at the top of both
vhost files.

---

## Backups

Nightly at 02:30 EAT, kept 14 days, in `/var/backups/postgres/<container>/`.
Both POS databases are included automatically — the script discovers PostgreSQL
containers by image rather than naming them.

```bash
journalctl -u db-backup --since yesterday    # did last night work?
```

Restore procedure: `/opt/voice-platform/runbooks/database-restore.md`. It has
been exercised, not just written (49/49 tables, 25 migration rows).

**Known limit:** the dumps are on the same disk as the databases. They protect
against a bad migration or a mistaken delete, **not against losing the server.**
`BACKUP_REMOTE_TARGET` is the hook for an off-host copy and is unset.

---

## Not done yet

Honest list. Item 2 records a resolved defect for deployment verification;
the remaining items are still open and are not blocked by anything except a
decision or planned work.

1. **POS federation is implemented but not activated in production.** POS keeps
   its local email/password login and now offers an optional NIU Auth SSO path.
   The safe federation slices include strict OIDC discovery, S256 PKCE
   transactions, token transport, Ed25519 JWKS verification, browser callback,
   and exact local subject/active-tenant admission. Activation still requires
   registering the exact production client/callback, configuring server-only
   credentials, explicitly linking existing users, and completing provider
   revocation, dual-session, and rollback evidence.

2. **The former sync construction cycle has been removed in source.** Catalogue
   and Pricing now publish through a deferred `SyncChangePublisher` port,
   preserving the existing mutation payloads and transaction boundaries.
   Containerized Laravel verification remains required before deployment
   because Docker is unavailable on this host.

The POS federation verifier now has an audited JOSE dependency candidate:
`lcobucci/jwt` 5.6.0 passed Composer audit on the server. It is not enabled yet;
the Ed25519 JWKS adapter, claim tests, local subject mapping, tenant admission,
and callback transaction are implemented as fail-closed boundaries. Federation
still cannot issue a POS session in deployment because it remains disabled by
default; enabling it requires token exchange, admission, audit evidence, and
rollback behavior to be accepted together. The mapper requires
an exact `(issuer, subject)` link and active local tenant membership; it never
auto-links by email or creates a user.

3. **No CI/CD.** Deploys are the manual tar-and-rebuild above. The repository
   has no commits and no remote yet.

4. **`apps/mobile` (Flutter) is not deployed** and has no deployment story.

5. **No off-host backups** (see above).

6. **Elasticsearch and RabbitMQ are in `infra/compose.yaml` but unused** — no
   client library in `composer.json`, no code references. They are kept for
   planned POS work and are **not running on the server**. If you start them
   there, note Elasticsearch alone wants ~1.5GB on a shared 23GB box.

### NOVA Auth healthcheck correction (2026-08-13)

The live Auth container served `/sign-in` with HTTP 200 internally and publicly,
but Docker reported `unhealthy` because its probe targeted loopback while Next
was listening on the container interface. `infra/compose-auth.yaml` now probes
the container hostname. Roll out only after reviewing the change:

```bash
cd /opt/nova-pos/infra
docker compose -f compose-auth.yaml up -d --no-deps --force-recreate auth
docker inspect --format='{{.State.Health.Status}}' nova-auth-auth-1
```

The repeatable public smoke test is `scripts/Test-AuthDeployment.ps1`. It
checks HTTP-to-HTTPS redirect, security headers, OIDC discovery, S256 PKCE
advertisement, and the disabled bootstrap endpoint without submitting a login
or exposing credentials.

On 2026-08-14, an isolated PostgreSQL/Auth stack passed bootstrap, password
sign-in, generic bad-credential denial, session retrieval, logout, post-logout
denial, TOTP enable/verification/disable, and an audited admin setting write
using the deployed Auth image. A second isolated run registered a temporary
confidential OAuth client, completed authorization-code + PKCE authorization,
consent, token exchange, and userinfo subject retrieval. Every temporary
network, schema-only database, user, client, token, and secret was removed.
Password recovery remains open until a secure mail provider/webhook is
configured and exercised end to end.

The Auth image now supports Resend without a code or database secret. To enable
it, set `AUTH_MAIL_PROVIDER=resend`, a verified `AUTH_MAIL_FROM`, and a freshly
rotated `AUTH_RESEND_API_KEY` in the deployment secret environment, then
recreate only the Auth container and exercise recovery in a disposable stack
before production use. Never paste the key into source, Graphify, or this
runbook.

### Planned self-hosted secret manager migration

The current production fallback remains available for rollback. The private
self-hosted Infisical stack is deployed on the server, bound only to
`127.0.0.1:3005`, and the Auth service now reads the available Resend secret
from its `prod`/`/nova-auth` scope. Google remains disabled until its optional
secret is configured. The remaining migration checks are staged:

1. Deploy Infisical on a private Docker network; do not publish its UI/API or
   database to the internet.
2. Create separate staging and production projects/environments and give Auth a
   machine identity limited to the Auth production path.
3. Copy only Google/Resend provider secrets into the production path and verify
   read, rotation, rollback, backup restore, and vault-outage behavior.
4. `AUTH_SECRET_STORE=infisical` is now enabled for the deployed Auth service
   while retaining the environment fallback procedure. Verify password sign-in,
   Google isolation, and email delivery after each provider change.
5. Keep `AUTH_SECRET_STORE_WRITE_ENABLED=false` until the implemented
   session-scoped MFA step-up route (`/api/control/secrets/step-up`) has been
   verified in production. Only then allow write-only replacement from the
   NOVA Auth admin settings page.

NOVA administrators do not log in to Infisical for normal configuration. The
NOVA Auth backend is the control plane; Infisical is private storage. Never put
an Infisical root token in `.env`, the browser, logs, Graphify, or audit rows.
Keep Infisical encryption/recovery material separate from its backups and test
restoration before removing the environment fallback.

For the vault backup/restore runbook, install `age` on the server and keep the
private age identity offline. `scripts/backup-infisical.sh` refuses to create an
unencrypted backup and encrypts both the Infisical PostgreSQL dump and the
`.env.infisical` encryption configuration. Verify the generated checksum and
perform a disposable restore at least once before enabling production writes:

```bash
AGE_RECIPIENT='age1your-offline-backup-recipient' \
  bash /opt/nova-pos/scripts/backup-infisical.sh /secure/backups/nova-infisical
AGE_IDENTITY='/secure/offline/infisical-backup-key.txt' \
  bash /opt/nova-pos/scripts/restore-infisical.sh \
  /secure/backups/nova-infisical/infisical-<timestamp> --confirm
```

Never place the age identity, decrypted dump, or decrypted Infisical
environment file in the repository, browser, Graphify corpus, or a ticket.

After the server secret is present, an authenticated Auth administrator may use
`/admin/settings` to set `delivery.provider` to `resend`, set the verified
`delivery.from` sender, and confirm `delivery.resendApiKey` is the reference
`AUTH_RESEND_API_KEY`. The frontend never accepts the raw key; restart the Auth
container after these restart-mode settings change, then run password-recovery
E2E and inspect the control-plane audit row.

### POS onboarding email delivery

POS onboarding uses a separate server-side Resend boundary. It does not reuse
the NIU Auth control-plane secret automatically. Keep a sending-scoped Resend
key in the POS deployment secret manager and set only these references in the
API environment:

```text
ONBOARDING_RESEND_ENABLED=true
RESEND_API_KEY=<secret-manager-injected value>
ONBOARDING_MAIL_FROM=NIU POS <verified-sender@your-domain.com>
ONBOARDING_DELIVERY_MAX_ATTEMPTS=3
```

Restart only the API after reviewing the environment. Confirm the authenticated
onboarding preferences response reports `externalDeliveryAvailable: true` and
that the delivery list is tenant-scoped. The wizard's `Send again` action is the
only current trigger; it requires the onboarding permission, email consent, a
verified recipient address, and an available retry. Leave the feature flag off
until this explicit test succeeds. Do not paste either Resend key into the
frontend, source, Graphify, or this runbook.

For a repeatable server check, run `scripts/Test-OnboardingDelivery.ps1` with
an authenticated browser cookie header. Without `-Send` it only reads the
tenant-scoped preference and delivery endpoints. Add `-Send -DeliveryId <uuid>`
only when intentionally testing one real delivery; the script never prints the
cookie or any provider secret.

The new onboarding delivery code is not present in the currently running image
until the normal POS release is deployed. After the release artifact is on the
server, run migrations as the owner role, then execute the focused suite inside
the API container before enabling `ONBOARDING_RESEND_ENABLED`:

```bash
docker compose -f infra/compose-pos.yaml up -d --build api web
docker compose -f infra/compose-pos.yaml exec \
  -e DB_USERNAME=nova_owner -e DB_PASSWORD=<owner-password> \
  api php artisan migrate --force
docker compose -f infra/compose-pos.yaml exec api \
  php artisan test --filter='OnboardingProvisioningManagerTest|ResendOnboardingNotificationDeliveryAdapterTest'
```

If the focused suite is green, run the read-only PowerShell check first. Only
then set the feature flag and perform one explicit `-Send` test.

### 2026-08-17 POS onboarding delivery deployment evidence

The POS API and web images were rebuilt on `vp-server` from the repository
release artifact and restarted with `compose-pos.yaml`. The owner-role
migration completed successfully, including the onboarding delivery-attempt
and provider-evidence columns. A pre-release backup was created at
`/opt/nova-pos/deploy-backups/pre-resend-20260816T220720Z.tar.gz` (SHA-256
`dc71e75b66bae30a5022ae2e2280afa1d0340704753f3089db0a38af5a1307d0`).

The isolated PHP 8.5.3/Composer 2.9.3 suite passed **177 tests / 929
assertions**. The live API container and PostgreSQL are healthy, the web
container is healthy, and `https://pos.niuautomations.com/up` returned HTTP
200. `ONBOARDING_RESEND_ENABLED` remains off; no real email was sent during
deployment. Enable it only after the authenticated read-only check and one
intentional delivery test described above.

---

## Related

- `vault/20-architecture/ADR-0048 Containerized Public API Deployment.md` — why
  the deployment is shaped this way, and the defects it uncovered
- `infra/compose-pos.yaml`, `infra/compose-auth.yaml` — the deployed stacks
- `infra/reverse-proxy/` — vhosts, including the two-step certbot procedure
- `apps/auth/README.md` — the identity service's own documentation
