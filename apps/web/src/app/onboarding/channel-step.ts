export type CustomerChannel = "web" | "mobile";

/**
 * Resolve the channel for the branch the server has asked the wizard to show.
 * The combined web_mobile selection is intentionally not used here: after the
 * web pass the server returns mobile_app and that must create the second client.
 */
export function channelForOnboardingStep(step: string): CustomerChannel | null {
  if (step === "web_storefront") return "web";
  if (step === "mobile_app") return "mobile";
  return null;
}
