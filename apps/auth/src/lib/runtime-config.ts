const splitOrigins = (value: string | undefined): string[] =>
  (value ?? "")
    .split(",")
    .map((origin) => origin.trim())
    .filter(Boolean);

export const authRuntimeConfig = {
  baseUrl: process.env.BETTER_AUTH_URL,
  publicSignUpEnabled: process.env.AUTH_ALLOW_PUBLIC_SIGN_UP === "true",
  bootstrapEnabled: Boolean(process.env.AUTH_BOOTSTRAP_TOKEN),
  trustedOrigins: splitOrigins(process.env.AUTH_TRUSTED_ORIGINS),
};

export function assertProductionAuthConfiguration(): void {
  if (process.env.NODE_ENV !== "production") return;

  const missing = [
    ["AUTH_DATABASE_URL", process.env.AUTH_DATABASE_URL],
    ["BETTER_AUTH_SECRET", process.env.BETTER_AUTH_SECRET],
    ["BETTER_AUTH_URL", process.env.BETTER_AUTH_URL],
  ].filter(([, value]) => !value).map(([name]) => name);

  if (missing.length > 0) {
    throw new Error(`Missing production authentication configuration: ${missing.join(", ")}`);
  }
}
