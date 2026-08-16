---
id: PLAN-NIU-ONBOARD-001
type: product-plan
status: in-progress
date: 2026-08-16
owners: [product, architecture, identity-platform, web, mobile, operations]
modules: [MOD-TENANCY, MOD-IAM, MOD-ONBOARDING, MOD-CHANNELS, MOD-CATALOGUE, MOD-CUSTOMERS, MOD-NOTIFY, MOD-ENTERPRISE]
requirements: [REQ-NIU-ONBOARD-001, REQ-NIU-CHANNEL-001, REQ-NIU-CUSTOMER-ID-001, REQ-NIU-AUTOMATION-001, REQ-NIU-CUSTOMIZATION-001]
tests: [TEST-NIU-ONBOARD-001, TEST-NIU-CHANNEL-001, TEST-NIU-CUSTOMER-ID-001, TEST-NIU-AUTOMATION-001, TEST-NIU-CUSTOMIZATION-001]
risks: [RISK-NIU-ONBOARD-001, RISK-NIU-CHANNEL-001, RISK-NIU-CUSTOMER-ID-001, RISK-NIU-AUTOMATION-001]
adrs: [ADR-0001, ADR-0036, ADR-0037, ADR-0057, ADR-0062]
---

# NIU Adaptive Onboarding and Channel Provisioning Plan

## Outcome

Make setup feel like a short, conversational conversation while producing a real,
auditable organization configuration. The first meaningful question is the
merchant's operating surface:

1. **POS only**
2. **Web ecommerce**
3. **Mobile ecommerce**
4. **Web and mobile ecommerce**

The answer selects a versioned industry blueprint and a safe set of follow-up
steps. It must never create sample products, fake stores, placeholder users, or
unreviewed production credentials. A draft is persisted and resumable; the
server, not the browser, is authoritative for capabilities, tenancy, and
provisioning state.

## Requirements and acceptance criteria

### REQ-NIU-ONBOARD-001 — Conversational, resumable onboarding

- Ask one decision or short field per step, show progress and the reason for the
  question, and allow Back, Save and finish later, and Resume.
- Persist a draft after every successful step with an idempotency key and a
  versioned wizard definition. Refreshing or retrying cannot duplicate a tenant,
  membership, client, notification, or job.
- Show immediate pending, success, and failure states beside the action. A
  completed step is not implied by a disabled button.
- On completion, show a setup timeline containing what was automated, what needs
  the owner's approval, and the next action.

### REQ-NIU-CHANNEL-001 — Channel-aware branching

- Store a typed `channelSelection` of `pos`, `web`, `mobile`, or `web_mobile`.
- The API returns the next safe step from the selected blueprint; the client
  renders only steps allowed by that response.
- All channel clients carry an organization, environment, audience, redirect,
  and lifecycle state. The browser never receives a client secret.
- Web and mobile share catalogue, pricing, customers (where opted in), orders,
  availability, and fulfillment policy, but have independent publication and
  release state.

### REQ-NIU-CUSTOMER-ID-001 — Separate workforce and customer identity

- Workforce identity (owners, managers, cashiers, operators) uses NIU Auth and
  can receive POS/administration memberships only through an explicit local
  admission operation.
- Customer identity is an optional, customer-scoped web/mobile audience. Guest
  checkout remains possible. A customer account never grants a POS tenant
  membership.
- Linking identities requires an explicit authenticated action and an issuer +
  subject key; email similarity alone cannot link accounts.

### REQ-NIU-AUTOMATION-001 — Safe automation with human gates

- Automate only deterministic, reversible setup: organization/tenant mapping,
  initial owner membership, role/navigation defaults, enabled capabilities,
  channel client metadata, notification templates, catalogue import scaffolding,
  location/register scaffolding, and build/checklist jobs.
- Require explicit review for tax/legal activation, payment and messaging
  credentials, production domains/DNS, app-store signing or publishing, data
  imports/merges, refunds/credit policies, high-risk permissions, and regulated
  profiles.
- Every run has a dry-run preview, approval boundary, idempotency key, audit
  record, status (`queued|running|needs_action|succeeded|failed|rolled_back`),
  retry policy, and compensation/rollback path.

### REQ-NIU-CUSTOMIZATION-001 — Blueprint plus bounded overrides

