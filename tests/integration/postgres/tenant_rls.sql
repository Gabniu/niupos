\set ON_ERROR_STOP on

BEGIN;

CREATE ROLE nova_rls_test_app NOLOGIN;
CREATE SCHEMA nova_rls_test;

CREATE TABLE nova_rls_test.organizations (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    name text NOT NULL
);

CREATE TABLE nova_rls_test.roles (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    name text NOT NULL
);

CREATE TABLE nova_rls_test.role_permissions (
    tenant_id uuid NOT NULL,
    role_id uuid NOT NULL,
    permission_id text NOT NULL,
    PRIMARY KEY (tenant_id, role_id, permission_id)
);

CREATE TABLE nova_rls_test.tenant_audit_events (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    event_type text NOT NULL
);

CREATE TABLE nova_rls_test.warehouses (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    name text NOT NULL
);

CREATE TABLE nova_rls_test.catalogue_barcodes (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    normalized_value text NOT NULL
);

CREATE TABLE nova_rls_test.devices (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    public_id uuid NOT NULL
);

CREATE TABLE nova_rls_test.product_prices (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    amount_minor bigint NOT NULL
);

CREATE TABLE nova_rls_test.stock_movements (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    quantity_delta bigint NOT NULL
);

CREATE TABLE nova_rls_test.register_shifts (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    status text NOT NULL
);

CREATE TABLE nova_rls_test.stock_reservations (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    status text NOT NULL
);

CREATE TABLE nova_rls_test.sales (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    gross_minor bigint NOT NULL
);

CREATE TABLE nova_rls_test.sale_lines (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    gross_minor bigint NOT NULL
);

CREATE TABLE nova_rls_test.payment_attempts (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    amount_minor bigint NOT NULL
);

CREATE TABLE nova_rls_test.receipts (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    gross_minor bigint NOT NULL
);

CREATE TABLE nova_rls_test.sync_changes (id uuid PRIMARY KEY, tenant_id uuid NOT NULL);
CREATE TABLE nova_rls_test.sync_device_cursors (id uuid PRIMARY KEY, tenant_id uuid NOT NULL);
CREATE TABLE nova_rls_test.sync_command_inbox (id uuid PRIMARY KEY, tenant_id uuid NOT NULL);
CREATE TABLE nova_rls_test.sync_conflicts (id uuid PRIMARY KEY, tenant_id uuid NOT NULL);

ALTER TABLE nova_rls_test.organizations ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.organizations FORCE ROW LEVEL SECURITY;

CREATE POLICY organizations_tenant_isolation
    ON nova_rls_test.organizations
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.roles FORCE ROW LEVEL SECURITY;
CREATE POLICY roles_tenant_isolation ON nova_rls_test.roles
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.role_permissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.role_permissions FORCE ROW LEVEL SECURITY;
CREATE POLICY role_permissions_tenant_isolation ON nova_rls_test.role_permissions
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.tenant_audit_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.tenant_audit_events FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_audit_events_isolation ON nova_rls_test.tenant_audit_events
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.warehouses ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.warehouses FORCE ROW LEVEL SECURITY;
CREATE POLICY warehouses_tenant_isolation ON nova_rls_test.warehouses
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.catalogue_barcodes ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.catalogue_barcodes FORCE ROW LEVEL SECURITY;
CREATE POLICY catalogue_barcodes_tenant_isolation ON nova_rls_test.catalogue_barcodes
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.devices ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.devices FORCE ROW LEVEL SECURITY;
CREATE POLICY devices_tenant_isolation ON nova_rls_test.devices
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.product_prices ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.product_prices FORCE ROW LEVEL SECURITY;
CREATE POLICY product_prices_tenant_isolation ON nova_rls_test.product_prices
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.stock_movements ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.stock_movements FORCE ROW LEVEL SECURITY;
CREATE POLICY stock_movements_tenant_isolation ON nova_rls_test.stock_movements
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.register_shifts ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.register_shifts FORCE ROW LEVEL SECURITY;
CREATE POLICY register_shifts_tenant_isolation ON nova_rls_test.register_shifts
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.stock_reservations ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.stock_reservations FORCE ROW LEVEL SECURITY;
CREATE POLICY stock_reservations_tenant_isolation ON nova_rls_test.stock_reservations
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.sales FORCE ROW LEVEL SECURITY;
CREATE POLICY sales_tenant_isolation ON nova_rls_test.sales
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.sale_lines ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.sale_lines FORCE ROW LEVEL SECURITY;
CREATE POLICY sale_lines_tenant_isolation ON nova_rls_test.sale_lines
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.payment_attempts ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.payment_attempts FORCE ROW LEVEL SECURITY;
CREATE POLICY payment_attempts_tenant_isolation ON nova_rls_test.payment_attempts
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

