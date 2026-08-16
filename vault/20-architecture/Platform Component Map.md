---
id: ARCH-PLATFORM-0001
type: architecture
status: draft
owners:
  - architecture
adrs:
  - ADR-0001
tests:
  - TEST-STRATEGY-0001
---

# Platform Component Map

## Runtime flow

```text
Next.js PWA ─┐
             ├─ HTTPS/versioned API ─> Laravel API ─> PostgreSQL
Flutter ─────┘                            │               │
                                         │               └─ outbox
                                         ├─ Redis             │
                                         │                    v
                                         └──────────────> RabbitMQ
                                                              │
                                  ┌───────────────────────────┼──────────────────────┐
                                  v                           v                      v
                           Search projector             Report projector      Integration workers
                                  │                           │                      │
                                  v                           v                      v
                           Elasticsearch                PostgreSQL views       external providers
```

## Consistency classes

| Class | Examples | Required behavior |
|---|---|---|
| Transactional | Sale finalization, payments, refunds, stock movements, shift close | PostgreSQL commit or complete rollback |
| Monotonic asynchronous | Search projection, notifications, analytics enrichment | Versioned event, idempotent consumer, retry and replay |
| Ephemeral | Cache, presence, rate limits, short-lived locks | May disappear; authoritative fallback exists |
| Offline replicated | Catalogue projection, pending sales, device outbox | Local transaction, idempotent server merge, visible reconciliation |

## Failure containment

| Failed component | Checkout behavior | Recovery requirement |
|---|---|---|
| Redis | Continue using PostgreSQL/local repository with reduced rate/cache performance where safe | Rewarm; no business-data recovery |
| RabbitMQ | Authoritative transaction commits with outbox pending; async side effects lag | Relay retries; alert on oldest outbox age |
| Elasticsearch | Checkout uses local catalogue or bounded PostgreSQL lookup; advanced search degrades | Replay projection and atomic alias cutover |
| PostgreSQL | Connected writes stop; already-provisioned devices may transact offline within policy | Restore/failover; synchronize queued transactions |
| API/network | Device enters explicit offline state | Durable local outbox and health feedback |
| Local database | Stop offline completion unless recoverable; do not pretend data was saved | Export diagnostics, restore projection, preserve recoverable outbox |
| Payment provider | Cash remains available; digital payment remains pending/failed per provider semantics | Reconciliation and webhook replay |

## Mandatory platform metadata

Every request, command, event, log, and trace carries applicable identifiers:

- correlation ID
- causation ID
- idempotency key
- tenant/company ID
- branch ID
- register/device ID
- user/actor ID
- client version and sync protocol version
- occurred-at and recorded-at timestamps

Sensitive values must be classified and redacted before logging.