- A blueprint supplies industry defaults. The organization can override allowed
  terminology, enabled capabilities, navigation, roles, branding, notification
  preferences, channel settings, fulfillment choices, and operating hours.
- Platform policy owns protected settings: tenant isolation, identity audience,
  permission invariants, audit retention, tax/fiscal controls, and secret
  handling. Changes to protected settings require an elevated, audited action.
- Configuration is versioned. A blueprint update is proposed as a diff and does
  not silently rewrite live business behavior.

## Branching flow

### Common first steps

1. Sign in or create the workforce owner through NIU Auth.
2. Choose or create the organization. If the user has no tenant, offer the
   authenticated organization-creation wizard rather than sending them to an
   operator-only server command.
3. Choose the industry blueprint (grocery/convenience, supermarket,
   bakery/cake, restaurant/cafe, apparel, salon/services, wholesale/B2B).
4. Choose channels using the four choices above; explain that the choice can be
   changed later without losing the POS ledger.
5. Review the generated plan and confirm the data and permissions boundary.

### POS only

Collect legal/display name, currency/region, locations, warehouses/registers,
catalogue source, tax profile, staff roles, payment methods, receipt settings,
and offline device policy. Provision tenant, owner membership, location and
register drafts, role templates, and a POS workspace. End at a guided “add your
first real product / connect a register” checklist; never seed fake records.

### Web ecommerce

After common steps, collect storefront name/domain (preview first), brand
assets, catalogue publication rules, customer-account policy (guest/optional/
required), fulfillment (pickup/delivery/shipping), payment provider choice,
notifications, and preview/publish approval. Create a public web client using
PKCE, a draft storefront configuration, and a publication checklist. Domain,
payment, and live publish remain owner-approved gates.

### Mobile ecommerce

Collect app display name, icon/brand assets, customer-account policy, deep-link
scheme, supported platforms, fulfillment, notifications, and release target.
Create a mobile public client using PKCE and a build configuration. Provide a
real preview/PWA where available; label signing, store submission, and release
as pending until a build service reports success. Never claim an app is live
from a configuration write alone.

### Web and mobile

Run web configuration first, then mobile configuration, with a shared catalogue,
pricing, customer consent policy, order state, and inventory availability
policy. Require an explicit decision for shared stock, channel reservations, or
separate allocation pools before publishing either channel.

## Industry blueprints

Blueprints are capability presets, not separate products:

| Profile | Defaults to ask about | Initial scope |
|---|---|---|
| Grocery/convenience | weighted items, barcode scanning, replenishment, pickup | launch |
| Supermarket/enterprise | branches, warehouses, staff roles, transfers, approvals | launch after grocery |
| Bakery/cake | pre-orders, pickup slots, production dates, custom notes | launch |
| Restaurant/cafe | menus, modifiers, tables, kitchen routing, tips | planned |
| Apparel | variants, sizes/colors, transfers, returns | planned |
| Salon/services | appointments, staff calendars, deposits, customer reminders | planned |
| Wholesale/B2B | accounts, price lists, credit terms, invoices | planned |
| Pharmacy/regulated | traceability, controlled permissions, compliance evidence | deferred pending counsel |

The blueprint determines questions and navigation, never tenant isolation or
financial truth. Feature entitlements and blueprint versions keep the platform
extensible without a giant conditional frontend.

## Notifications and owner control

Create an in-app setup timeline and notification center backed by MOD-NOTIFY.
Notify the owner (and only explicitly authorized delegates) for draft saved,
invitation accepted, provisioning started/completed/failed, import progress,
build ready, domain/payment approval needed, stock or sync incidents, and
automation rollback. Allow per-event email/SMS/push/in-app preferences, quiet
hours, digest/escalation, and unsubscribe where legally required. Messages carry
status and correlation IDs, not secrets or full payment/customer data.

The owner sees a “Customize” surface for permitted overrides and an “Automation
run” surface for preview, approve, retry, or rollback. Platform operators get an
audited queue of blocked approvals and failed jobs; they do not gain merchant
data merely because they can maintain a blueprint.

## Data and API shape (planned)

Introduce an onboarding draft and provisioning-run boundary rather than placing
wizard state in browser storage only:

- `onboarding_drafts`: tenant/org, blueprint/version, channel selection, step
  answers, status, owner, revision, timestamps.
- `channel_registrations`: channel, audience, client metadata, environment,
  publication state, domain/build references; no secret values.