ALTER TABLE nova_rls_test.receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE nova_rls_test.receipts FORCE ROW LEVEL SECURITY;
CREATE POLICY receipts_tenant_isolation ON nova_rls_test.receipts
    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);

DO $$
DECLARE sync_table text;
BEGIN
    FOREACH sync_table IN ARRAY ARRAY['sync_changes', 'sync_device_cursors', 'sync_command_inbox', 'sync_conflicts'] LOOP
        EXECUTE format('ALTER TABLE nova_rls_test.%I ENABLE ROW LEVEL SECURITY', sync_table);
        EXECUTE format('ALTER TABLE nova_rls_test.%I FORCE ROW LEVEL SECURITY', sync_table);
        EXECUTE format(
            'CREATE POLICY %I ON nova_rls_test.%I USING (tenant_id = nullif(current_setting(''app.tenant_id'', true), '''')::uuid) WITH CHECK (tenant_id = nullif(current_setting(''app.tenant_id'', true), '''')::uuid)',
            sync_table || '_tenant_isolation', sync_table
        );
    END LOOP;
END
$$;

GRANT USAGE ON SCHEMA nova_rls_test TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.organizations TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.roles TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.role_permissions TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.tenant_audit_events TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.warehouses TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.catalogue_barcodes TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.devices TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.product_prices TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.stock_movements TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.register_shifts TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.stock_reservations TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.sales TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.sale_lines TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.payment_attempts TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.receipts TO nova_rls_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON nova_rls_test.sync_changes, nova_rls_test.sync_device_cursors, nova_rls_test.sync_command_inbox, nova_rls_test.sync_conflicts TO nova_rls_test_app;

INSERT INTO nova_rls_test.organizations (id, tenant_id, name) VALUES
    ('01989f8e-7a42-7b41-8fc0-87e9b48e813e', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'Tenant A'),
    ('01989f8e-901f-7d7d-89d1-aa1e42622d4b', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'Tenant B');

