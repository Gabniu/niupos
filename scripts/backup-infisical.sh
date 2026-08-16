#!/usr/bin/env bash
set -Eeuo pipefail

# Create encrypted, operator-managed backups of the Infisical database and
# encryption configuration. The recipient is public; the age identity must be
# kept outside the server and never committed to the repository.
ROOT_DIR="${NOVA_POS_ROOT:-/opt/nova-pos}"
INFRA_DIR="$ROOT_DIR/infra"
OUTPUT_DIR="${1:?Usage: AGE_RECIPIENT=age1... bash scripts/backup-infisical.sh /secure/backup-dir}"
AGE_RECIPIENT="${AGE_RECIPIENT:?Set AGE_RECIPIENT to the offline backup recipient before running}"

command -v age >/dev/null 2>&1 || { echo "age is required; refusing to create an unencrypted backup" >&2; exit 1; }
[[ -f "$INFRA_DIR/.env.infisical" ]] || { echo "Infisical environment file is missing" >&2; exit 1; }

umask 077
mkdir -p "$OUTPUT_DIR"
chmod 700 "$OUTPUT_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

docker compose --env-file "$INFRA_DIR/.env.infisical" -f "$INFRA_DIR/compose-infisical.yaml" \
  exec -T db sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump --no-owner --no-privileges -U infisical_owner -d infisical' \
  > "$work_dir/infisical.sql"

age -r "$AGE_RECIPIENT" -o "$OUTPUT_DIR/infisical-$stamp.sql.age" "$work_dir/infisical.sql"
age -r "$AGE_RECIPIENT" -o "$OUTPUT_DIR/infisical-$stamp.env.age" "$INFRA_DIR/.env.infisical"
sha256sum "$OUTPUT_DIR/infisical-$stamp.sql.age" "$OUTPUT_DIR/infisical-$stamp.env.age" > "$OUTPUT_DIR/infisical-$stamp.sha256"
chmod 600 "$OUTPUT_DIR"/infisical-"$stamp".*
echo "Encrypted Infisical backup created: $OUTPUT_DIR/infisical-$stamp.*"
