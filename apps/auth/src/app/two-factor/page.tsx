"use client";

export const dynamic = "force-dynamic";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

export default function TwoFactorPage() {
  const router = useRouter();
  const [callbackURL, setCallbackURL] = useState("/admin");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { const timer = window.setTimeout(() => setCallbackURL(new URLSearchParams(window.location.search).get("callbackURL") ?? "/admin"), 0); return () => window.clearTimeout(timer); }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setPending(true); setError(null);
    const form = new FormData(event.currentTarget);
    const { error: verifyError } = await authClient.twoFactor.verifyTotp({ code: String(form.get("code") ?? ""), trustDevice: form.get("trust") === "on" });
    setPending(false);
    if (verifyError) { setError(verifyError.message ?? "Verification failed."); return; }
    router.push(callbackURL);
  }

  return <AuthFrame eyebrow="Two-factor authentication" title="Verify your identity" description="Enter the six-digit code from your authenticator application.">
    <form className="space-y-4" onSubmit={submit}><Field label="Authentication code" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required /><label className="flex items-center gap-2 text-xs text-slate-500"><input className="size-4" name="trust" type="checkbox" />Trust this device for 30 days</label>{error && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}<button className={primaryButton} disabled={pending} type="submit">{pending ? "Verifying…" : "Verify"}</button></form>
  </AuthFrame>;
}
