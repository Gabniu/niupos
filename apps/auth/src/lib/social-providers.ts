import { loadStoredSettings, resolveSecretReference, stored } from "@/lib/control-plane-runtime";
import type { GoogleProviderConfiguration } from "@/lib/social-provider-config";

export async function loadGoogleProviderConfiguration(): Promise<GoogleProviderConfiguration> {
  const settings = await loadStoredSettings();
  const enabled = stored(settings, "social.google.enabled", process.env.AUTH_GOOGLE_ENABLED === "true");
  const clientId = stored(settings, "social.google.clientId", process.env.GOOGLE_CLIENT_ID ?? "");
  const clientSecret = await resolveSecretReference(settings, "social.google.clientSecret", "GOOGLE_CLIENT_SECRET");
  const hostedDomain = stored(settings, "social.google.hostedDomain", process.env.GOOGLE_HOSTED_DOMAIN ?? "");
  return { enabled, clientId, clientSecret: clientSecret ?? "", hostedDomain };
}

export { assertGoogleProviderConfiguration } from "@/lib/social-provider-config";
