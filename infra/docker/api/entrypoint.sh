#!/bin/sh
# Startup checks and boot sequence for the NOVA POS API container.
#
# Everything here runs before supervisord takes over. The checks fail CLOSED --
# the container refuses to start rather than serving in a state that leaks
# credentials or runs against an unmigrated database. A container that will not
# start is an obvious problem; one that starts wrong is found by someone else.

set -eu

fail() {
    echo "[entrypoint] FATAL: $1" >&2
    exit 1
}

note() {
    echo "[entrypoint] $1"
}

# ---------------------------------------------------------------------------
# 1. APP_KEY must exist.
# ---------------------------------------------------------------------------
# Laravel derives session cookie and encrypted-column keys from it. Without one
# the framework throws on the first encrypted operation, which in practice
# means the app appears to boot and then 500s on login.
#
# It is deliberately NOT generated here: a key minted at container start
# changes on every restart, silently invalidating every existing session and
# making any encrypted column unreadable. It belongs in the environment file,
# generated once.
[ -n "${APP_KEY:-}" ] || fail "APP_KEY is not set. Generate one with
    docker compose -f infra/compose-pos.yaml run --rm api php artisan key:generate --show
  and put it in the environment file. Do not let it change between restarts --
  existing sessions and encrypted columns are derived from it."

# ---------------------------------------------------------------------------
# 2. Debug mode is refused on anything that is not local.
# ---------------------------------------------------------------------------
# THIS IS THE CHECK THAT MATTERS FOR A PUBLIC TEST ENVIRONMENT.
#
# With APP_DEBUG=true, an unhandled exception renders Laravel's error page,
# and that page includes the resolved environment: DB_PASSWORD, APP_KEY, mail
# credentials, any API token in the environment. Anyone able to trigger an
# error -- a malformed request will usually do -- reads them. It is one of the
# most reliably exploited misconfigurations in the PHP ecosystem.
#
# php.ini already sets display_errors=Off, which blocks the raw PHP path, but
# Laravel's debug page is rendered by the framework and is not covered by it.
# Hence a second, explicit gate here.
#
# The escape hatch exists because debugging a test environment is a real need.
# It requires setting a second variable whose name states the consequence, so
# it cannot happen by copying a development env file by accident.
if [ "${APP_DEBUG:-false}" = "true" ] && [ "${APP_ENV:-production}" != "local" ]; then
    if [ "${NOVA_ALLOW_PUBLIC_DEBUG:-false}" != "true" ]; then
        fail "APP_DEBUG=true with APP_ENV=${APP_ENV:-production}.
  Laravel's debug error page prints the whole environment -- database password,
  APP_KEY, mail credentials -- to anyone who can trigger an exception. On a host
  with a public hostname that is a credential disclosure.
  Set APP_DEBUG=false, or set NOVA_ALLOW_PUBLIC_DEBUG=true to accept the risk
  deliberately."
    fi
    note "WARNING: APP_DEBUG=true and NOVA_ALLOW_PUBLIC_DEBUG=true."
    note "WARNING: error pages will expose environment variables including"
    note "WARNING: DB_PASSWORD and APP_KEY. Do not leave this on."
fi

# ---------------------------------------------------------------------------
# 3. Wait for PostgreSQL.
# ---------------------------------------------------------------------------
# compose's depends_on with a healthcheck already orders startup, but this
# container may also be run standalone, and a database restart should not
# require restarting the app.
if [ -n "${DB_HOST:-}" ]; then
    note "waiting for postgres at ${DB_HOST}:${DB_PORT:-5432}"
    attempt=0
    until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -q; do
        attempt=$((attempt + 1))
        [ "$attempt" -lt 60 ] || fail "postgres at ${DB_HOST}:${DB_PORT:-5432} did not become ready within 60s"
        sleep 1
    done
    note "postgres is ready"
fi

# ---------------------------------------------------------------------------
# 4. Migrations, only when explicitly asked for.
# ---------------------------------------------------------------------------
# Default off. Migrating automatically on boot means a container restart can
# alter the schema unattended, and with more than one replica two of them race.
# A test environment is the reasonable place to switch it on, so it is one
# variable -- but it is a decision, not a default.
if [ "${NOVA_RUN_MIGRATIONS:-false}" = "true" ]; then
    note "running migrations"
    php artisan migrate --force --no-interaction || fail "migrations failed"
fi

# ---------------------------------------------------------------------------
# 5. Recreate Laravel's writable directories.
# ---------------------------------------------------------------------------
# These are created in the Dockerfile, and they are gone by the time this runs.
#
# The image is deployed with storage/framework, storage/logs and
# bootstrap/cache as tmpfs (see infra/compose-pos.yaml). A tmpfs mount REPLACES
# the directory it is mounted over with an empty filesystem, so every
# subdirectory baked into the image underneath those paths disappears at
# container start.
#
# Laravel does not create them itself and does not fail clearly when they are
# missing: `view:cache` aborts with "View path not found", which reads like a
# configuration problem with the view paths rather than a missing directory.
# Found exactly that way on the first run of this image.
mkdir -p storage/app/private \
         storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache

# Targets exactly the tmpfs paths. An earlier version ran `chown -R` over the
# whole application directory, which recursed into read-only image layers,
# failed, and killed the entrypoint under `set -e` -- a restart loop whose logs
# blamed the storage permissions rather than the recursion.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || {
    echo "[entrypoint] FATAL: could not take ownership of storage/ or bootstrap/cache." >&2
    echo "[entrypoint] Both must be writable mounts -- check the tmpfs entries in compose." >&2
    exit 1
}

# nginx's working directories, for the same reason: /var/lib/nginx and
# /var/log/nginx are tmpfs, so whatever the image created underneath them is
# gone.
#
# nginx opens its compiled-in default error log path before it parses the
# configuration, so `error_log /dev/stderr` in nginx.conf does not save it --
# it aborts with "could not open error log file" and supervisord restarts it in
# a loop while php-fpm stays up, which presents as a container that is running
# but answers nothing.
mkdir -p /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/fastcgi \
         /var/lib/nginx/logs \
         /var/log/nginx \
         /run/nginx
chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx

# ---------------------------------------------------------------------------
# 6. Warm the framework caches.
# ---------------------------------------------------------------------------
# config:cache also has a side effect worth knowing: once cached, env() returns
# null outside config files. That is correct Laravel practice, and it is better
# to surface it here than to have it appear only in production.
#
# Cleared first because the image may carry caches baked at build time from a
# different environment.
note "warming caches"
php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

note "starting supervisord"
exec "$@"
