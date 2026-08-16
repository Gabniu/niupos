"use client";

export const dynamic = "force-dynamic";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

export default function ResetPasswordPage() {
  const [token, setToken] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [complete, setComplete] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { const timer = window.setTimeout(() => setToken(new URLSearchParams(window.location.search).get("token")), 0); return () => window.clearTimeout(timer); }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) { setError("This recovery link is invalid or incomplete."); return; }
    setPending(true); setError(null);
    const form = new FormData(event.currentTarget);
    const { error: resetError } = await authClient.resetPassword({ token, newPassword: String(form.get("password") ?? "") });
    setPending(false);
    if (resetError) { setError(resetError.message ?? "Password reset failed."); return; }
    setComplete(true);
  }

  return <AuthFrame eyebrow="Account recovery" title="Choose a new password" description="Use a strong password that you do not reuse elsewhere." footer={<Link className="font-medium text-emerald-700" href="/sign-in">Back to sign in</Link>}>
    {complete ? <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">Password updated. You can now sign in.</p> : <form className="space-y-4" onSubmit={submit}><Field label="New password" name="password" type="password" autoComplete="new-password" required minLength={12} />{error && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}<button className={primaryButton} disabled={pending || !token} type="submit">{pending ? "Updating…" : "Update password"}</button></form>}
  </AuthFrame>;
}
