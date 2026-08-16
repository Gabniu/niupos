import { createHash } from "node:crypto";

import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authSettingDefinitions } from "@/lib/control-plane";
import { loadStoredSettings, stored } from "@/lib/control-plane-runtime";
import { authDatabase } from "@/lib/database";
import { authRuntimeConfig } from "@/lib/runtime-config";
import { SECRET_STEP_UP_COOKIE, verifySecretStepUpToken } from "@/lib/secret-elevation";
import { secretStore } from "@/lib/secret-store";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

function sameOrigin(request: Request): boolean {
  const origin = request.headers.get("origin");
  if (!origin) return false;
  const allowed = new Set([authRuntimeConfig.baseUrl, ...authRuntimeConfig.trustedOrigins].filter(Boolean));
  return allowed.has(origin);
}

export async function POST(request: Request) {
  const session = await administrator();
  if (!session) return NextResponse.json({ message: "Not found." }, { status: 404 });
  const elevation = request.headers.get("cookie")?.split(";").map((part) => part.trim()).find((part) => part.startsWith(`${SECRET_STEP_UP_COOKIE}=`))?.slice(SECRET_STEP_UP_COOKIE.length + 1);
  if (!verifySecretStepUpToken(elevation, session.user.id)) return NextResponse.json({ message: "MFA elevation is required before changing secrets." }, { status: 403 });
  if (process.env.AUTH_SECRET_STORE !== "infisical" || process.env.AUTH_SECRET_STORE_WRITE_ENABLED !== "true") {
    return NextResponse.json({ message: "Secure secret storage is not enabled." }, { status: 503 });
  }
  if (!sameOrigin(request) || !request.headers.get("content-type")?.toLowerCase().includes("application/json")) {
    return NextResponse.json({ message: "Invalid secret update request." }, { status: 422 });
  }

  const body = await request.json().catch(() => null) as { key?: unknown; value?: unknown } | null;
  if (!body || typeof body.key !== "string" || typeof body.value !== "string" || !body.value || body.value.length > 16_384) {
    return NextResponse.json({ message: "Invalid secret update." }, { status: 422 });
  }
  const definition = authSettingDefinitions.find((candidate) => candidate.key === body.key && candidate.mode === "secret-reference");
  if (!definition) return NextResponse.json({ message: "Unknown secret setting." }, { status: 422 });

  const settings = await loadStoredSettings();
  const reference = stored(settings, definition.key, definition.defaultValue);
  if (typeof reference !== "string" || !reference) return NextResponse.json({ message: "Secret reference is not configured." }, { status: 503 });

  try {
    const writer = secretStore().set;
    if (!writer) throw new Error("Secret store is read-only.");
    await writer(reference, body.value);
    const fingerprint = createHash("sha256").update(body.value).digest("hex");
    await authDatabase.query(
      "INSERT INTO auth_control_audit (actor_user_id, event_type, subject_key, previous_value, next_value) VALUES ($1, 'auth.secret.updated', $2, NULL, $3::jsonb)",
      [session.user.id, definition.key, JSON.stringify({ stored: true, fingerprint })],
    );
    return NextResponse.json({ data: { key: definition.key, saved: true } });
  } catch {
    return NextResponse.json({ message: "The secret could not be saved." }, { status: 503 });
  }
}
