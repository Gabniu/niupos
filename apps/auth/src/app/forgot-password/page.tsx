"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

export default function ForgotPasswordPage() {
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setPending(true);
    const form = new FormData(event.currentTarget);
    await authClient.requestPasswordReset({ email: String(form.get("email") ?? ""), redirectTo: "/reset-password" });
    setPending(false); setSent(true);
  }

  return <AuthFrame eyebrow="Account recovery" title="Reset your password" description="We will send recovery instructions if the address belongs to an account." footer={<Link className="font-medium text-emerald-700" href="/sign-in">Back to sign in</Link>}>
    {sent ? <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">If the account exists, recovery instructions have been sent.</p> : <form className="space-y-4" onSubmit={submit}><Field label="Email" name="email" type="email" autoComplete="email" required /><button className={primaryButton} disabled={pending} type="submit">{pending ? "Sending…" : "Send recovery link"}</button></form>}
  </AuthFrame>;
}
