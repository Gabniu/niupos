export type SettingMode = "live" | "restart" | "secret-reference";
export type SettingValue = string | number | boolean | string[];

export type AuthSettingDefinition = {
  key: string;
  category: "General" | "Email and password" | "Sessions" | "Organizations" | "Two factor" | "OAuth and OIDC" | "Social sign-in" | "Delivery";
  label: string;
  description: string;
  mode: SettingMode;
  type: "string" | "number" | "boolean" | "string-list";
  defaultValue: SettingValue;
  minimum?: number;
  maximum?: number;
};

export const authSettingDefinitions: readonly AuthSettingDefinition[] = [
  { key: "general.appName", category: "General", label: "Application name", description: "Name displayed by the identity provider.", mode: "restart", type: "string", defaultValue: "NIU Auth" },
  { key: "general.baseUrl", category: "General", label: "Issuer base URL", description: "Canonical HTTPS origin used in cookies and OIDC metadata.", mode: "restart", type: "string", defaultValue: "" },
  { key: "general.trustedOrigins", category: "General", label: "Trusted origins", description: "Exact browser origins allowed to call the authentication service.", mode: "restart", type: "string-list", defaultValue: [] },
  { key: "password.publicSignUp", category: "Email and password", label: "Public registration", description: "Allow users to create their own identity account.", mode: "restart", type: "boolean", defaultValue: false },
  { key: "password.requireVerifiedEmail", category: "Email and password", label: "Require verified email", description: "Require email verification before password sign-in completes.", mode: "restart", type: "boolean", defaultValue: true },
  { key: "password.minimumLength", category: "Email and password", label: "Minimum password length", description: "Minimum accepted password length.", mode: "restart", type: "number", defaultValue: 12, minimum: 8, maximum: 128 },
  { key: "password.maximumLength", category: "Email and password", label: "Maximum password length", description: "Maximum accepted password length.", mode: "restart", type: "number", defaultValue: 128, minimum: 32, maximum: 512 },
  { key: "session.expiresIn", category: "Sessions", label: "Session lifetime (seconds)", description: "Absolute session lifetime.", mode: "restart", type: "number", defaultValue: 604800, minimum: 300, maximum: 31536000 },
  { key: "session.updateAge", category: "Sessions", label: "Session refresh age (seconds)", description: "Minimum age before session expiry is refreshed.", mode: "restart", type: "number", defaultValue: 86400, minimum: 60, maximum: 2592000 },
  { key: "organization.userCreation", category: "Organizations", label: "User-created organizations", description: "Permit non-administrators to create organizations.", mode: "live", type: "boolean", defaultValue: false },
  { key: "twoFactor.issuer", category: "Two factor", label: "Authenticator issuer", description: "Issuer shown by authenticator applications.", mode: "restart", type: "string", defaultValue: "NIU Auth" },
  { key: "twoFactor.trustedDeviceDays", category: "Two factor", label: "Trusted-device lifetime (days)", description: "How long a verified device can bypass a second prompt.", mode: "restart", type: "number", defaultValue: 30, minimum: 0, maximum: 365 },
  { key: "oauth.dynamicRegistration", category: "OAuth and OIDC", label: "Dynamic client registration", description: "Allow authenticated callers to register OAuth clients dynamically.", mode: "restart", type: "boolean", defaultValue: false },
  { key: "oauth.unauthenticatedRegistration", category: "OAuth and OIDC", label: "Unauthenticated registration", description: "Allow public callers to register clients. Keep disabled unless explicitly required.", mode: "restart", type: "boolean", defaultValue: false },
  { key: "oauth.scopes", category: "OAuth and OIDC", label: "Advertised scopes", description: "OIDC and application scopes advertised by the provider.", mode: "restart", type: "string-list", defaultValue: ["openid", "profile", "email", "offline_access"] },
  { key: "oauth.accessTokenLifetime", category: "OAuth and OIDC", label: "Access-token lifetime (seconds)", description: "Lifetime of issued OAuth access tokens.", mode: "restart", type: "number", defaultValue: 3600, minimum: 60, maximum: 86400 },
  { key: "oauth.refreshTokenLifetime", category: "OAuth and OIDC", label: "Refresh-token lifetime (seconds)", description: "Lifetime of issued refresh tokens.", mode: "restart", type: "number", defaultValue: 2592000, minimum: 3600, maximum: 31536000 },
  { key: "social.google.enabled", category: "Social sign-in", label: "Google sign-in", description: "Show Google as an upstream sign-in option in NIU Auth.", mode: "restart", type: "boolean", defaultValue: false },
  { key: "social.google.clientId", category: "Social sign-in", label: "Google client ID", description: "OAuth web client ID issued by Google Cloud for this Auth origin.", mode: "restart", type: "string", defaultValue: "" },
  { key: "social.google.clientSecret", category: "Social sign-in", label: "Google client secret reference", description: "Secret-store reference containing the Google OAuth client secret; the value is never stored here.", mode: "secret-reference", type: "string", defaultValue: "GOOGLE_CLIENT_SECRET" },
  { key: "social.google.hostedDomain", category: "Social sign-in", label: "Google Workspace domain restriction", description: "Optional verified Google Workspace domain (for example, example.com). Leave blank for consumer and multi-domain accounts.", mode: "restart", type: "string", defaultValue: "" },
  { key: "delivery.provider", category: "Delivery", label: "Email delivery provider", description: "Transactional email transport. Resend requires a server-side API-key reference and verified sender.", mode: "restart", type: "string", defaultValue: "webhook" },
  { key: "delivery.from", category: "Delivery", label: "Email sender", description: "Verified Resend sender address, including an optional display name.", mode: "restart", type: "string", defaultValue: "" },
  { key: "delivery.resendApiKey", category: "Delivery", label: "Resend API key reference", description: "Secret-store reference containing the Resend API key; the key itself is never stored here.", mode: "secret-reference", type: "string", defaultValue: "AUTH_RESEND_API_KEY" },
  { key: "delivery.webhookUrl", category: "Delivery", label: "Mail webhook URL", description: "Secret-store reference containing the transactional-mail webhook URL.", mode: "secret-reference", type: "string", defaultValue: "" },
  { key: "delivery.webhookToken", category: "Delivery", label: "Mail webhook token", description: "Secret-store reference containing the webhook bearer token.", mode: "secret-reference", type: "string", defaultValue: "" },
] as const;

export const definitionByKey = new Map(authSettingDefinitions.map((definition) => [definition.key, definition]));

export function validateSettingValue(definition: AuthSettingDefinition, value: unknown): SettingValue {
  if (definition.key === "delivery.provider") {
    if (value === "webhook" || value === "resend") return value;
    throw new Error("The setting value is invalid.");
  }
  if (definition.type === "boolean" && typeof value === "boolean") return value;
  if (definition.type === "string" && typeof value === "string" && value.length <= 2048) {
    if (definition.mode === "secret-reference" && value !== "" && !/^[A-Z][A-Z0-9_]{2,100}$/.test(value)) throw new Error("Secret references must be uppercase environment-style names.");
    return value;
  }
  if (definition.type === "string-list" && Array.isArray(value) && value.length <= 100 && value.every((item) => typeof item === "string" && item.length <= 2048)) return value;
  if (definition.type === "number" && typeof value === "number" && Number.isFinite(value) && (definition.minimum === undefined || value >= definition.minimum) && (definition.maximum === undefined || value <= definition.maximum)) return value;
  throw new Error("The setting value is invalid.");
}
