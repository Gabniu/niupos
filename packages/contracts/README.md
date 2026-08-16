# NOVA Contracts

This package owns versioned API, event, error, idempotency, and synchronization
schemas. Generated clients are outputs; hand-edited client-specific wire
contracts are forbidden.

`schemas/sync-v1.schema.json` is the canonical offline synchronization wire
contract. Tenant and device identity come from authenticated request context and
are local repository partition keys; clients must not trust envelope-supplied
tenant or device identifiers. Server cursors are opaque and strictly increasing,
but can contain gaps when feeds are tenant-filtered.
