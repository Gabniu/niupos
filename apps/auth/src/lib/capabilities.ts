export type CapabilityStatus = "enabled" | "available" | "planned";

export const betterAuthCapabilities = [
  ["Email and password", "Email/password sign-in, verification, and recovery", "enabled"],
  ["Administrator", "User lifecycle, roles, bans, impersonation, and sessions", "enabled"],
  ["Organizations", "Organizations, members, invitations, and active context", "enabled"],
  ["Two-factor authentication", "TOTP, OTP delivery, trusted devices, and backup codes", "enabled"],
  ["OAuth 2.1 / OpenID Connect provider", "Authorization code + PKCE, tokens, consent, discovery, and userinfo", "enabled"],
  ["Passkeys", "WebAuthn passwordless authentication", "available"],
  ["Magic link", "Passwordless email authentication", "available"],
  ["Email OTP", "Email-delivered one-time codes", "available"],
  ["Social and generic OAuth", "Sign in through external identity providers", "available"],
  ["SSO / SAML", "Enterprise OIDC and SAML connections", "planned"],
  ["SCIM", "Directory provisioning and deprovisioning", "planned"],
  ["API keys", "Machine credentials with scoped access", "planned"],
  ["JWT and bearer resource verification", "Resource-server token verification", "planned"],
  ["Multi-session", "Concurrent account sessions and account selection", "planned"],
  ["Device authorization", "Device-code authentication for constrained clients", "planned"],
] as const satisfies readonly [string, string, CapabilityStatus][];
