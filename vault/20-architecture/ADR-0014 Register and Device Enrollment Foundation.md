---
id: ADR-0014
type: architecture-decision
status: accepted
date: 2026-08-08
owners:
  - identity
  - checkout
requirements:
  - REQ-G2-REGISTER-001
  - REQ-G2-DEVICE-ENROLL-001
modules:
  - MOD-REGISTER
tests:
  - TEST-G2-REGISTER-TENANCY-001
  - TEST-G2-DEVICE-ENROLL-001
risks:
  - RISK-G2-DEVICE-TOKEN-001
  - RISK-G2-DEVICE-CROSS-TENANT-001
---

# ADR-0014 Register and Device Enrollment Foundation

## Context

Checkout, shifts, offline commands, and hardware integration need a stable identity for the physical or virtual register and for each enrolled device. A device enrollment credential is particularly sensitive: retaining its plaintext would turn a database disclosure into an immediate device impersonation path. Tenant identity also must be present in every parent reference so an application defect cannot attach a register or device to another tenant's hierarchy.

## Decision

- `MOD-REGISTER` owns tenant-scoped registers and devices.
- A register belongs to exactly one branch through `(tenant_id, branch_id)`. A device belongs to exactly one register through `(tenant_id, register_id)`. Composite foreign keys enforce both relationships.
- Every tenant table enables and forces PostgreSQL row-level security using the request-scoped `app.tenant_id` setting.
- A device receives an immutable UUID public identifier distinct from its database identifier, a display name, lifecycle status, enrollment expiry and consumption timestamps, and an optional last-seen timestamp.
- Enrollment issuance returns a one-time token containing 256 bits of randomness. Only its SHA-256 digest is stored. Consumption locks the matching tenant row in a transaction, rejects missing, expired, or consumed credentials, erases the digest, records consumption, and activates the device.
- Active-device resolution requires the current `TenantContext`, public identifier, active status, and completed enrollment.

## Traceability and acceptance evidence

| Requirement | Acceptance criteria | Test evidence | Risk controlled |
|---|---|---|---|
| REQ-G2-REGISTER-001 — Registers are owned by an exact tenant and branch. | Context-free creation fails; application checks and composite database keys reject cross-tenant branches; register tables use forced RLS. | TEST-G2-REGISTER-TENANCY-001 (`RegisterDeviceManagerTest`). | RISK-G2-DEVICE-CROSS-TENANT-001 |
| REQ-G2-DEVICE-ENROLL-001 — A tenant can issue and consume a one-time device enrollment credential without persisting plaintext. | The raw high-entropy token is returned once, only its SHA-256 digest is stored, expiry and reuse fail, consumption is atomic, and active resolution cannot cross tenants. | TEST-G2-DEVICE-ENROLL-001 (`RegisterDeviceManagerTest`). | RISK-G2-DEVICE-TOKEN-001, RISK-G2-DEVICE-CROSS-TENANT-001 |

## Consequences

- Enrollment is an application service rather than an HTTP contract, allowing later administrative APIs and device clients to share the same invariant boundary.
- Clearing the digest after successful consumption makes reuse fail closed and minimizes retained credential material.
- Public device identifiers cannot be changed through Eloquent updates, preserving a stable identity for later command signing and audit correlation.
- `last_seen_at` is reserved but is not updated until authenticated device requests exist.

## Risks

- RISK-G2-DEVICE-TOKEN-001 — An enrollment token could be disclosed or replayed before expiry. Controls: 256-bit randomness, digest-only storage, finite expiry, transactional row locking, one-use consumption, and digest erasure.
- RISK-G2-DEVICE-CROSS-TENANT-001 — A register or device could be associated with another tenant. Controls: mandatory `TenantContext`, tenant-qualified application queries, composite foreign keys, and forced RLS.

## Deferred work

- Administrative HTTP endpoints and authorization policies.
- Register shifts, fiscal/receipt sequence allocation, and cash-drawer state.
- Device authentication credentials issued after enrollment, rotation, revocation, and last-seen updates.
- Hardware capabilities, scanner/printer bindings, offline command signing, and synchronization.
