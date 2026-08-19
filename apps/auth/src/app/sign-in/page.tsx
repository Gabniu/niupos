"use client";

export const dynamic = "force-dynamic";

import Link from "next/link";
import { FormEvent, Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

function SignInForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [pending, setPending] = useState(false);
  const [googleEnabled, setGoogleEnabled] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const setupComplete = searchParams.get("setup") === "complete";

  useEffect(() => {
    const controller = new AbortController();
    void fetch("/api/providers", { signal: controller.signal, cache: "no-store" }).then(async (response) => {
      if (!response.ok) return;
      const body = await response.json() as { google?: unknown };
      setGoogleEnabled(body.google === true);
    }).catch(() => undefined);
    return () => controller.abort();
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const callbackURL = new URLSearchParams(window.location.search).get("callbackURL") ?? "/admin";
    const { data, error: signInError } = await authClient.signIn.email({
      email: String(form.get("email") ?? ""),
      password: String(form.get("password") ?? ""),
      rememberMe: form.get("remember") === "on",
      callbackURL,
    });
    setPending(false);
    if (signInError) { setError(signInError.message ?? "Sign in failed."); return; }
    if ((data as { twoFactorRedirect?: boolean } | null)?.twoFactorRedirect) { router.push(`/two-factor?callbackURL=${encodeURIComponent(callbackURL)}`); return; }
    router.push(callbackURL);
  }

  async function signInWithGoogle() {
    const callbackURL = new URLSearchParams(window.location.search).get("callbackURL") ?? "/admin";
    setPending(true);
    setError(null);
    const { error: socialError } = await authClient.signIn.social({ provider: "google", callbackURL });
    if (socialError) { setPending(false); setError(socialError.message ?? "Google sign in failed."); }
  }

  return <AuthFrame eyebrow="Secure access" title="Sign in" description="Use one identity across NIU and every connected application." footer={<>Need access? <Link className="font-medium text-emerald-700" href="/sign-up">Create an account</Link></>}>
    <form className="space-y-4" onSubmit={submit}>
      {setupComplete && <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs leading-5 text-emerald-800" role="status">Administrator setup is complete. Sign in with the account you just created.</p>}
      <Field label="Email" name="email" type="email" autoComplete="username" required />
      <Field label="Password" name="password" type="password" autoComplete="current-password" required minLength={8} />
      <div className="flex items-center justify-between gap-4 text-xs"><label className="flex items-center gap-2 text-slate-500"><input className="size-4" name="remember" type="checkbox" />Remember me</label><Link className="font-medium text-emerald-700" href="/forgot-password">Forgot password?</Link></div>
      {error && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}
      <button className={primaryButton} disabled={pending} type="submit">{pending ? "Signing in…" : "Sign in"}</button>
      {googleEnabled && <><div className="flex items-center gap-3 py-1 text-[10px] uppercase tracking-[0.16em] text-slate-400"><span className="h-px flex-1 bg-slate-200" />or<span className="h-px flex-1 bg-slate-200" /></div><button className="flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" disabled={pending} onClick={() => void signInWithGoogle()} type="button"><span aria-hidden="true" className="text-base font-semibold">G</span>Continue with Google</button></>}
    </form>
  </AuthFrame>;
}

export default function SignInPage() {
  return <Suspense fallback={<AuthFrame eyebrow="Secure access" title="Sign in" description="Use one identity across NIU and every connected application." footer={<>Loading sign in…</>}><div className="h-48" /></AuthFrame>}><SignInForm /></Suspense>;
}
