"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PosShell } from "@/components/PosShell";
import { apiError, apiFetch, selectedTenantId } from "@/lib/api";

type Overview = {
  tenantName: string;
  metrics: Array<{ label: string; value: string; note?: string }>;
  activity: Array<{ label: string; detail: string; occurredAt: string }>;
};

function DashboardIcon() {
  return (
    <svg
      aria-hidden="true"
      className="size-4"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth="1.7"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"
      />
    </svg>
  );
}

const quickActions = [
  { label: "New sale", href: "/sale/", glyph: "+" },
  { label: "Add product", href: "/products/", glyph: "↗" },
  { label: "Stock movement", href: "/inventory/", glyph: "↗" },
  { label: "Reports", href: "/reports/", glyph: "↗" },
] as const;

export default function DashboardPage() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [state, setState] = useState<"loading" | "ready" | "empty" | "error">(
    "loading",
  );

  useEffect(() => {
    const token = window.localStorage.getItem("nova.access_token");
    if (!token || !selectedTenantId()) {
      queueMicrotask(() => setState("empty"));
      return;
    }
    apiFetch("/api/v1/dashboard/overview")
      .then(async (response) => {
        if (!response.ok)
          throw await apiError(response, "Dashboard data is unavailable.");
        const body = (await response.json()) as { data?: Overview };
        if (!body.data) {
          setState("empty");
          return;
        }
        setOverview(body.data);
        setState("ready");
      })
      .catch(() => setState("error"));
  }, []);

  return (
    <PosShell activePath="/dashboard/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] text-slate-900">
        <div className="mx-auto max-w-6xl space-y-5 px-4 py-4 sm:px-6 lg:px-6">
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
              <p className="text-[10px] font-medium uppercase tracking-[0.16em] text-emerald-700">
                Overview
              </p>
              <h1 className="mt-1.5 text-[1.05rem] font-normal tracking-[-0.015em] sm:text-[1.25rem]">
                Your business at a glance
              </h1>
              <p className="mt-1.5 text-xs text-slate-500 sm:text-[13px]">
                A calm view of what needs your attention.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Link
                className="flex h-11 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 hover:border-emerald-300 hover:text-emerald-700"
                href="/shift/"
              >
                Shift
              </Link>
              <Link
                className="flex h-11 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition-[transform,box-shadow,background-color] duration-200 ease-out hover:-translate-y-px hover:bg-emerald-700 hover:shadow-md"
                href="/sale/"
              >
                <span className="text-lg leading-none" aria-hidden="true">
                  +
                </span>{" "}
                New sale
              </Link>
            </div>
          </div>
          {state === "loading" && (
            <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500">
              Loading your business overview…
            </div>
          )}
          {state === "error" && (
            <div
              className="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-sm text-amber-900"
              role="alert"
            >
              Your business overview could not be loaded.
            </div>
          )}
          {state === "empty" && (
            <div className="flex min-h-24 items-center justify-between gap-5 rounded-xl border border-slate-200 bg-white px-5 py-3.5 shadow-[0_3px_14px_rgba(15,23,42,.03)]">
              <div className="flex items-center gap-3">
                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500">
                  <DashboardIcon />
                </span>
                <div>
                  <p className="text-sm font-medium text-slate-700">
                    No store selected
                  </p>
                  <p className="mt-0.5 text-xs text-slate-500">
                    Sign in and select a store to see live business data.
                  </p>
                </div>
              </div>
              <Link
                className="shrink-0 text-xs font-medium text-emerald-700 hover:text-emerald-800"
                href="/select-store/"
              >
                Select store <span aria-hidden="true">→</span>
              </Link>
            </div>
          )}
          {state === "ready" && overview && (
            <>
              <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {overview.metrics.map((metric) => (
                  <article
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_16px_rgba(15,23,42,.04)]"
                    key={metric.label}
                  >
                    <p className="text-xs text-slate-500">{metric.label}</p>
                    <p className="mt-4 text-2xl font-medium tracking-tight">
                      {metric.value}
                    </p>
                    {metric.note && (
                      <p className="mt-1 text-xs text-emerald-700">
                        {metric.note}
                      </p>
                    )}
                  </article>
                ))}
              </section>
              <section className="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
                <div>
                  <h2 className="mb-4 text-lg font-medium">Quick actions</h2>
                  <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {quickActions.map((item) => (
                      <Link
                        className="group rounded-xl border border-slate-200 bg-white p-4 text-left text-sm text-slate-700 shadow-sm transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-px hover:border-emerald-300 hover:shadow-md"
                        href={item.href}
                        key={item.label}
                      >
                        {item.label}
                        <span className="mt-5 block text-lg text-emerald-600 transition-transform duration-200 ease-out group-hover:translate-x-0.5">
                          {item.glyph}
                        </span>
                      </Link>
                    ))}
                  </div>
                </div>
                <div>
                  <h2 className="mb-4 text-lg font-medium">Recent activity</h2>
                  <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                    {overview.activity.length === 0 ? (
                      <p className="text-sm text-slate-500">
                        No recent activity for this store.
                      </p>
                    ) : (
                      overview.activity.map((item) => (
                        <div
                          className="border-b border-slate-100 pb-3 last:border-0 last:pb-0"
                          key={`${item.label}-${item.occurredAt}`}
                        >
                          <p className="text-sm text-slate-700">{item.label}</p>
                          <p className="mt-1 text-xs text-slate-500">
                            {item.detail} · {item.occurredAt}
                          </p>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </section>
            </>
          )}
        </div>
      </main>
    </PosShell>
  );
}
