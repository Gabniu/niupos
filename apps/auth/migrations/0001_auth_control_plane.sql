CREATE TABLE IF NOT EXISTS auth_control_settings (
    setting_key text PRIMARY KEY,
    setting_value jsonb NOT NULL,
    setting_mode text NOT NULL CHECK (setting_mode IN ('live', 'restart', 'secret-reference')),
    version bigint NOT NULL DEFAULT 1,
    updated_by text NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS auth_control_audit (
    id bigserial PRIMARY KEY,
    actor_user_id text NOT NULL,
    event_type text NOT NULL,
    subject_key text NOT NULL,
    previous_value jsonb,
    next_value jsonb,
    occurred_at timestamptz NOT NULL DEFAULT now()
);

REVOKE DELETE, TRUNCATE ON auth_control_audit FROM PUBLIC;
