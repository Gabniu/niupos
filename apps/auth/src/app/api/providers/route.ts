import { NextResponse } from "next/server";

import { loadGoogleProviderConfiguration } from "@/lib/social-providers";

export async function GET(): Promise<NextResponse> {
  const google = await loadGoogleProviderConfiguration();
  return NextResponse.json({ google: google.enabled && Boolean(google.clientId && google.clientSecret) });
}
