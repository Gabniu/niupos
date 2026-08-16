import { headers } from "next/headers";
import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authDatabase } from "@/lib/database";

async function administrator() {
  const session = await auth.api.getSession({ headers: await headers() });
  const role = session?.user && "role" in session.user ? String(session.user.role ?? "") : "";
  return session && role.split(",").includes("admin") ? session : null;
}

export async function GET(request: Request) {
  if (!await administrator()) return NextResponse.json({ message: "Not found." }, { status: 404 });
  const url = new URL(request.url);
  const limit = Math.min(Math.max(Number(url.searchParams.get("limit") ?? 50) || 50, 1), 200);
  const offset = Math.max(Number(url.searchParams.get("offset") ?? 0) || 0, 0);
  try {
    const result = await authDatabase.query<{
      id: string;
      actor_user_id: string | null;
      event_type: string;
      subject_key: string;
      previous_value: unknown;
      next_value: unknown;
      occurred_at: Date;
    }>("SELECT id, actor_user_id, event_type, subject_key, previous_value, next_value, occurred_at FROM auth_control_audit ORDER BY occurred_at DESC, id DESC LIMIT $1 OFFSET $2", [limit, offset]);
    return NextResponse.json({ data: result.rows.map((row) => ({ ...row, occurredAt: row.occurred_at.toISOString() })), limit, offset });
  } catch {
    return NextResponse.json({ message: "Audit storage is unavailable. Apply its migration first." }, { status: 503 });
  }
}
