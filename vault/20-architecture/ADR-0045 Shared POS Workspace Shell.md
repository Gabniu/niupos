# ADR-0045 Shared POS Workspace Shell

## Status

Accepted — 2026-08-11.

## Context

Authenticated POS pages had been implemented as independent routes, which caused the sidebar, mobile navigation, header status, and workspace spacing to vary between dashboard, catalogue, inventory, sales, shifts, and receipts.

## Decision

Use `apps/web/src/components/PosShell.tsx` as the shared authenticated workspace frame. It owns the desktop sidebar, compact header, connected state, icon system, active navigation treatment, and mobile drawer/toggle. Authentication and store/workspace selection pages remain outside this shell because they are pre-workspace flows.

All authenticated operational pages render inside this shell and provide their active route: dashboard, sales, products, inventory, shifts, and receipt detail. Navigation remains real links; no page receives fabricated business data from the shell. On desktop the rail is sticky and viewport-bounded (`h-screen` with overflow clipped), so only the workspace content scrolls. On narrow screens the same navigation is a fixed drawer behind an explicit toggle.

## Traceability

- Requirement: consistent navigation and responsive workspace chrome across POS pages.
- Implementation: `apps/web/src/components/PosShell.tsx`, `apps/web/src/app/dashboard/page.tsx`, `apps/web/src/app/sale/page.tsx`, `apps/web/src/app/products/page.tsx`, `apps/web/src/app/inventory/page.tsx`, `apps/web/src/app/shift/page.tsx`, `apps/web/src/app/receipts/[receipt]/page.tsx`.
- Design constraints: compact Hanken Grotesk typography, 8px rhythm, keyboard-visible controls, and mobile navigation that is available rather than hidden.
- Verification: web lint, TypeScript, production build, repository architecture tests, and live route smoke checks. The current web verification is 14 offline/sync tests passing, lint passing, and the production build passing.

## Risks and follow-up

- Reports and Settings links remain routed to the dashboard until their database-backed contracts are implemented.
- The dashboard retains its original inline shell for now; it should converge on `PosShell` in a later cleanup to remove duplicate navigation definitions.