- `provisioning_runs` and `provisioning_actions`: idempotency, dry-run/approval,
  action status, audit/correlation, compensation metadata.
- `notification_preferences` and `setup_events`: owner routing and timeline.

Expose versioned endpoints for draft read/write, next-step evaluation, plan
preview, approval, run status, allowed customization, channel preview, and
publication. Contracts must be tenant-scoped, permission-gated, rate-limited,
audited, and safe to retry.

## Delivery sequence

1. Ratify this plan and ADR-0062; add module contracts and requirement tests.
2. Implement draft, blueprint, channel, and provisioning-run persistence with
   tenant/RLS and audit evidence.
3. Build the conversational wizard shell with real API state, branch tests,
   accessible transitions, reduced-motion, and resume behavior.
4. Deliver POS-only onboarding and authenticated organization creation first.
5. Add web ecommerce draft/publication checklist and customer-account boundary.
6. Add mobile build configuration/preview and release approval boundary.
7. Add notification center/preferences and operator approval queue. The first
   in-app setup notification API now exists; external delivery remains gated.
8. Add grocery and bakery blueprints; validate supermarket/enterprise next.
9. Exercise failure, retry, rollback, duplicate-submit, cross-tenant, and
   unauthorized-customer-membership tests before enabling automation in
   production.

### First slice now implemented (2026-08-16)

The API now persists one authenticated user's resumable onboarding draft with
optimistic revision checks and bounded idempotency keys. The first web wizard
asks the four channel choices, then the industry profile and organization name,
and renders the server-returned next step and automation/approval preview. It
converts a completed draft into one real Kenya tenant and one owner membership
through the existing tenancy/IAM application contracts. POS then collects the
first real company, branch, warehouse, and register through the existing
Tenancy/Register contracts.

Web/mobile branches now create a real tenant-scoped customer channel
registration containing only public metadata (client ID, environment, redirect
URIs, and lifecycle state), then stop at an explicit approval gate. Secrets,
domains, payment credentials, signing, and publication are not accepted by the
browser or created implicitly. The slice does not seed products, catalogue,
inventory, customer accounts, or placeholder orders; those remain explicit next
setup actions.

The channel registration API is available at `api/v1/channels/registrations`
for authenticated tenant owners with `channels.registrations.manage`. A
registration can move from `draft` to `approval_required`; publication and
external build/deployment workers remain later steps. For `web_mobile`, the
wizard deliberately presents web configuration first so shared catalogue,
stock-allocation, customer-consent, and mobile-release decisions can be made
before the second channel is enabled.

The onboarding provisioning boundary is also now available through
`api/v1/onboarding/provisioning-runs/preview`, `GET .../{runId}`, and
`POST .../{runId}/approve`, `POST .../{runId}/process`, plus
`GET /api/v1/onboarding/setup-timeline`.
It creates a durable dry-run plan and action list,
uses `onboarding.provision` owner permission, records security audit events,
and transitions approval-gated actions from `needs_approval` to `queued` only
after an explicit approval. A worker processes queued actions inside a
tenant-scoped transaction, but remains fail-closed until each external domain,
payment, messaging, signing, and publication adapter has its own evidence and
rollback path.
Preview and approval transitions are also written to the tenant-scoped setup
timeline so the owner can see what happened without inferring completion from a
disabled button. The first worker boundary is fail-closed: until a verified
executor is registered for an action, processing records `needs_action` and
never reports a domain, payment, message, build, or release side effect as
successful. The worker has two safe internal executors: initializing the
existing tenant workspace-preferences row and initializing tenant notification
preferences (in-app enabled, external channels disabled). Both are idempotent,
transactional, auditable, and have no external side effects. All other actions
remain blocked until their adapters are proven. A POS-only run therefore reaches
`completed`; web/mobile runs remain `needs_action` when publication or release
actions have no verified executor. The web wizard invokes the worker after
approval and renders the resulting status/timeline instead of leaving the owner
to infer whether queued work ran.

