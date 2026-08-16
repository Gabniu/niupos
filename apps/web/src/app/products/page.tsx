"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";
import { PosShell } from "@/components/PosShell";

type Product = {
  id: string;
  name: string;
  variantName: string;
  sku: string;
  unitCode: string | null;
};

export default function ProductsPage() {
  const pageSize = 20;
  const [products, setProducts] = useState<Product[]>([]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  async function load(query = ""): Promise<void> {
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetch(
        `/api/v1/catalogue/products${query ? `?search=${encodeURIComponent(query)}` : ""}`,
      );
      if (!response.ok)
        throw await apiError(response, "Products could not be loaded.");
      const body = (await response.json()) as { data?: Product[] };
      setProducts(body.data ?? []);
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "Products could not be loaded.",
      );
    } finally {
      setLoading(false);
    }
  }
  useEffect(() => {
    queueMicrotask(() => {
      void load();
    });
    // The initial load intentionally runs once for the current tenant.
    // Search submits call the same loader explicitly.
  }, []);
  function submit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    setPage(1);
    void load(search.trim());
  }
  const pageCount = Math.max(1, Math.ceil(products.length / pageSize));
  const currentPage = Math.min(page, pageCount);
  const pagedProducts = products.slice((currentPage - 1) * pageSize, currentPage * pageSize);
  return (
    <PosShell activePath="/products/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] px-4 py-6 text-slate-900">
        <div className="mx-auto max-w-5xl">
          <header className="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
                Catalogue
              </p>
              <h1 className="mt-1 text-xl font-normal tracking-tight">
                Products
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Active products available to this store.
              </p>
            </div>
            <Link
              className="text-sm text-slate-500 hover:text-slate-900"
              href="/dashboard/"
            >
              Back
            </Link>
          </header>
          <form
            className="mt-5 flex flex-col gap-2 sm:flex-row"
            onSubmit={submit}
          >
            <input
              className="h-11 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search product, variant, or SKU"
              aria-label="Search products"
            />
            <button
              className="h-11 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-700 sm:shrink-0"
              type="submit"
            >
              Search
            </button>
          </form>
          {error && (
            <p
              className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"
              role="alert"
            >
              {error}
            </p>
          )}
          {loading ? (
            <p className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
              Loading active products…
            </p>
          ) : products.length === 0 ? (
            <p className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
              No active products match this search.
            </p>
          ) : (
            <div className="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-[0_4px_16px_rgba(15,23,42,.04)]">
              <div className="min-w-[520px]">
                <div className="grid grid-cols-[1.3fr_1fr_.8fr_.5fr] gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-[11px] font-medium uppercase tracking-[0.12em] text-slate-500">
                  <span>Product</span>
                  <span>Variant</span>
                  <span>SKU</span>
                  <span>Unit</span>
                </div>
                {pagedProducts.map((product) => (
                  <div
                    className="grid grid-cols-[1.3fr_1fr_.8fr_.5fr] gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0"
                    key={product.id}
                  >
                    <span className="truncate font-medium text-slate-800">
                      {product.name}
                    </span>
                    <span className="truncate text-slate-600">
                      {product.variantName}
                    </span>
                    <span className="truncate text-slate-500">
                      {product.sku}
                    </span>
                    <span className="text-slate-500">
                      {product.unitCode ?? "—"}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
          {!loading && !error && products.length > 0 && (
            <nav className="mt-4 flex items-center justify-between gap-3" aria-label="Product pages">
              <p className="text-xs text-slate-500">
                Showing {(currentPage - 1) * pageSize + 1}–{Math.min(currentPage * pageSize, products.length)} of {products.length}
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
