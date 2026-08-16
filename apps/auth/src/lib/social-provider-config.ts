export type GoogleProviderConfiguration = {
  enabled: boolean;
  clientId: string;
  clientSecret: string;
  hostedDomain?: string;
};

export function googleProviderReady(configuration: GoogleProviderConfiguration): boolean {
  return configuration.enabled && Boolean(configuration.clientId && configuration.clientSecret);
}

export function assertGoogleProviderConfiguration(configuration: GoogleProviderConfiguration): void {
  if (configuration.enabled && (!configuration.clientId || !configuration.clientSecret)) {
    throw new Error("Google sign-in is enabled but its client credentials are missing.");
  }
}
