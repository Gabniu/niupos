import assert from "node:assert/strict";
import test from "node:test";

import { definitionByKey, validateSettingValue } from "./control-plane.ts";

test("secret settings accept references but reject raw secret-shaped values", () => {
  const definition = definitionByKey.get("delivery.webhookToken");
  assert.ok(definition);
  assert.equal(validateSettingValue(definition, "AUTH_MAIL_WEBHOOK_TOKEN"), "AUTH_MAIL_WEBHOOK_TOKEN");
  assert.throws(() => validateSettingValue(definition, "actual-secret-value"));
});

test("bounded numeric settings reject unsafe values", () => {
  const definition = definitionByKey.get("password.minimumLength");
  assert.ok(definition);
  assert.equal(validateSettingValue(definition, 12), 12);
  assert.throws(() => validateSettingValue(definition, 4));
});

test("email delivery provider is limited to supported transports", () => {
  const definition = definitionByKey.get("delivery.provider");
  assert.ok(definition);
  assert.equal(validateSettingValue(definition, "resend"), "resend");
  assert.equal(validateSettingValue(definition, "webhook"), "webhook");
  assert.throws(() => validateSettingValue(definition, "smtp"));
});
