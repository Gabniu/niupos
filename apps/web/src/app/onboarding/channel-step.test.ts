import test from "node:test";
import assert from "node:assert/strict";
import { channelForOnboardingStep } from "./channel-step.ts";

test("maps the server-derived onboarding branches to the correct client channel", () => {
  assert.equal(channelForOnboardingStep("web_storefront"), "web");
  assert.equal(channelForOnboardingStep("mobile_app"), "mobile");
  assert.equal(channelForOnboardingStep("ready"), null);
});
