"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

export default function SignUpPage() {
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setPending(true); setError(null); setMessage(null);
    const form = new FormData(event.currentTarget);
    const { error: signUpError } = await authClient.signUp.email({ name: String(form.get("name") ?? ""), email: String(form.get("email") ?? ""), password: String(form.get("password") ?? ""), callbackURL: "/admin" });
    setPending(false);
    if (signUpError) { setError(signUpError.message ?? "Account creation is unavailable."); return; }
    setMessage("Check your email to verify the account before signing in.");
  }

  return <AuthFrame eyebrow="Account access" title="Create an identity" description="Registration is available only when enabled by the platform administrator." footer={<>Already registered? <Link className="font-medium text-emerald-700" href="/sign-in">Sign in</Link></>}>
    <form className="space-y-4" onSubmit={submit}>
      <Field label="Full name" name="name" autoComplete="name" required maxLength={100} />
      <Field label="Email" name="email" type="email" autoComplete="email" required />
      <Field label="Password" name="password" type="password" autoComplete="new-password" required minLength={12} />
      {message && <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">{message}</p>}
      {error && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}
      <button className={primaryButton} disabled={pending} type="submit">{pending ? "Creating…" : "Create account"}</button>
    </form>
  </AuthFrame>;
}