Setup events now also create tenant-scoped in-app notifications for the acting
owner. `GET /api/v1/onboarding/setup-notifications` returns the latest 100
notifications and `POST .../{notificationId}/read` marks one read. The writer
is idempotent and does not send email, SMS, or push; those channels require a
verified delivery adapter and explicit tenant preferences. The onboarding
wizard renders the in-app records with unread counts and mark-read actions.
`GET/PUT /api/v1/onboarding/notification-preferences` persists the tenant's
in-app, email, SMS, push, and quiet-hours intent. Email availability is reported
only when the server-side Resend flag, key, and sender are present; SMS and push
remain unavailable.
If an owner enables an external channel before that adapter exists, setup
events create a tenant-scoped `blocked` delivery intent with an auditable
reason; nothing is sent and retries cannot bypass the registry.
`GET /api/v1/onboarding/notification-deliveries` and the wizard's delivery
status panel make blocked intents visible without exposing recipient secrets.

The API now defines separate contracts for external provisioning executors and
notification delivery adapters. Each receives a tenant-scoped request DTO and
must return a status plus evidence; the worker remains fail-closed until a
verified implementation is registered. NIU Auth already sends identity emails
through its server-side Resend integration. Onboarding email will use Resend as
the first provider only after recipient lookup, server-side secret references,
retry behaviour, and delivery evidence are implemented and tested. Enabling a
channel therefore records intent but does not send a message yet.

The Resend adapter boundary validates the sender, recipient, and bounded
plain-text content, sends only over HTTPS, never returns the API key, and records
only a provider message ID and HTTP status as evidence. An explicit,
permission-protected delivery action now applies consent checks, a retry limit,
and tenant-scoped state updates. Preferences alone never send a message.

`GET /api/v1/onboarding/provisioning-capabilities` exposes the server-owned
executor registry. Only workspace and notification initialization are currently
available; storefront publication and mobile release are explicitly unavailable
and marked as external-side-effect actions. The wizard renders this registry so
owners see the same verified/blocked state before approving a plan.

## Traceability and test catalogue

The requirement IDs remain tracked as cross-gate work. The implemented slice
has executable evidence below; the remaining items must gain executable
evidence before production automation is enabled:

- `TEST-NIU-ONBOARD-001`: resume, branch, validation, autosave, duplicate
  submit, accessibility, reduced motion, and no-placeholder data.
- `TEST-NIU-CHANNEL-001`: four selections produce the exact allowed step graph;
  web/mobile client metadata is isolated, tenant/RLS scoped, approval gated,
  duplicate-safe, and secrets never leave the server.
- `TEST-NIU-CUSTOMER-ID-001`: guest checkout, optional account, explicit link,
  and rejection of customer-to-workforce membership escalation.
- `TEST-NIU-AUTOMATION-001`: dry-run diff, approval gates, idempotent retry,
  audit/outbox, failure notification, and compensation.
- `TEST-NIU-CUSTOMIZATION-001`: blueprint versioning, allowed override,
  protected-setting denial, and visible configuration diff.

## Risks and controls

| Risk | Control | Owner |
|---|---|---|
| Wizard creates duplicate tenants or clients | server idempotency, unique keys, transaction/outbox, replay tests | API |
| Customer identity accidentally grants workforce access | separate audiences/scopes, explicit mapping, tenant admission tests | Identity |
| Automation publishes an unsafe production configuration | dry-run + approval gates + audit + rollback | Operations |
| One profile becomes a giant conditional UI | versioned blueprints and feature entitlements | Product |
| Web/mobile stock diverges | explicit allocation/reservation policy and reconciliation | Inventory |
| Owner misses a blocked action | in-app timeline plus configurable delivery/escalation | Notify |
| Regulated workflows are oversimplified | deferred profile and legal/compliance review gate | Product/Compliance |

## Reference patterns reviewed

- Shopify POS staff roles and multi-location operations: <https://help.shopify.com/en/manual/sell-in-person/shopify-pos/staff-management/understanding-pos-staff-management>
- Toast online ordering and location access: <https://doc.toasttab.com/doc/platformguide/adminToastOnlineOrderingOverview.html>
- Lightspeed role and multi-shop inventory controls: <https://retail-support.lightspeedhq.com/hc/en-us/articles/229129608-Setting-up-employee-roles-and-access>
- Shopify customer-scoped headless identity: <https://shopify.dev/docs/storefronts/headless/building-with-the-customer-account-api/index>
- Google identity audiences and Microsoft external identity boundaries: <https://docs.cloud.google.com/architecture/identity> and <https://learn.microsoft.com/en-us/entra/external-id/tenant-configurations>
