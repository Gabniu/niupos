import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authRuntimeConfig } from "@/lib/runtime-config";
import { SECRET_STEP_UP_COOKIE, verifySecretStepUpToken } from "@/lib/secret-elevation";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

function sameOrigin(request: Request): boolean {
  const origin = request.headers.get("origin");
  return Boolean(origin && new Set([authRuntimeConfig.baseUrl, ...authRuntimeConfig.trustedOrigins].filter(Boolean)).has(origin));
}

function elevationCookie(request: Request): string | undefined {
  return request.headers.get("cookie")?.split(";").map((part) => part.trim()).find((part) => part.startsWith(`${SECRET_STEP_UP_COOKIE}=`))?.slice(SECRET_STEP_UP_COOKIE.length + 1);
}

export async function POST(request: Request) {
  const session = await administrator();
  if (!session) return NextResponse.json({ message: "Not found." }, { status: 404 });
  if (!sameOrigin(request) || !request.headers.get("content-type")?.toLowerCase().includes("application/json")) {
    return NextResponse.json({ message: "Invalid client-secret rotation request." }, { status: 422 });
  }
  if (!verifySecretStepUpToken(elevationCookie(request), session.user.id)) {
    return NextResponse.json({ message: "MFA elevation is required before rotating a client secret." }, { status: 403 });
  }
  const body = await request.json().catch(() => null) as { client_id?: unknown } | null;
  if (!body || typeof body.client_id !== "string" || !body.client_id.trim() || body.client_id.length > 255) {
    return NextResponse.json({ message: "Invalid client." }, { status: 422 });
  }
  try {
    const result = await auth.api.rotateClientSecret({ body: { client_id: body.client_id }, headers: await headers() });
    return NextResponse.json({ data: result }, { headers: { "Cache-Control": "no-store" } });
  } catch {
    return NextResponse.json({ message: "The client secret could not be rotated." }, { status: 503 });
  }
}
