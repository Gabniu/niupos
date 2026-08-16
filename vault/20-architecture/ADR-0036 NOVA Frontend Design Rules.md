---
id: ADR-0036
title: NOVA Frontend Design Rules
status: accepted
date: 2026-08-08
requirements: [REQ-G6-WEB-001]
tests: [TEST-G6-WEB-001]
risks: [RISK-G6-WEB-001]
---

# Decision

All NIU web pages (the visible successor branding for NOVA POS/Auth) use one shared visual system and are reviewed against their
provided reference before acceptance.

- Hanken Grotesk is the only product font for headings, labels, controls,
  navigation, body copy, and brand wordmarks. Product wordmarks are uppercase,
  upright, and slightly larger than nearby navigation.
- Use the reference palette: soft white surfaces, deep navy text, black and
  charcoal for NIU POS primary actions and active indicators, emerald for NIU Auth/Identity,
  light gray structure, and soft red for errors. Semantic success may use a
  restrained status green only when it communicates a real operational state.
- Use the 8px spacing rhythm, restrained weights, compact centered content rails,
  16px primary control/card radii, and subtle tonal shadows.
- Authentication right rails stay compact and centered. Desktop subtitles that
  are designed as one sentence remain on one line; responsive layouts may wrap
  only when the viewport requires it.
- Interactive controls preserve at least a 48px touch target, while visual
  density is controlled through typography, gaps, and content width.
- Reference imagery may inspire composition, but image overlays, carousel copy,
  and branding must remain subordinate to the task.
- No invented tenant, store, product, price, or register data may appear in a
  production page. Use database-backed data with explicit loading, empty,
  error, and offline states.
- Visible product names are NIU POS, NIU Auth, and NIU Identity. Existing NOVA
  strings remain only for technical compatibility (URLs, environment keys,
  deployment/container names, package names, and historical ADR identifiers).
- Administrative people, organization, application, and consent pages default
  to compact database-backed tables. Add, inspect, and edit operations use
  accessible modal dialogs instead of permanent side forms; empty states use a
  purposeful icon or approved illustration and never sample records. Search,
  filters, and result counts sit in a lightweight control row outside the table
  surface; table rows use semantic icons (for example, a user icon) rather than
  decorative status dots as identity markers.
- The authenticated header provides a compact profile control with the real
  signed-in identity and administrator status. Do not repeat that identity as
  an overview metric/card unless a task explicitly requires it.
- Configuration is grouped into compact sections/accordions or dialogs rather
  than an unbounded single scroll, while preserving save status, versioning,
  activation mode, and secret-reference protections.
- Every browser-facing NIU app ships a matching NIU monogram favicon/app icon;
  product labels and favicon metadata must not fall back to the retired visible
  NOVA brand.
- Every page review records deliberate deviations from the supplied reference
  and confirms desktop, tablet, mobile, keyboard, and reduced-motion behavior.
- Every asynchronous create/save/submit action guards against duplicate
  submissions, disables its trigger while pending, shows progress immediately,
  renders failures beside the action, and confirms success before closing the
  flow.
- Conversational setup wizards ask one meaningful decision or short field per
  step, show progress and the reason for the question, autosave a real draft,
  support Back/Resume/Save and finish later, and end with a server-backed setup
  timeline. Branches come from a versioned API definition; the browser must not
  invent capability steps or treat a disabled button as proof of completion.
- Wizard transitions use subtle 160–240ms motion, provide reduced-motion and
  keyboard/screen-reader behavior, preserve inline validation, and never use a
  blocking full-screen spinner for routine work. Provisioning actions show
  pending/succeeded/failed/needs-action states and safe retry affordances.
- Entry pages must not apply a document-wide veil while the page is usable. Any
  image treatment is scoped to the image layer; content rails remain opaque and
  above decorative layers. Service-worker updates must refresh a stale client
  after activation so a previous shell cannot leave an obsolete modal or layer
  visible after deployment.

# Scope

Additional durable rules: authenticated shells use a compact 208px rail by
default; desktop rails are viewport-bounded/sticky so only the content region
scrolls; task-focused register flows may use a 56px icon rail. Checkout
summaries stay visible on desktop with sticky, viewport-bounded panels and
return to normal flow on narrow screens. Organisation preferences may hide the
rail or enable application kiosk presentation; kiosk presentation is a layout
preference and not a substitute for browser/device lockdown or authentication.

These rules apply to Login, Register, Forgot Password, OTP/MFA, reset-password,
Store Selection, and all subsequent POS, administration, and reporting pages.
