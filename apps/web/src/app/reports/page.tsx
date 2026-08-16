"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PosShell } from "@/components/PosShell";
import { apiError, apiFetch } from "@/lib/api";

type Report = {
  period: { from: string; to: string };
  totals: Array<{
    currencyCode: string;
    salesCount: number;
    grossMinor: number;
    netMinor: number;
    taxMinor: number;
  }>;
  topProducts: Array<{
    currencyCode: string;
    productName: string;
    variantName: string;
    quantity: number;
    grossMinor: number;
  }>;
};

type PeriodKey = "today" | "week" | "month";

const periodOptions: Array<{ key: PeriodKey; label: string }> = [
  { key: "today", label: "Today" },
  { key: "week", label: "7 days" },
  { key: "month", label: "This month" },
];

function periodDates(period: PeriodKey): { from: string; to: string } {
  const now = new Date();
  const from = new Date(now);

  if (period === "today") {
    from.setHours(0, 0, 0, 0);
  } else if (period === "week") {
    from.setDate(from.getDate() - 6);
    from.setHours(0, 0, 0, 0);
  } else {
    from.setDate(1);
    from.setHours(0, 0, 0, 0);
  }

  return { from: from.toISOString(), to: now.toISOString() };
}

function periodLabel(period: Report["period"]): string {
  const formatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
  });

  return `${formatter.format(new Date(period.from))} – ${formatter.format(new Date(period.to))}`;
}

function money(minor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
  }).format(minor / 100);
}

export default function ReportsPage() {
  const [report, setReport] = useState<Report | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState<PeriodKey>("month");

  useEffect(() => {
    queueMicrotask(() => {
      setLoading(true);
      setError(null);
      const dates = periodDates(period);
      const query = new URLSearchParams(dates).toString();

      apiFetch(`/api/v1/reports/summary?${query}`)
        .then(async (response) => {
          if (!response.ok)
            throw await apiError(response, "Reports could not be loaded.");
          const body = (await response.json()) as { data?: Report };
          setReport(body.data ?? null);
        })
        .catch((cause: unknown) =>
          setError(
            cause instanceof Error
              ? cause.message
              : "Reports could not be loaded.",
          ),
        )
        .finally(() => setLoading(false));
    });
  }, [period]);

  return (
    <PosShell activePath="/reports/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] px-4 py-6 text-slate-900">
        <div className="mx-auto max-w-6xl">
          <header className="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
                Insights
              </p>
              <h1 className="mt-1 text-xl font-normal tracking-tight">
                Sales reports
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Committed sales facts for the current workspace.
              </p>
            </div>
            <div className="flex items-center gap-3 sm:pb-0.5">
              <div
                aria-label="Report period"
                className="flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm"
                role="group"
              >
                {periodOptions.map((option) => (
                  <button
                    aria-pressed={period === option.key}
                    className={`min-h-10 rounded-md px-3 text-xs transition-colors ${
                      period === option.key
                        ? "bg-slate-900 font-medium text-white"
                        : "text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                    }`}
                    key={option.key}
                    onClick={() => setPeriod(option.key)}
                    type="button"
                  >
                    {option.label}
                  </button>
                ))}
              </div>
              <Link
                className="text-sm text-slate-500 hover:text-slate-900"
                href="/dashboard/"
              >
                Back
              </Link>
            </div>
          </header>
          {error && (
            <p
              className="mt-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"
              role="alert"
            >
              {error}
            </p>
          )}
          {loading ? (
            <p className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
              Loading reports…
            </p>
          ) : !report ? (
            <p className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
              No report data is available for this workspace.
            </p>
          ) : report.totals.length === 0 ? (
            <div className="mt-5 rounded-xl border border-slate-200 bg-white p-6">
              <p className="text-sm font-medium text-slate-800">
                No completed sales in this period
              </p>
              <p className="mt-1 text-sm text-slate-500">
                Completed sales will appear here once this workspace records its
                first finalized transaction.
              </p>
            </div>
          ) : (
            <>
              <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {report.totals.map((total) => (
                  <article
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_16px_rgba(15,23,42,.04)]"
                    key={total.currencyCode}
                  >
                    <p className="text-xs text-slate-500">
                      {total.currencyCode} gross
                    </p>
                    <p className="mt-2 text-2xl font-normal tracking-tight">
                      {money(total.grossMinor, total.currencyCode)}
                    </p>
                    <p className="mt-2 text-xs text-slate-500">
                      {total.salesCount} finalized sale
                      {total.salesCount === 1 ? "" : "s"}
                    </p>
                  </article>
                ))}
                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_16px_rgba(15,23,42,.04)]">
                  <p className="text-xs text-slate-500">Currencies</p>
                  <p className="mt-2 text-2xl font-normal tracking-tight">
                    {report.totals.length}
                  </p>
                  <p className="mt-2 text-xs text-slate-500">
                    Separate totals are preserved.
                  </p>
                </article>
              </div>
              <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_16px_rgba(15,23,42,.04)]">
                <div className="border-b border-slate-100 px-5 py-4">
                  <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 className="text-sm font-medium text-slate-800">
                      Top products by gross sales
                    </h2>
                    <p className="text-xs text-slate-400">
                      {periodLabel(report.period)}
                    </p>
                  </div>
                  <p className="mt-1 text-xs text-slate-500">
                    Across finalized sales in the selected period.
                  </p>
                </div>
                <div className="divide-y divide-slate-100">
                  {report.topProducts.length === 0 ? (
                    <p className="p-5 text-sm text-slate-500">
                      No product lines are available for this period.
                    </p>
                  ) : (
                    report.topProducts.map((product) => (
                      <div
                        className="flex items-center justify-between gap-4 px-5 py-3"
                        key={`${product.currencyCode}-${product.productName}-${product.variantName}`}
                      >
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-slate-800">
                            {product.productName}
                          </p>
                          <p className="mt-1 truncate text-xs text-slate-500">
                            {product.variantName} · {product.quantity} unit
                            {product.quantity === 1 ? "" : "s"}
                          </p>
                        </div>
                        <p className="shrink-0 text-sm font-medium text-slate-800">
                          {money(product.grossMinor, product.currencyCode)}
                        </p>
                      </div>
                    ))
                  )}
                </div>
              </section>
            </>
          )}
        </div>
      </main>
    </PosShell>
  );
}
