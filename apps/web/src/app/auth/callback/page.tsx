"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { apiError, apiFetch } from "@/lib/api";

type CallbackResponse = {
  data?: { access_token?: string; expires_at?: string };
};

export default function AuthCallbackPage() {
  const router = useRouter();
  const started = useRef(false);
  const [message, setMessage] = useState("Completing NIU Auth sign-in…");
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (started.current) return;
    started.current = true;

    const params = new URLSearchParams(window.location.search);
    const state = params.get("state");
    const code = params.get("code");
    const providerError = params.get("error");
    if (!state || (!code && !providerError)) {
      window.queueMicrotask(() => {
        setMessage("This NIU Auth sign-in link is incomplete or expired.");
        setFailed(true);
      });
      return;
    }

    const query = new URLSearchParams({ state });
    if (code) query.set("code", code);
    if (providerError) query.set("error", providerError);

    void (async () => {
      try {
        const response = await apiFetch(
          `/api/v1/auth/federation/callback?${query.toString()}`,
        );
        if (!response.ok)
          throw await apiError(response, "NIU Auth sign-in could not be completed.");
        const body = (await response.json()) as CallbackResponse;
        if (!body.data?.access_token)
          throw new Error("The NIU Auth response was incomplete.");
        window.localStorage.setItem("nova.access_token", body.data.access_token);
        if (body.data.expires_at)
          window.localStorage.setItem(
            "nova.session_expires_at",
            body.data.expires_at,
          );
        router.replace("/select-store/");
      } catch (cause: unknown) {
        setMessage(
          cause instanceof Error
            ? cause.message
            : "NIU Auth sign-in could not be completed.",
        );
        setFailed(true);
      }
    })();
  }, [router]);

  return (
    <main className="grid min-h-screen place-items-center bg-[#f7f9fd] px-4 text-center text-slate-900">
      <section className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-7 shadow-sm">
        <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-900">
          NIU POS
        </p>
        <h1 className="mt-3 text-lg font-medium tracking-tight">
          {failed ? "Sign-in needs attention" : "Signing you in"}
        </h1>
        <p className="mt-2 text-sm leading-5 text-slate-500">{message}</p>
        {failed && (
          <Link
            className="mt-5 inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-black"
            href="/"
          >
            Back to POS sign in
          </Link>
        )}
      </section>
    </main>
  );
}
