---
id: nova-knowledge-system
type: index
status: active
owners:
  - architecture
graphify_source: true
---

# NOVA Knowledge System

NOVA uses two complementary knowledge layers:

1. Human-authored notes in this vault contain requirements, ADRs, module plans, reviews, risks, test plans, and implementation records.
2. Graphify output in `../90-generated/graphify/` is generated navigation and analysis. It must not be edited manually.

## Traceability chain

Every material capability follows this chain:

`Vision → Requirement → Domain rule → ADR → Module → API/event/data contract → Test → Implementation → Operational evidence`

Stable IDs are mandatory. Suggested prefixes are `VISION-`, `REQ-`, `RULE-`, `ADR-`, `MOD-`, `API-`, `EVT-`, `DATA-`, `TEST-`, `RISK-`, and `OPS-`.

## Update workflow

1. Edit a human-authored source note or implementation file.
2. Update affected backlinks and traceability fields.
3. Run Graphify incrementally in deep mode from the project root.
4. Review graph health, changed nodes, surprising connections, isolated concepts, and community boundaries.
5. Convert meaningful findings into an ADR, risk, requirement, review note, or backlog item.
6. Run `scripts/Export-Knowledge.ps1` to refresh HTML and generated Obsidian notes.
7. Do not mark work complete until `scripts/Test-Knowledge.ps1` passes.

## Generated-content boundary

The `.graphifyignore` file excludes both `graphify-out/` and `vault/90-generated/`. This prevents a generated graph from ingesting its own reports and generated notes.

