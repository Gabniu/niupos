#!/usr/bin/env bash
set -Eeuo pipefail

# Restore into the explicitly named Infisical stack. This intentionally refuses
# to run without --confirm because it overwrites the current database.
ROOT_DIR="${NOVA_POS_ROOT:-/opt/nova-pos}"
INFRA_DIR="$ROOT_DIR/infra"
BACKUP_PREFIX="${1:?Usage: AGE_IDENTITY=... bash scripts/restore-infisical.sh /secure/backup-dir/infisical-<stamp> --confirm}"
CONFIRM="${2:-}"
AGE_IDENTITY="${AGE_IDENTITY:?Set AGE_IDENTITY to an offline age identity file}"
[[ "$CONFIRM" == "--confirm" ]] || { echo "Refusing restore without --confirm" >&2; exit 1; }
command -v age >/dev/null 2>&1 || { echo "age is required" >&2; exit 1; }
[[ -f "$BACKUP_PREFIX.sql.age" && -f "$BACKUP_PREFIX.env.age" ]] || { echo "Backup pair not found" >&2; exit 1; }

umask 077
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT
age --decrypt --identity "$AGE_IDENTITY" -o "$work_dir/infisical.sql" "$BACKUP_PREFIX.sql.age"
age --decrypt --identity "$AGE_IDENTITY" -o "$work_dir/.env.infisical" "$BACKUP_PREFIX.env.age"
chmod 600 "$work_dir/.env.infisical"
cmp -s "$work_dir/.env.infisical" "$INFRA_DIR/.env.infisical" || cp "$work_dir/.env.infisical" "$INFRA_DIR/.env.infisical"

docker compose --env-file "$INFRA_DIR/.env.infisical" -f "$INFRA_DIR/compose-infisical.yaml" up -d db redis backend
docker compose --env-file "$INFRA_DIR/.env.infisical" -f "$INFRA_DIR/compose-infisical.yaml" \
  exec -T db sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" psql -v ON_ERROR_STOP=1 -U infisical_owner -d infisical -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"'
docker compose --env-file "$INFRA_DIR/.env.infisical" -f "$INFRA_DIR/compose-infisical.yaml" \
  exec -T db sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" psql -v ON_ERROR_STOP=1 -U infisical_owner -d infisical' < "$work_dir/infisical.sql"
docker compose --env-file "$INFRA_DIR/.env.infisical" -f "$INFRA_DIR/compose-infisical.yaml" restart backend
echo "Infisical database restore completed; verify /api/status and an authenticated secret read before resuming writes."
