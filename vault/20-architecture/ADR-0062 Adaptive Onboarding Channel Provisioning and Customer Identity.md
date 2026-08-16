---
id: ADR-0062
title: Adaptive Onboarding, Channel Provisioning, and Customer Identity
status: accepted
date: 2026-08-16
requirements: [REQ-NIU-ONBOARD-001, REQ-NIU-CHANNEL-001, REQ-NIU-CUSTOMER-ID-001, REQ-NIU-AUTOMATION-001, REQ-NIU-CUSTOMIZATION-001]
tests: [TEST-NIU-ONBOARD-001, TEST-NIU-CHANNEL-001, TEST-NIU-CUSTOMER-ID-001, TEST-NIU-AUTOMATION-001, TEST-NIU-CUSTOMIZATION-001]
risks: [RISK-NIU-ONBOARD-001, RISK-NIU-CHANNEL-001, RISK-NIU-CUSTOMER-ID-001, RISK-NIU-AUTOMATION-001]
---

# Decision

NIU will use a server-authoritative, versioned onboarding blueprint. The wizard
first asks whether the organization needs POS only, web ecommerce, mobile
ecommerce, or both web and mobile. That answer selects a safe step graph and
capability preset; it does not silently publish a channel or create sample data.

Onboarding drafts and provisioning runs are tenant-scoped durable records. Every
write is idempotent, auditable, resumable, and visible in a setup timeline. The
browser renders a safe subset of the server's definition; it cannot invent a
step, grant a permission, or store a client secret.

NIU Auth remains the workforce identity provider. Customer web/mobile identity is
an optional, separate audience with narrower scopes. A customer account, guest
checkout, or storefront session cannot become a POS tenant membership. Explicit
issuer+subject linking is required.

# Why

POS, ecommerce, and service businesses need different questions and defaults.
Shopify, Toast, Lightspeed, and headless commerce identity APIs demonstrate the
value of location-aware permissions, channel-specific publication, fulfillment,
and customer-scoped identity. NIU's differentiator is combining those choices
with an adaptive, auditable setup plan while retaining one operational truth.

# Automation boundary

Safe automation includes draft/tenant mapping, default roles and navigation,
channel client metadata, notification templates, location/register scaffolding,
and build/checklist jobs. Human approval remains mandatory for tax/legal,
payment/messaging secrets, domains/DNS, app signing/publishing, data merges,
refund/credit policies, regulated profiles, and high-risk permissions.

Automation must provide dry-run diff, approval, idempotency key, status,
correlation/audit evidence, retry, and compensation. `succeeded` means the
specific action completed; it never means an external domain, payment provider,
or app store accepted a change unless that external evidence exists.

# Consequences

- Formalizes MOD-ONBOARDING and MOD-CHANNELS boundaries and a setup timeline.
- Requires blueprint/version data, onboarding drafts, channel registrations,
  provisioning runs/actions, and notification preferences.
- Keeps POS ledger, inventory, and tenant admission authoritative in their own
  modules; onboarding composes contracts rather than importing domains.
- Introduces more planning and approval states, but avoids unsafe “magic setup”
  and makes owner/operator responsibilities explicit.
- Native mobile publishing is a later release; the first mobile branch can
  produce a real preview/build configuration without claiming store publication.
- The first implementation slice persists a user-owned draft before a tenant
  exists. It uses application-level user ownership plus optimistic revisions;
  tenant/RLS provisioning is introduced when the draft is converted into a
  tenant through the existing Tenancy and IAM contracts.
- Conversion now creates exactly one active Kenya tenant and one owner
  membership inside the completion transaction. A second completion step
  creates the first real company, branch, warehouse, and register atomically for
  POS. Non-POS conversion stops at a channel setup step rather than inventing
  operational records.
- The first channel slice persists tenant-scoped web/mobile registration
  metadata with a public client ID, audience, environment, redirect URIs, and
  status. It rejects secret material, requires the channel-management
  permission, and transitions only to `approval_required`; external secrets,
  domains, payment providers, app signing, and publication remain gated actions.
- A `web_mobile` choice starts with web registration. Mobile registration is a
  separate follow-up so shared catalogue, availability, customer-consent, and
  release decisions are explicit rather than silently inferred.
- Provisioning previews are durable tenant-scoped runs/actions with a dry-run
  flag, idempotency fingerprint, correlation ID, audit event, and explicit
  `needs_action` to `queued` approval transition. A worker processes queued
  actions transactionally; external adapters must still prove external success
  and compensation before publication or credential automation is enabled.
- The initial worker boundary is deliberately fail-closed: an unregistered
  action executor changes queued actions to `needs_action` with an auditable
  reason rather than claiming success. External adapters must be registered
  explicitly and prove compensation before they can advance an action.
- The initial internal executors initialize the existing tenant workspace
  preferences row and tenant notification preferences, preserving existing
  owner choices and recording whether each row was newly initialized. These are
  reversible configuration state with no external side effect; all other action
  codes remain blocked. A POS-only run may complete when every action is handled
  by these internal executors; web/mobile runs remain `needs_action` at the
  external publication/release boundary. The wizard explicitly invokes this
  worker after approval and displays the returned status and timeline event.
- Setup events fan out to a tenant-scoped in-app notification record for the
  acting owner. Notification creation is idempotent and read state is explicit;
  external email/SMS/push delivery is not attempted until a verified adapter is
  registered. The onboarding wizard renders the in-app records and supports
  explicit mark-read transitions. Tenant owners can also update notification
  channel and quiet-hours intent through the onboarding preferences API; the
  response explicitly reports external delivery as unavailable until verified.
  Enabled external channels create blocked delivery intents rather than network
  attempts; a verified adapter must transition them with evidence. The owner can
  inspect these intents through the read-only delivery endpoint and wizard panel.
- External work and messaging use explicit adapter contracts with tenant-scoped
  request/result DTOs. The contracts do not enable side effects by themselves;
implementations require verified recipient resolution, secret handling,
retries, and evidence. NIU Auth's existing server-side Resend integration is
intentionally separate from onboarding. The API now contains a disabled-by-
default Resend adapter and an explicit, permission-protected dispatcher that
enforces bounded content, email consent, recipient verification, retry limits,
and safe provider evidence.
- Provisioning capabilities are exposed from a server-owned registry. The worker
  consults that registry rather than treating action names as proof of an
  implementation; unavailable external actions remain `needs_action`. The
  wizard displays the same capability state before approval.

# Traceability

See [[NIU Adaptive Onboarding and Channel Provisioning Plan]], [[ADR-0001 Full-Stack Modular Platform]],
[[ADR-0036 NOVA Frontend Design Rules]], [[ADR-0037 Shared Better Auth Identity Provider]],
and [[ADR-0057 POS Federated Subject Mapping and Tenant Admission]].
