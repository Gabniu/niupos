"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";
import { PosShell } from "@/components/PosShell";

type Balance = {
  id: string;
  warehouseName: string;
  productName: string;
  variantName: string;
  sku: string;
  quantity: number;
};

export default function InventoryPage() {
  const pageSize = 12;
  const [balances, setBalances] = useState<Balance[]>([]);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    queueMicrotask(() => {
      const warehouseId = window.localStorage.getItem("nova.warehouse_id");
      apiFetch(
        `/api/v1/inventory/balances${warehouseId ? `?warehouseId=${encodeURIComponent(warehouseId)}` : ""}`,
      )
        .then(async (response) => {
          if (!response.ok)
            throw await apiError(response, "Inventory could not be loaded.");
          const body = (await response.json()) as { data?: Balance[] };
          setBalances(body.data ?? []);
        })
        .catch((cause: unknown) =>
          setError(
            cause instanceof Error
              ? cause.message
              : "Inventory could not be loaded.",
          ),
        )
        .finally(() => setLoading(false));
    });
  }, []);

  const visible = balances.filter((balance) =>
    `${balance.productName} ${balance.variantName} ${balance.sku} ${balance.warehouseName}`
      .toLowerCase()
      .includes(query.trim().toLowerCase()),
  );
  const pageCount = Math.max(1, Math.ceil(visible.length / pageSize));
  const currentPage = Math.min(page, pageCount);
  const paged = visible.slice((currentPage - 1) * pageSize, currentPage * pageSize);
  const units = visible.reduce((total, balance) => total + balance.quantity, 0);

  return (
    <PosShell activePath="/inventory/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] px-4 py-6 text-slate-900">
        <div className="mx-auto max-w-6xl">
          <header className="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
                Operations
              </p>
              <h1 className="mt-1 text-xl font-normal tracking-tight">
                Warehouse inventory
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Real-time stock visibility for the selected workspace.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <input
                className="h-10 min-w-0 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 sm:w-64"
                aria-label="Search inventory"
                placeholder="Search inventory"
                value={query}
                onChange={(event) => {
                  setQuery(event.target.value);
                  setPage(1);
                }}
              />
              <Link
                className="text-sm text-slate-500 hover:text-slate-900"
                href="/dashboard/"
              >
                Back
              </Link>
            </div>
          </header>
          {!loading && !error && balances.length > 0 && (
            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <div className="rounded-xl border border-slate-200 bg-white p-4">
                <p className="text-xs text-slate-500">Visible lines</p>
                <p className="mt-2 text-xl font-normal tracking-tight">
                  {visible.length}
                </p>
              </div>
              <div className="rounded-xl border border-slate-200 bg-white p-4">
                <p className="text-xs text-slate-500">Units on hand</p>
                <p className="mt-2 text-xl font-normal tracking-tight">
                  {units}
                </p>
              </div>
            </div>
          )}
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
              Loading inventory…
            </p>
          ) : visible.length === 0 ? (
            <div className="mt-5 rounded-xl border border-slate-200 bg-white p-6">
              <p className="text-sm font-medium text-slate-800">
                {balances.length === 0
                  ? "No inventory balances recorded"
                  : "No matching inventory lines"}
              </p>
              <p className="mt-1 text-sm text-slate-500">
                {balances.length === 0
                  ? "Balances will appear here after stock receipts or adjustments are posted for this workspace."
                  : "Try a product, variant, SKU, or warehouse name."}
              </p>
            </div>
          ) : (
            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {paged.map((balance) => (
                <article
                  className="group flex min-h-56 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_4px_16px_rgba(15,23,42,.04)] transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-px hover:border-emerald-200 hover:shadow-md"
                  key={balance.id}
                >
                  <div className="flex aspect-[4/3] items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg
                      aria-hidden="true"
                      className="size-10 opacity-70"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      strokeWidth="1.3"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7"
                      />
                    </svg>
                  </div>
                  <div className="mt-4 flex flex-1 flex-col">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h2 className="truncate text-sm font-medium text-slate-800">
                          {balance.productName}
                        </h2>
                        <p className="mt-1 truncate text-xs text-slate-500">
                          {balance.variantName}
                        </p>
                      </div>
                      <span
                        className={`shrink-0 rounded-full px-2 py-1 text-[10px] font-medium uppercase tracking-wide ${balance.quantity <= 0 ? "bg-red-50 text-red-700" : "bg-emerald-50 text-emerald-700"}`}
                      >
                        {balance.quantity <= 0 ? "Out" : "In stock"}
                      </span>
                    </div>
                    <div className="mt-auto flex items-end justify-between gap-3 pt-4">
                      <div>
                        <p className="text-[11px] uppercase tracking-[0.12em] text-slate-400">
                          {balance.warehouseName}
                        </p>
                        <p className="mt-1 text-xs text-slate-400">
                          {balance.sku}
                        </p>
                      </div>
                      <p
                        className={`text-xl font-normal tracking-tight ${balance.quantity < 0 ? "text-red-700" : "text-slate-800"}`}
                      >
                        {balance.quantity}
                      </p>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
          {!loading && !error && visible.length > 0 && (
            <nav className="mt-4 flex items-center justify-between gap-3" aria-label="Inventory pages">
              <p className="text-xs text-slate-500">
                Showing {(currentPage - 1) * pageSize + 1}–{Math.min(currentPage * pageSize, visible.length)} of {visible.length}
              </p>
              <div className="flex items-center gap-2">
                <button
                  className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                  disabled={currentPage === 1}
                  onClick={() => setPage((value) => Math.max(1, value - 1))}
                  type="button"
                >
                  Previous
                </button>
                <span className="min-w-16 text-center text-xs text-slate-500">{currentPage} / {pageCount}</span>
                <button
                  className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                  disabled={currentPage === pageCount}
                  onClick={() => setPage((value) => Math.min(pageCount, value + 1))}
                  type="button"
                >
                  Next
                </button>
              </div>
            </nav>
          )}
        </div>
      </main>
    </PosShell>
  );
}
