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
    const result = await authDatabase.query<{ id: string; name: string; slug: string; created_at: Date; member_count: number }>(`SELECT o.id, o.name, o.slug, o."createdAt" AS created_at, COUNT(m.id)::int AS member_count FROM "organization" o LEFT JOIN "member" m ON m."organizationId" = o.id GROUP BY o.id, o.name, o.slug, o."createdAt" ORDER BY o."createdAt" DESC`);
    return NextResponse.json({ data: result.rows.map((row) => ({ ...row, createdAt: row.created_at.toISOString() })) });
  } catch {
    return NextResponse.json({ message: "Organization storage is unavailable. Apply the Better Auth organization migration first." }, { status: 503 });
  }
}
