import { oauthProvider } from "@better-auth/oauth-provider";
import { betterAuth } from "better-auth";
import { admin, jwt, organization, twoFactor } from "better-auth/plugins";

import { authDatabase } from "@/lib/database";
import { liveBoolean, loadStoredSettings, resolveSecretReference, stored } from "@/lib/control-plane-runtime";
import { configureAuthEmail, sendAuthEmail } from "@/lib/email";
import { assertProductionAuthConfiguration, authRuntimeConfig } from "@/lib/runtime-config";
import { loadGoogleProviderConfiguration } from "@/lib/social-providers";
import { googleProviderReady } from "@/lib/social-provider-config";

assertProductionAuthConfiguration();

const storedSettings = await loadStoredSettings();
const googleProvider = await loadGoogleProviderConfiguration();
const googleReady = googleProviderReady(googleProvider);
if (googleProvider.enabled && !googleReady) console.warn("[NIU Auth] Google sign-in is enabled but unavailable because its client credentials are incomplete; password sign-in remains available.");
configureAuthEmail({
  provider: stored(storedSettings, "delivery.provider", process.env.AUTH_MAIL_PROVIDER === "resend" ? "resend" : "webhook") === "resend" ? "resend" : "webhook",
  endpoint: await resolveSecretReference(storedSettings, "delivery.webhookUrl", "AUTH_MAIL_WEBHOOK_URL"),
  token: await resolveSecretReference(storedSettings, "delivery.webhookToken", "AUTH_MAIL_WEBHOOK_TOKEN"),
  apiKey: await resolveSecretReference(storedSettings, "delivery.resendApiKey", "AUTH_RESEND_API_KEY"),
  from: stored(storedSettings, "delivery.from", process.env.AUTH_MAIL_FROM ?? ""),
});

export const auth = betterAuth({
  appName: stored(storedSettings, "general.appName", "NIU Auth"),
  baseURL: stored(storedSettings, "general.baseUrl", authRuntimeConfig.baseUrl),
  secret: process.env.BETTER_AUTH_SECRET,
  database: authDatabase,
  trustedOrigins: stored(storedSettings, "general.trustedOrigins", authRuntimeConfig.trustedOrigins),
  ...(googleReady ? {
    socialProviders: {
      google: {
        clientId: googleProvider.clientId,
        clientSecret: googleProvider.clientSecret,
        ...(googleProvider.hostedDomain ? { hd: googleProvider.hostedDomain } : {}),
      },
    },
  } : {}),
  emailAndPassword: {
    enabled: true,
    disableSignUp: !(authRuntimeConfig.bootstrapEnabled || stored(storedSettings, "password.publicSignUp", authRuntimeConfig.publicSignUpEnabled)),
    requireEmailVerification: stored(storedSettings, "password.requireVerifiedEmail", true),
    minPasswordLength: stored(storedSettings, "password.minimumLength", 12),
    maxPasswordLength: stored(storedSettings, "password.maximumLength", 128),
    sendResetPassword: async ({ user, url }) => {
      await sendAuthEmail({
        to: user.email,
        subject: "Reset your NIU Auth password",
        text: `Use this secure link to reset your password: ${url}`,
      });
    },
  },
  emailVerification: {
    sendVerificationEmail: async ({ user, url }) => {
      await sendAuthEmail({
        to: user.email,
        subject: "Verify your NIU Auth email",
        text: `Verify your email address using this secure link: ${url}`,
      });
    },
  },
  session: {
    expiresIn: stored(storedSettings, "session.expiresIn", 60 * 60 * 24 * 7),
    updateAge: stored(storedSettings, "session.updateAge", 60 * 60 * 24),
    cookieCache: { enabled: false },
  },
  advanced: {
    useSecureCookies: process.env.NODE_ENV === "production",
  },
  plugins: [
    admin({ defaultRole: "user", adminRoles: ["admin"] }),
    jwt(),
    organization({ allowUserToCreateOrganization: async (user) => user.role?.split(",").includes("admin") || await liveBoolean("organization.userCreation", false) }),
    twoFactor({ issuer: stored(storedSettings, "twoFactor.issuer", "NIU Auth") }),
    oauthProvider({
      loginPage: "/sign-in",
      consentPage: "/consent",
      silenceWarnings: { oauthAuthServerConfig: true, openidConfig: true },
      allowDynamicClientRegistration: stored(storedSettings, "oauth.dynamicRegistration", false),
      allowUnauthenticatedClientRegistration: stored(storedSettings, "oauth.unauthenticatedRegistration", false),
      scopes: stored(storedSettings, "oauth.scopes", ["openid", "profile", "email", "offline_access"]),
      accessTokenExpiresIn: stored(storedSettings, "oauth.accessTokenLifetime", 3600),
      refreshTokenExpiresIn: stored(storedSettings, "oauth.refreshTokenLifetime", 2592000),
    }),
  ],
});
