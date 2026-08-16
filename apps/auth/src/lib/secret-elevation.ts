import { createHmac, timingSafeEqual } from "node:crypto";

export const SECRET_STEP_UP_COOKIE = "nova_secret_step_up";
export const SECRET_STEP_UP_TTL_SECONDS = 600;

function signingSecret(): string {
  const secret = process.env.BETTER_AUTH_SECRET;
  if (!secret) throw new Error("Authentication signing secret is unavailable.");
  return secret;
}

function encode(value: string): string {
  return Buffer.from(value, "utf8").toString("base64url");
}

function signature(payload: string): string {
  return createHmac("sha256", signingSecret()).update(payload).digest("base64url");
}

export function issueSecretStepUpToken(userId: string, now = Date.now()): string {
  const payload = encode(JSON.stringify({ sub: userId, exp: Math.floor(now / 1000) + SECRET_STEP_UP_TTL_SECONDS }));
  return `${payload}.${signature(payload)}`;
}

export function verifySecretStepUpToken(token: string | undefined, userId: string, now = Date.now()): boolean {
  if (!token) return false;
  const [payload, provided] = token.split(".");
  if (!payload || !provided) return false;
  const expected = signature(payload);
  const expectedBytes = Buffer.from(expected);
  const providedBytes = Buffer.from(provided);
  if (expectedBytes.length !== providedBytes.length || !timingSafeEqual(expectedBytes, providedBytes)) return false;
  try {
    const data = JSON.parse(Buffer.from(payload, "base64url").toString("utf8")) as { sub?: unknown; exp?: unknown };
    return data.sub === userId && typeof data.exp === "number" && data.exp >= Math.floor(now / 1000);
  } catch {
    return false;
  }
}
