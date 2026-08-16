---
id: ADR-0050
title: NOVA Auth Container Healthcheck Listener
status: accepted
date: 2026-08-13
requirements:
  - REQ-DEPLOY-AUTH-HEALTH-001
tests:
  - infra/compose-auth.yaml
risks:
  - RISK-DEPLOY-AUTH-HEALTH-001
modules:
  - MOD-AUTH
related:
  - "[[ADR-0037 Shared Better Auth Identity Provider]]"
  - "[[ADR-0048 Containerized Public API Deployment]]"
---

# ADR-0050 — NOVA Auth Container Healthcheck Listener

## Decision

Probe NOVA Auth through the container hostname on port `3004`, rather than
`127.0.0.1`. The production Next server listens on the container interface
(`172.27.0.x:3004`), so a loopback probe reports `unhealthy` while the internal
and public `/sign-in` endpoints return 200.

The healthcheck remains local to the container and does not expose a new host
port or alter reverse-proxy routing.

## Verification and rollout

`docker compose -f infra/compose-auth.yaml config --quiet` must pass. After
copying the updated compose file to the server, recreate only the auth service:

```bash
cd /opt/nova-pos/infra
docker compose -f compose-auth.yaml up -d --no-deps --force-recreate auth
docker inspect --format='{{.State.Health.Status}}' nova-auth-auth-1
```

The expected result is `healthy`; `/sign-in` must still return HTTP 200 through
both the internal listener and `https://novaauth.niuautomations.com/sign-in`.
