---
id: REV-0062
type: graph-review
status: open
date: 2026-08-16
plan: PLAN-NIU-ONBOARD-001
adr: ADR-0062
---

# Adaptive Onboarding Graph Review

## Review evidence

The focused graph query connected the existing `Tenant`, `MOD-CUSTOMERS`,
`MOD-NOTIFY`, `IdentityServiceProvider`, and client/workspace nodes. The latest
code-only refresh contains 3,194 nodes, 5,900 links, 29 hyperedges, and 394
communities. A structural health check found no dangling link endpoints and no
duplicate node IDs. Non-code semantic refresh remains blocked until an approved
Graphify LLM key is available; community labels also need an LLM refresh after
reclustering.

## Findings

- The existing graph already has strong tenancy, identity, customer, notification,
  client, and secret-provisioning concepts that the new plan composes.
- The first onboarding draft/channel-selection slice and tenant
  conversion are now represented by the onboarding provider, draft manager,
  channel enum, HTTP controller, migrations, web wizard, tenant creator,
  owner-membership, location, and register boundary nodes. The Channels module
  now adds tenant/RLS-scoped public registration metadata, approval transition,
  permission migration, API routes, web/mobile setup UI, and focused tests. The
  Onboarding module now adds durable dry-run provisioning runs/actions,
  idempotent previews, owner approval transition, audit events, and focused
  manager tests, plus a tenant-scoped setup timeline and a fail-closed worker
  boundary. Internal workspace-preferences and notification-preferences
  initialization actions are idempotent and safe; external adapters remain
  blocked. POS-only runs now complete when all actions are internally verified,
  while web/mobile runs stop at their external action boundary. The wizard now
  invokes the worker and renders that outcome rather than leaving queued work
  ambiguous. Setup events now fan out to tenant-scoped in-app notifications;
  the wizard renders unread/read state and notification intent controls, while
  external delivery remains unconfigured and fail-closed.
- The worker now consults an explicit executor registry and exposes capability
  metadata; the wizard renders that state, and no publication or mobile-release
  executor is registered.
- External notification preferences now produce tenant-scoped blocked delivery
  intents for auditability. The owner can inspect those intents in the wizard and
  read-only API; sending remains an explicit action with a retry limit.
- External provisioning and notification adapter contracts now carry
  tenant-scoped request/result DTOs and evidence requirements. Provisioning
  executors remain fail-closed; the Resend adapter is registered but disabled by
  default and can only run through the protected dispatcher.
- NIU Auth already has a server-side Resend integration for identity email.
  Onboarding uses a separate server configuration and never reuses the Auth
  control-plane credential implicitly.
- The deployment host has Docker and PHP 8.5.3 inside the API container; the
  new delivery files pass `php -l` there. The currently running image predates
  this change, so database-backed focused tests and a real delivery remain
  deployment-gated.
- Community labels changed during reclustering; labels should be refreshed when
  a semantic Graphify backend is available.

## Open actions

1. Verify the new explicit Resend delivery action in the PHP/PostgreSQL
   environment, including idempotency, retries, and persisted provider
   evidence. Add the remaining verified external executors and adapters
   afterwards; the
   `web_mobile` second-channel step is now server-derived and the client selects
   mobile on its second pass. Queued actions must remain blocked from external
   publication until adapter evidence and rollback exist.
2. Run a full non-code semantic Graphify update after a permitted LLM key is
   available; do not hand-edit generated graph artifacts.
3. Re-run this review after external adapters and the full wizard branch are
   implemented, checking for cross-tenant or identity/customer boundary edges.