INSERT INTO nova_rls_test.roles (id, tenant_id, name) VALUES
    ('01989f8e-b111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'Cashier A'),
    ('01989f8e-b222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'Cashier B');

INSERT INTO nova_rls_test.role_permissions (tenant_id, role_id, permission_id) VALUES
    ('01989f8e-7a42-7b41-8fc0-87e9b48e813e', '01989f8e-b111-7111-8111-111111111111', 'catalogue.products.read'),
    ('01989f8e-901f-7d7d-89d1-aa1e42622d4b', '01989f8e-b222-7222-8222-222222222222', 'catalogue.products.write');

INSERT INTO nova_rls_test.tenant_audit_events (id, tenant_id, event_type) VALUES
    ('01989f8e-c111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'identity.role.created'),
    ('01989f8e-c222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'identity.role.created');

INSERT INTO nova_rls_test.warehouses (id, tenant_id, name) VALUES
    ('01989f8e-d111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'Warehouse A'),
    ('01989f8e-d222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'Warehouse B');

INSERT INTO nova_rls_test.catalogue_barcodes (id, tenant_id, normalized_value) VALUES
    ('01989f8e-e111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', '616111'),
    ('01989f8e-e222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', '616222');

INSERT INTO nova_rls_test.devices (id, tenant_id, public_id) VALUES
    ('01989f8e-f111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', '01989f8e-f111-7111-8111-aaaaaaaaaaaa'),
    ('01989f8e-f222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', '01989f8e-f222-7222-8222-bbbbbbbbbbbb');

INSERT INTO nova_rls_test.product_prices (id, tenant_id, amount_minor) VALUES
    ('01989f8e-a111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 11600),
    ('01989f8e-a222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 23200);

INSERT INTO nova_rls_test.stock_movements (id, tenant_id, quantity_delta) VALUES
    ('01989f8e-3111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 10),
    ('01989f8e-3222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 20);

INSERT INTO nova_rls_test.register_shifts (id, tenant_id, status) VALUES
    ('01989f8e-4111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'open'),
    ('01989f8e-4222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'open');

INSERT INTO nova_rls_test.stock_reservations (id, tenant_id, status) VALUES
    ('01989f8e-5111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 'active'),
    ('01989f8e-5222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 'active');

INSERT INTO nova_rls_test.sales (id, tenant_id, gross_minor) VALUES
    ('01989f8e-6111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 11600),
    ('01989f8e-6222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 23200);

INSERT INTO nova_rls_test.sale_lines (id, tenant_id, gross_minor) VALUES
    ('01989f8e-7111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 11600),
    ('01989f8e-7222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 23200);

INSERT INTO nova_rls_test.payment_attempts (id, tenant_id, amount_minor) VALUES
    ('01989f8e-8111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 11600),
    ('01989f8e-8222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 23200);

INSERT INTO nova_rls_test.receipts (id, tenant_id, gross_minor) VALUES
    ('01989f8e-9111-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', 11600),
    ('01989f8e-9222-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b', 23200);

INSERT INTO nova_rls_test.sync_changes (id, tenant_id) VALUES
    ('01989f8e-a311-7111-8111-111111111111', '01989f8e-7a42-7b41-8fc0-87e9b48e813e'),
    ('01989f8e-a322-7222-8222-222222222222', '01989f8e-901f-7d7d-89d1-aa1e42622d4b');
INSERT INTO nova_rls_test.sync_device_cursors SELECT id, tenant_id FROM nova_rls_test.sync_changes;
INSERT INTO nova_rls_test.sync_command_inbox SELECT id, tenant_id FROM nova_rls_test.sync_changes;
INSERT INTO nova_rls_test.sync_conflicts SELECT id, tenant_id FROM nova_rls_test.sync_changes;

SET LOCAL ROLE nova_rls_test_app;
SELECT set_config('app.tenant_id', '01989f8e-7a42-7b41-8fc0-87e9b48e813e', true);

DO $$
BEGIN
    IF (SELECT count(*) FROM nova_rls_test.organizations) <> 1 THEN
        RAISE EXCEPTION 'RLS read isolation failed';
    END IF;

    IF EXISTS (
        SELECT 1 FROM nova_rls_test.organizations
        WHERE tenant_id <> current_setting('app.tenant_id')::uuid
    ) THEN
        RAISE EXCEPTION 'Cross-tenant row became visible';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.roles) <> 1 THEN
        RAISE EXCEPTION 'Role RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.role_permissions) <> 1 THEN
        RAISE EXCEPTION 'Role-permission RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.tenant_audit_events) <> 1 THEN
        RAISE EXCEPTION 'Tenant audit RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.warehouses) <> 1 THEN
        RAISE EXCEPTION 'Warehouse RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.catalogue_barcodes) <> 1 THEN
        RAISE EXCEPTION 'Catalogue barcode RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.devices) <> 1 THEN
        RAISE EXCEPTION 'Device RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.product_prices) <> 1 THEN
        RAISE EXCEPTION 'Product price RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.stock_movements) <> 1 THEN
        RAISE EXCEPTION 'Stock movement RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.register_shifts) <> 1 THEN
        RAISE EXCEPTION 'Register shift RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.stock_reservations) <> 1 THEN
        RAISE EXCEPTION 'Stock reservation RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.sales) <> 1 THEN
        RAISE EXCEPTION 'Sale RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.sale_lines) <> 1 THEN
        RAISE EXCEPTION 'Sale line RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.payment_attempts) <> 1 THEN
        RAISE EXCEPTION 'Payment attempt RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.receipts) <> 1 THEN
        RAISE EXCEPTION 'Receipt RLS read isolation failed';
    END IF;

    IF (SELECT count(*) FROM nova_rls_test.sync_changes) <> 1
        OR (SELECT count(*) FROM nova_rls_test.sync_device_cursors) <> 1
        OR (SELECT count(*) FROM nova_rls_test.sync_command_inbox) <> 1
        OR (SELECT count(*) FROM nova_rls_test.sync_conflicts) <> 1 THEN
        RAISE EXCEPTION 'Sync protocol RLS read isolation failed';
    END IF;
END
$$;

DO $$
BEGIN
    INSERT INTO nova_rls_test.organizations (id, tenant_id, name)
    VALUES (
        '01989f8e-a0aa-7169-a232-2cfddc31d499',
        '01989f8e-901f-7d7d-89d1-aa1e42622d4b',
        'Forbidden cross-tenant write'
    );
    RAISE EXCEPTION 'RLS cross-tenant write unexpectedly succeeded';
EXCEPTION
    WHEN insufficient_privilege THEN NULL;
END
$$;

RESET ROLE;
ROLLBACK;
