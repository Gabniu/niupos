#!/usr/bin/env bash
set -Eeuo pipefail

# Interactive, secret-safe cutover helper. Values are read directly on the
# server and never appear in command history, logs, or process arguments.
ENV_FILE="${AUTH_ENV_FILE:-/opt/nova-pos/apps/auth/.env.production}"
[[ -f "$ENV_FILE" ]] || { echo "Auth environment file not found: $ENV_FILE" >&2; exit 1; }
[[ -t 0 ]] || { echo "Run this script interactively over SSH; refusing non-interactive input." >&2; exit 1; }

read -r -p "Infisical project ID: " project_id
read -r -p "Infisical Universal Auth client ID: " client_id
read -r -s -p "Infisical Universal Auth client secret: " client_secret
printf '\n'

[[ "$project_id" =~ ^[A-Za-z0-9-]{20,100}$ ]] || { echo "Invalid project ID." >&2; exit 1; }
[[ "$client_id" =~ ^[A-Za-z0-9-]{20,100}$ ]] || { echo "Invalid client ID." >&2; exit 1; }
[[ -n "$client_secret" && ${#client_secret} -le 16384 ]] || { echo "Invalid client secret." >&2; exit 1; }

umask 077
temporary="$(mktemp "${ENV_FILE}.XXXXXX")"
trap 'rm -f "$temporary"' EXIT

awk '!/^(AUTH_SECRET_STORE|AUTH_SECRET_STORE_WRITE_ENABLED|INFISICAL_API_URL|INFISICAL_PROJECT_ID|INFISICAL_ENVIRONMENT|INFISICAL_SECRET_PATH|INFISICAL_CLIENT_ID|INFISICAL_CLIENT_SECRET|INFISICAL_ORG_SLUG)=/' "$ENV_FILE" > "$temporary"
cat >> "$temporary" <<EOF

AUTH_SECRET_STORE=infisical
AUTH_SECRET_STORE_WRITE_ENABLED=false
INFISICAL_API_URL=http://backend:8080
INFISICAL_PROJECT_ID=$project_id
INFISICAL_ENVIRONMENT=prod
INFISICAL_SECRET_PATH=/nova-auth
INFISICAL_CLIENT_ID=$client_id
INFISICAL_CLIENT_SECRET=$client_secret
EOF

chmod 600 "$temporary"
mv "$temporary" "$ENV_FILE"
trap - EXIT
unset project_id client_id client_secret
echo "Auth Infisical configuration saved with secret writes disabled. Restart Auth only after reviewing the configuration-safe health check."
