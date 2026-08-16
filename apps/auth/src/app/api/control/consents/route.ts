import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authDatabase } from "@/lib/database";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

export async function GET() {
  if (!await administrator()) return NextResponse.json({ message: "Not found." }, { status: 404 });
  try {
    const result = await authDatabase.query<{ id: string; user_id: string; client_id: string; scopes: string[]; created_at: Date; client_name: string | null }>(`SELECT c.id, c."userId" AS user_id, c."clientId" AS client_id, c.scopes, c."createdAt" AS created_at, oc."clientName" AS client_name FROM "oauthConsent" c LEFT JOIN "oauthClient" oc ON oc."clientId" = c."clientId" ORDER BY c."createdAt" DESC`);
    return NextResponse.json({ data: result.rows.map((row) => ({ ...row, createdAt: row.created_at.toISOString() })) });
  } catch {
    return NextResponse.json({ message: "Consent storage is unavailable. Apply the Better Auth OAuth migration first." }, { status: 503 });
  }
}
