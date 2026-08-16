type AuthEmail = {
  to: string;
  subject: string;
  text: string;
};

type EmailDeliveryProvider = "webhook" | "resend";

let deliveryConfiguration: {
  provider?: EmailDeliveryProvider;
  endpoint?: string;
  token?: string;
  apiKey?: string;
  from?: string;
} = {};

export function configureAuthEmail(configuration: {
  provider?: EmailDeliveryProvider;
  endpoint?: string;
  token?: string;
  apiKey?: string;
  from?: string;
}): void {
  deliveryConfiguration = configuration;
}

export async function sendAuthEmail(message: AuthEmail): Promise<void> {
  const provider = deliveryConfiguration.provider ?? (process.env.AUTH_MAIL_PROVIDER === "resend" ? "resend" : "webhook");

  if (provider === "resend") {
    const apiKey = deliveryConfiguration.apiKey ?? process.env.AUTH_RESEND_API_KEY;
    const from = deliveryConfiguration.from ?? process.env.AUTH_MAIL_FROM;
    if (!apiKey || !from) throw new Error("Resend email delivery is not configured.");

    const response = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
        "User-Agent": "NIU-Auth/1.0",
      },
      body: JSON.stringify({ from, to: [message.to], subject: message.subject, text: message.text }),
    });
    if (!response.ok) throw new Error("Resend email delivery failed.");
    return;
  }

  const endpoint = deliveryConfiguration.endpoint ?? process.env.AUTH_MAIL_WEBHOOK_URL;
  if (!endpoint) throw new Error("Authentication email delivery is not configured.");

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(deliveryConfiguration.token ?? process.env.AUTH_MAIL_WEBHOOK_TOKEN
        ? { Authorization: `Bearer ${deliveryConfiguration.token ?? process.env.AUTH_MAIL_WEBHOOK_TOKEN}` }
        : {}),
    },
    body: JSON.stringify(message),
  });

  if (!response.ok) throw new Error("Authentication email delivery failed.");
}
