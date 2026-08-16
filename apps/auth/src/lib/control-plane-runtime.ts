import { authDatabase } from "@/lib/database";
import { secretStore } from "@/lib/secret-store";

export async function loadStoredSettings(): Promise<Map<string, unknown>> {
  try {
    const result = await authDatabase.query<{ setting_key: string; setting_value: unknown }>("SELECT setting_key, setting_value FROM auth_control_settings");
    return new Map(result.rows.map((row) => [row.setting_key, row.setting_value]));
  } catch {
    return new Map();
  }
}

export async function liveBoolean(key: string, fallback: boolean): Promise<boolean> {
  try {
    const result = await authDatabase.query<{ setting_value: unknown }>("SELECT setting_value FROM auth_control_settings WHERE setting_key = $1", [key]);
    return typeof result.rows[0]?.setting_value === "boolean" ? result.rows[0].setting_value : fallback;
  } catch { return fallback; }
}

export function stored<T>(settings: Map<string, unknown>, key: string, fallback: T): T {
  return settings.has(key) ? settings.get(key) as T : fallback;
}

export async function resolveSecretReference(settings: Map<string, unknown>, key: string, fallbackEnvironmentName: string): Promise<string | undefined> {
  const reference = stored(settings, key, fallbackEnvironmentName);
  if (typeof reference !== "string" || !reference) return undefined;
  try {
    return await secretStore().get(reference);
  } catch {
    console.warn(`[NIU Auth] Secret reference unavailable: ${reference}`);
    return undefined;
  }
}
