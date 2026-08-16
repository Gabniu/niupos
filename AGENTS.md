# NOVA POS Project Instructions

## Graphify and Obsidian are living project systems

- Treat Graphify as the machine-queryable knowledge graph for requirements, domain concepts, architecture, implementation, tests, risks, decisions, and source-code relationships.
- Treat the Obsidian vault as the human-readable planning, architecture, review, and decision workspace.
- Before answering material questions about NOVA, query the existing graph when `graphify-out/graph.json` exists. Do not rely only on memory or isolated files.
- When requirements, architecture, ADRs, module contracts, tests, or implementation files materially change, update the Graphify corpus and run an incremental Graphify update before declaring the work complete.
- Keep Graphify provenance fields and Obsidian links traceable to their source files. Never invent relationships; mark uncertain relationships as ambiguous or as open questions.
- Maintain stable concept names and note identifiers so Graphify nodes, Obsidian notes, requirements, ADRs, tests, and code can be cross-referenced reliably.
- Every planned requirement must link to its owning module, acceptance criteria, tests, risks, and relevant ADRs. Every ADR must link back to the requirements and graph concepts it affects.
- Review graph health, god nodes, surprising connections, isolated nodes, and community boundaries after material planning or architecture changes. Convert important findings into Obsidian review notes or explicit backlog items.
- Do not manually edit generated Graphify artifacts. Update source documents and regenerate or incrementally update the graph.
- Keep generated Graphify output out of the human-authored source corpus to prevent recursive ingestion.

## Completion rule

Work is not complete until its documentation, traceability links, planned or implemented tests, Graphify state, and applicable Obsidian notes agree with the current implementation.
