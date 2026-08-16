import { timingSafeEqual } from "node:crypto";

import { NextResponse } from "next/server";

import { auth } from "@/lib/auth";
import { authDatabase } from "@/lib/database";

function validBootstrapToken(request: Request): boolean {
  const configured = process.env.AUTH_BOOTSTRAP_TOKEN;
  const supplied = request.headers.get("x-bootstrap-token");
  if (!configured || !supplied || configured.length < 32 || supplied.length !== configured.length) return false;
  return timingSafeEqual(Buffer.from(supplied), Buffer.from(configured));
}

export async function GET(request: Request) {
  if (!validBootstrapToken(request)) return NextResponse.json({ message: "Bootstrap is unavailable." }, { status: 404 });
  try {
    const result = await authDatabase.query<{ count: number }>('SELECT COUNT(*)::int AS count FROM "user"');
    return NextResponse.json({ available: result.rows[0]?.count === 0 });
  } catch {
    return NextResponse.json({ message: "Identity storage is unavailable. Apply the Better Auth migration first." }, { status: 503 });
  }
}

export async function POST(request: Request) {
  if (!validBootstrapToken(request)) return NextResponse.json({ message: "Bootstrap is unavailable." }, { status: 404 });
  const body = await request.json().catch(() => null) as { name?: unknown; email?: unknown; password?: unknown } | null;
  const name = typeof body?.name === "string" ? body.name.trim() : "";
  const email = typeof body?.email === "string" ? body.email.trim().toLowerCase() : "";
  const password = typeof body?.password === "string" ? body.password : "";
  if (name.length < 2 || name.length > 100 || !/^\S+@\S+\.\S+$/.test(email) || password.length < 12 || password.length > 128) {
    return NextResponse.json({ message: "Provide a valid name, email, and password of 12–128 characters." }, { status: 422 });
  }

  const client = await authDatabase.connect();
  try {
    await client.query("BEGIN");
    await client.query("SELECT pg_advisory_xact_lock(hashtext('nova-auth-bootstrap'))");
    const existing = await client.query<{ count: number }>('SELECT COUNT(*)::int AS count FROM "user"');
    if ((existing.rows[0]?.count ?? 0) > 0) {
      await client.query("ROLLBACK");
      return NextResponse.json({ message: "Bootstrap is already complete." }, { status: 409 });
    }
    const created = await auth.api.signUpEmail({ body: { name, email, password } });
    const userId = (created as { user?: { id?: string } }).user?.id;
    if (!userId) throw new Error("Bootstrap did not return a user.");
    await client.query('UPDATE "user" SET role = $1, "emailVerified" = TRUE WHERE id = $2', ["admin", userId]);
    await client.query("COMMIT");
    return NextResponse.json({ data: { email, role: "admin" } }, { status: 201 });
  } catch (error) {
    await client.query("ROLLBACK").catch(() => undefined);
    const message = error instanceof Error && /already exists|duplicate/i.test(error.message) ? "Bootstrap is already complete." : "The administrator could not be created.";
    return NextResponse.json({ message }, { status: message === "Bootstrap is already complete." ? 409 : 422 });
  } finally {
    client.release();
  }
}
