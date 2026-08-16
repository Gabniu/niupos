"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { apiError, apiFetch } from "@/lib/api";

const carousel = [
  { src: "/auth-carousel/grocery.jpg", label: "Keep every shelf moving." },
  {
    src: "/auth-carousel/bakery.jpg",
    label: "Serve customers with confidence.",
  },
  { src: "/auth-carousel/suits.jpg", label: "Know what is selling." },
  {
    src: "/auth-carousel/jeans.jpg",
    label: "Retail truth, from scan to settlement.",
  },
] as const;

export default function Home() {
  const router = useRouter();
  const [active, setActive] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [federatedSubmitting, setFederatedSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setInterval(
      () => setActive((current) => (current + 1) % carousel.length),
      6000,
    );
    return () => window.clearInterval(timer);
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    try {
      const response = await apiFetch("/api/v1/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: form.get("email"),
          password: form.get("password"),
        }),
      });
      if (!response.ok)
        throw await apiError(response, "The provided credentials are invalid.");
      const body = (await response.json()) as {
        data?: { access_token?: string; expires_at?: string };
      };
      if (!body.data?.access_token)
        throw new Error("The sign-in response was incomplete.");
      window.localStorage.setItem("nova.access_token", body.data.access_token);
      if (body.data.expires_at)
        window.localStorage.setItem(
          "nova.session_expires_at",
          body.data.expires_at,
        );
      router.push("/select-store/");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "Sign-in could not be completed.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function signInWithNiuAuth() {
    setFederatedSubmitting(true);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/auth/federation/start");
      if (!response.ok)
        throw await apiError(response, "NIU Auth sign-in is not available right now.");
      const body = (await response.json()) as {
        data?: { authorization_url?: string };
      };
      if (!body.data?.authorization_url)
        throw new Error("NIU Auth sign-in could not be started.");
      window.location.assign(body.data.authorization_url);
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "NIU Auth sign-in could not be started.",
      );
      setFederatedSubmitting(false);
    }
  }

  return (
    <main className="relative isolate min-h-screen bg-[#f7f9fd] text-slate-900 md:flex">
      <section
        className="relative z-0 hidden min-h-screen overflow-hidden bg-slate-950 md:block md:w-1/2"
        aria-label="NIU POS highlights"
      >
        {carousel.map((slide, index) => (
          <div
            className={`absolute inset-0 z-0 bg-cover bg-center transition-opacity duration-1000 ${index === active ? "opacity-100" : "opacity-0"}`}
            key={slide.src}
            style={{
              backgroundImage: `linear-gradient(180deg, rgba(0, 0, 0, .06), rgba(0, 0, 0, .28)), url(${slide.src})`,
            }}
            aria-hidden={index !== active}
          />
        ))}
        <div className="relative z-10 flex min-h-screen flex-col justify-between p-8 text-white lg:p-12">
          <div className="flex items-center gap-3">
            <span className="inline-flex rounded-lg bg-black/20 px-3 py-2 font-[var(--font-hanken)] text-lg font-medium tracking-[0.04em] backdrop-blur-sm">
              NIU <span className="text-emerald-300">POS</span>
            </span>
          </div>
          <div className="max-w-sm">
            <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.22em] text-emerald-200">
              Business continues
            </p>
            <p className="text-xl font-light leading-tight tracking-tight lg:text-3xl">
              {carousel[active].label}
            </p>
            <div
              className="mt-6 flex gap-2"
              role="tablist"
              aria-label="Login page highlights"
            >
              {carousel.map((slide, index) => (
                <button
                  className={`h-1.5 rounded-full transition-all ${index === active ? "w-10 bg-emerald-300" : "w-5 bg-white/40"}`}
                  key={slide.src}
                  onClick={() => setActive(index)}
                  role="tab"
                  aria-label={`Show highlight ${index + 1}`}
                  aria-selected={index === active}
                  type="button"
                />
              ))}
            </div>
          </div>
          <p className="text-xs text-white/70">
            Offline-first retail operations for Kenya&apos;s growing businesses.
          </p>
        </div>
      </section>

      <section className="relative z-10 flex min-h-screen w-full items-center justify-center bg-[#f7f9fd] px-4 py-8 sm:px-8 sm:py-10 md:w-1/2 lg:px-16 lg:py-16">
        <div className="w-full max-w-sm">
          <div className="mb-9 md:hidden">
            <div className="flex items-center gap-3">
              <span className="inline-flex rounded-lg bg-emerald-50 px-3 py-2 font-[var(--font-hanken)] text-lg font-medium tracking-[0.04em]">
              NIU <span className="text-emerald-600">POS</span>
              </span>
            </div>
          </div>
          <header className="mb-7 text-left">
            <h1 className="text-[1.4rem] font-medium tracking-[-0.035em] text-slate-900 sm:text-[1.7rem]">
              Sign in
            </h1>
            <p className="mt-2 text-sm leading-5 text-slate-500 md:whitespace-nowrap">
              Manage your business from one calm workspace.
            </p>
          </header>
          <form className="space-y-4" onSubmit={submit}>
            <label
              className="block text-[13px] font-medium text-slate-700"
              htmlFor="email"
            >
              Email or username
              <input
                className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                id="email"
                name="email"
                placeholder="you@store.com"
                autoComplete="username"
                required
              />
            </label>
            <label
              className="block text-[13px] font-medium text-slate-700"
              htmlFor="password"
            >
              Password
              <span className="relative mt-1.5 block">
                <input
                  className="h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 pr-16 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                  id="password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  placeholder="Enter your password"
                  autoComplete="current-password"
                  required
                />
                <button
                  className="absolute inset-y-0 right-0 px-3.5 text-xs font-medium text-slate-500 hover:text-slate-900"
                  onClick={() => setShowPassword((visible) => !visible)}
                  type="button"
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </span>
            </label>
            <div className="flex flex-wrap items-center justify-between gap-3 text-xs sm:text-sm">
              <label className="flex items-center gap-2 text-slate-500">
                <input
                  className="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                  type="checkbox"
                />
                Remember me
              </label>
              <a
                className="font-medium text-emerald-700 hover:text-emerald-900"
                href="#forgot-password"
              >
                Forgot password?
              </a>
            </div>
            {error && (
              <p
                className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"
                role="alert"
              >
                {error}
              </p>
            )}
            <button
              className="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 active:scale-[.99] disabled:cursor-wait disabled:opacity-60"
              disabled={submitting}
              type="submit"
            >
              {submitting ? "Signing in…" : "Sign in"}{" "}
              <svg
                aria-hidden="true"
                className="size-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth="1.8"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M5 12h13m-5-5 5 5-5 5"
                />
              </svg>
            </button>
          </form>
          <div className="my-5 flex items-center gap-3 text-[10px] uppercase tracking-[0.16em] text-slate-400">
            <span className="h-px flex-1 bg-slate-200" />
            or
            <span className="h-px flex-1 bg-slate-200" />
          </div>
          <button
            className="flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 active:scale-[.99] disabled:cursor-wait disabled:opacity-60"
            disabled={submitting || federatedSubmitting}
            onClick={() => void signInWithNiuAuth()}
            type="button"
          >
            {federatedSubmitting ? "Connecting to NIU Auth…" : "Continue with NIU Auth"}
          </button>
          <p className="mt-6 border-t border-slate-200 pt-5 text-center text-xs text-slate-500">
            Need access?{" "}
            <a
              className="font-medium text-emerald-700 hover:text-emerald-900"
              href="#request-access"
            >
              Request an account
            </a>
          </p>
          <p className="mt-3 text-center text-[11px] text-slate-400">
            NIU POS · Secure, explainable, and ready when the network is not.
          </p>
        </div>
      </section>
    </main>
  );
}
