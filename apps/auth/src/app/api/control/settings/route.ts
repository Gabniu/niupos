import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authSettingDefinitions, definitionByKey, validateSettingValue } from "@/lib/control-plane";
import { authDatabase } from "@/lib/database";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

export async function GET() {
  if (!await administrator()) return NextResponse.json({ message: "Not found." }, { status: 404 });
  try {
    const result = await authDatabase.query<{ setting_key: string; setting_value: unknown; version: string; updated_at: Date }>("SELECT setting_key, setting_value, version, updated_at FROM auth_control_settings ORDER BY setting_key");
    const overrides = new Map(result.rows.map((row) => [row.setting_key, row]));
    return NextResponse.json({ data: authSettingDefinitions.map((definition) => {
      const override = overrides.get(definition.key);
      return { ...definition, value: override?.setting_value ?? definition.defaultValue, version: override ? Number(override.version) : 0, updatedAt: override?.updated_at?.toISOString() ?? null };
    }) });
  } catch {
    return NextResponse.json({ message: "Control-plane storage is unavailable. Apply its migration first." }, { status: 503 });
  }
}

export async function PUT(request: Request) {
  const session = await administrator();
  if (!session) return NextResponse.json({ message: "Not found." }, { status: 404 });

  const body = await request.json().catch(() => null) as { key?: unknown; value?: unknown; version?: unknown } | null;
  if (!body || typeof body.key !== "string" || typeof body.version !== "number") return NextResponse.json({ message: "Invalid setting update." }, { status: 422 });
  const definition = definitionByKey.get(body.key);
  if (!definition) return NextResponse.json({ message: "Unknown setting." }, { status: 422 });

  let value;
  try { value = validateSettingValue(definition, body.value); }
  catch (error) { return NextResponse.json({ message: error instanceof Error ? error.message : "Invalid setting value." }, { status: 422 }); }

  const client = await authDatabase.connect();
  try {
    await client.query("BEGIN");
    const current = await client.query<{ setting_value: unknown; version: string }>("SELECT setting_value, version FROM auth_control_settings WHERE setting_key = $1 FOR UPDATE", [definition.key]);
    const existing = current.rows[0];
    const currentVersion = existing ? Number(existing.version) : 0;
    if (currentVersion !== body.version) { await client.query("ROLLBACK"); return NextResponse.json({ message: "This setting changed since it was loaded. Refresh and try again." }, { status: 409 }); }
    await client.query("INSERT INTO auth_control_settings (setting_key, setting_value, setting_mode, version, updated_by) VALUES ($1, $2::jsonb, $3, 1, $4) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, setting_mode = EXCLUDED.setting_mode, version = auth_control_settings.version + 1, updated_by = EXCLUDED.updated_by, updated_at = now()", [definition.key, JSON.stringify(value), definition.mode, session.user.id]);
    await client.query("INSERT INTO auth_control_audit (actor_user_id, event_type, subject_key, previous_value, next_value) VALUES ($1, 'auth.setting.updated', $2, $3::jsonb, $4::jsonb)", [session.user.id, definition.key, JSON.stringify(existing?.setting_value ?? null), JSON.stringify(value)]);
    await client.query("COMMIT");
    return NextResponse.json({ data: { key: definition.key, value, version: currentVersion + 1, activation: definition.mode } });
  } catch {
    await client.query("ROLLBACK");
    return NextResponse.json({ message: "The setting could not be saved." }, { status: 500 });
  } finally { client.release(); }
}
