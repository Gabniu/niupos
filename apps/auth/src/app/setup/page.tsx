"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";

import { AuthFrame } from "@/components/auth-frame";
import { Field, primaryButton } from "@/components/field";

type SetupState = "idle" | "pending" | "complete";

export default function SetupPage() {
  const router = useRouter();
  const [state, setState] = useState<SetupState>("idle");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (state !== "complete") return;
    const redirect = window.setTimeout(
      () => router.replace("/sign-in?setup=complete"),
      1800,
    );
    return () => window.clearTimeout(redirect);
  }, [router, state]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (state === "pending") return;
    setState("pending");
    setMessage(null);
    setError(null);

    const form = new FormData(event.currentTarget);
    const token = String(form.get("bootstrapToken") ?? "");
    try {
      const response = await fetch("/api/bootstrap", {
        method: "POST",
        headers: {
          "content-type": "application/json",
          "x-bootstrap-token": token,
        },
        body: JSON.stringify({
          name: form.get("name"),
          email: form.get("email"),
          password: form.get("password"),
        }),
      });
      const body = (await response.json().catch(() => ({}))) as {
        data?: { email?: string };
        message?: string;
      };

      if (response.status === 409) {
        setState("complete");
        setMessage(
          "Setup is already complete. The administrator may have been created by an earlier request. Redirecting you to sign in…",
        );
        return;
      }
      if (!response.ok) {
        setState("idle");
        setError(body.message ?? "Administrator setup failed. Please try again.");
        return;
      }

      setState("complete");
      setMessage(
        `Administrator ${body.data?.email ?? "account"} created. Remove AUTH_BOOTSTRAP_TOKEN and restart the service. Redirecting you to sign in…`,
      );
      event.currentTarget.reset();
    } catch {
      setState("idle");
      setError(
        "The setup request could not reach NIU Auth. Check the connection and try again.",
      );
    }
  }

  if (state === "complete") {
    return (
      <AuthFrame
        eyebrow="Setup complete"
        title="Administrator created"
        description="Your NIU Auth administrator is ready."
      >
        <div
          className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
          role="status"
          aria-live="polite"
        >
          <div className="flex items-start gap-3">
            <span className="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">
              <svg aria-hidden="true" className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2">
                <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 4 4L19 6" />
              </svg>
            </span>
            <div>
              <p className="font-medium">{message}</p>
              <p className="mt-2 text-xs leading-5 text-emerald-800">
                For security, remove the bootstrap token before exposing this service beyond setup.
              </p>
            </div>
          </div>
        </div>
        <Link className={`${primaryButton} mt-4`} href="/sign-in?setup=complete">
          Continue to sign in <span aria-hidden="true">→</span>
        </Link>
      </AuthFrame>
    );
  }

  return (
    <AuthFrame
      eyebrow="First-run setup"
      title="Create the first administrator"
      description="Use this one-time setup only after the identity database has been migrated."
    >
      <form
        className="space-y-4"
        onSubmit={submit}
        aria-busy={state === "pending"}
      >
        <fieldset disabled={state === "pending"} className="space-y-4">
          <Field label="Bootstrap token" name="bootstrapToken" type="password" required minLength={32} />
          <Field label="Full name" name="name" required maxLength={100} />
          <Field label="Email" name="email" type="email" required />
          <Field label="Password" name="password" type="password" required minLength={12} />
        </fieldset>
        {error && (
          <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-700" role="alert">
            {error}
          </p>
        )}
        <button className={primaryButton} disabled={state === "pending"} type="submit">
          {state === "pending" ? (
            <>
              <svg aria-hidden="true" className="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" />
                <path className="opacity-90" d="M21 12a9 9 0 0 1-9 9" stroke="currentColor" strokeLinecap="round" strokeWidth="3" />
              </svg>
              Creating administrator…
            </>
          ) : (
            <>Create administrator <span aria-hidden="true">→</span></>
          )}
        </button>
        <p className="text-center text-[11px] leading-4 text-slate-400">
          You will be taken to sign in after the administrator is created.
        </p>
      </form>
    </AuthFrame>
  );
}
