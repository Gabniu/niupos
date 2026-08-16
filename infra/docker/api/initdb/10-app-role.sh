#!/bin/sh
# Creates the restricted runtime role for the NOVA POS API.
#
# Runs once, on first initialisation of an empty data directory. If the
# postgres volume already exists this file is ignored -- recreate the volume to
# re-run it.
#
# A shell script rather than plain .sql so the password comes from the
# environment. A .sql file in this directory cannot read environment variables,
# which would mean committing the runtime password to the repository.
#
# WHY A SECOND ROLE EXISTS AT ALL
# ---------------------------------------------------------------------------
# ADR-0003 makes PostgreSQL row-level security a mandatory tenant-isolation
# control, and states that database owners and roles holding BYPASSRLS are
# forbidden for ordinary application traffic.
#
# That is not a formality. **A table's owner bypasses that table's RLS policies
# by default.** If the application connects as the role that owns the tables,
# every policy is silently inert: queries return every tenant's rows, the
# application's own query scoping becomes the only thing separating tenants,
# and TEST-G1-POSTGRES-RLS-001 passes while proving nothing -- it would be
# exercising policies that were never enforced on that connection.
#
# There is no error and no warning when this is wrong. The only way to know is
# to connect as a role that cannot bypass, which is what this script provides.
#
# Two roles, two jobs:
#   nova_owner  owns the schema and runs migrations. Bypasses RLS -- correct,
#               because migrations must see and alter everything.
#   nova_app    the application's runtime identity. Owns nothing, so policies
#               apply to it. This is the connection the API uses.

set -eu

: "${NOVA_APP_DB_PASSWORD:?NOVA_APP_DB_PASSWORD must be set for the runtime role}"

# --set and :'variable' rather than string interpolation: psql quotes the value
# itself, so a password containing a quote cannot terminate the statement early
# and become executable SQL.
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     --set ON_ERROR_STOP=1 \
     --set app_password="$NOVA_APP_DB_PASSWORD" <<'SQL'

-- Deliberately NOSUPERUSER, NOCREATEDB, NOCREATEROLE, and never granted
-- BYPASSRLS. Any of those would defeat the point of the role.
CREATE ROLE nova_app WITH
    LOGIN
    NOSUPERUSER
    NOCREATEDB
    NOCREATEROLE
    NOINHERIT
    PASSWORD :'app_password';

GRANT CONNECT ON DATABASE nova TO nova_app;
GRANT USAGE ON SCHEMA public TO nova_app;

-- Covers tables that already exist. Migrations have not run at this point, so
-- this is usually a no-op; the default privileges below are what actually
-- reach the application's tables.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nova_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nova_app;

-- The important part: anything the migration role creates from here on is
-- automatically readable and writable by nova_app. Without it, every new
-- migration would need a matching GRANT, and the first request after a deploy
-- would fail with a permission error on the new table.
--
-- Note the absence of CREATE, DROP and ALTER. The runtime role cannot change
-- the schema, so an injection flaw cannot drop a table.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO nova_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO nova_app;

SQL

echo "[initdb] created nova_app (NOSUPERUSER, no BYPASSRLS, owns nothing)"

# Transaction-local tenant context (ADR-0003 compares tenant_id against
# app.tenant_id) is applied with SET LOCAL, which requires no special
# privilege -- recorded here so it is clear nothing further was omitted.
