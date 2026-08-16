import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authRuntimeConfig } from "@/lib/runtime-config";
import { issueSecretStepUpToken, SECRET_STEP_UP_COOKIE, SECRET_STEP_UP_TTL_SECONDS } from "@/lib/secret-elevation";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

function sameOrigin(request: Request): boolean {
  const origin = request.headers.get("origin");
  if (!origin) return false;
  return new Set([authRuntimeConfig.baseUrl, ...authRuntimeConfig.trustedOrigins].filter(Boolean)).has(origin);
}

export async function POST(request: Request) {
  const session = await administrator();
  if (!session) return NextResponse.json({ message: "Not found." }, { status: 404 });
  if (!sameOrigin(request) || !request.headers.get("content-type")?.toLowerCase().includes("application/json")) {
    return NextResponse.json({ message: "Invalid MFA elevation request." }, { status: 422 });
  }
  const body = await request.json().catch(() => null) as { code?: unknown } | null;
  if (!body || typeof body.code !== "string" || !/^\d{6}$/.test(body.code)) {
    return NextResponse.json({ message: "Enter the six-digit authenticator code." }, { status: 422 });
  }
  const twoFactorEnabled = "twoFactorEnabled" in session.user && session.user.twoFactorEnabled === true;
  if (!twoFactorEnabled) return NextResponse.json({ message: "Enable MFA on this administrator account before changing secrets." }, { status: 403 });

  try {
    await auth.api.verifyTOTP({ body: { code: body.code, trustDevice: false }, headers: await headers() });
    const response = NextResponse.json({ data: { elevated: true, expiresIn: SECRET_STEP_UP_TTL_SECONDS } });
    response.cookies.set(SECRET_STEP_UP_COOKIE, issueSecretStepUpToken(session.user.id), {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "strict",
      path: "/api/control",
      maxAge: SECRET_STEP_UP_TTL_SECONDS,
    });
    return response;
  } catch {
    return NextResponse.json({ message: "MFA verification failed." }, { status: 401 });
  }
}
