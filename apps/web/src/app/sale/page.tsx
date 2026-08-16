"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";
import { PosShell } from "@/components/PosShell";

type PriceBook = { id: string; name: string; currencyCode: string };
type CartLine = {
  variantId: string;
  name: string;
  variantName: string;
  sku: string;
  quantity: number;
  unitPriceMinor?: number;
  grossMinor?: number;
  taxMinor?: number;
  netMinor?: number;
};
type VariantDetail = {
  id: string;
  name: string;
  variantName: string;
  sku: string;
};
type FinalizedSale = {
  saleId: string;
  grossMinor: number;
  currencyCode: string;
};
type Receipt = { receiptId: string; receiptNumber: number };

function money(minor: number | undefined, currency: string): string {
  if (minor === undefined) return "—";
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
  }).format(minor / 100);
}

function SalePageContent() {
  const [books, setBooks] = useState<PriceBook[]>([]);
  const [bookId, setBookId] = useState("");
  const [cart, setCart] = useState<CartLine[]>([]);
  const [scanValue, setScanValue] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [finalizedSale, setFinalizedSale] = useState<FinalizedSale | null>(
    null,
  );
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const currency =
    books.find((book) => book.id === bookId)?.currencyCode ?? "KES";
  const total = useMemo(
    () => cart.reduce((sum, line) => sum + (line.grossMinor ?? 0), 0),
    [cart],
  );

  useEffect(() => {
    const ready = [
      "nova.access_token",
      "nova.tenant_id",
      "nova.register_id",
      "nova.warehouse_id",
    ].every((key) => window.localStorage.getItem(key));
    if (!ready) {
      queueMicrotask(() => {
        setLoading(false);
        setError(
          "Select a store, warehouse, and register before starting a sale.",
        );
      });
      return;
    }
    apiFetch("/api/v1/sales/price-books")
      .then(async (response) => {
        if (!response.ok)
          throw await apiError(response, "Pricing books could not be loaded.");
        const body = (await response.json()) as { data?: PriceBook[] };
        const rows = body.data ?? [];
        setBooks(rows);
        setBookId(rows[0]?.id ?? "");
        if (rows.length === 0)
          setNotice("No active price book is configured for this store.");
      })
      .catch((cause: unknown) =>
        setError(
          cause instanceof Error
            ? cause.message
            : "Pricing books could not be loaded.",
        ),
      )
      .finally(() => setLoading(false));
  }, []);

  async function scan(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    const value = scanValue.trim();
    if (!value) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const response = await apiFetch("/api/v1/catalogue/scan", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ value, mode: "barcode" }),
      });
      if (!response.ok)
        throw await apiError(response, "The scan could not be completed.");
      const body = (await response.json()) as {
        data?: { outcome?: string; variant_id?: string };
      };
      if (body.data?.outcome !== "found" || !body.data.variant_id) {
        setNotice("No active product matched that barcode.");
        return;
      }
      const detailResponse = await apiFetch(
        `/api/v1/catalogue/variants/${body.data.variant_id}`,
      );
      if (!detailResponse.ok)
        throw await apiError(
          detailResponse,
          "The scanned product is no longer available.",
        );
      const detail = (await detailResponse.json()) as { data: VariantDetail };
      setCart((current) => {
        const prior = current.find((line) => line.variantId === detail.data.id);
        const newLine: CartLine = {
          variantId: detail.data.id,
          name: detail.data.name,
          variantName: detail.data.variantName,
          sku: detail.data.sku,
          quantity: 1,
        };
        return prior
          ? current.map((line) =>
              line.variantId === prior.variantId
                ? { ...line, quantity: line.quantity + 1 }
                : line,
            )
          : [...current, newLine];
      });
      setScanValue("");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The scan could not be completed.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function quote(): Promise<void> {
    if (!bookId || cart.length === 0) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/sales/quote", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          price_book_id: bookId,
          currency_code: currency,
          at: new Date().toISOString().replace(/\.\d{3}Z$/, "+00:00"),
          lines: cart.map((line) => ({
            variant_id: line.variantId,
            quantity: line.quantity,
          })),
        }),
      });
      if (!response.ok)
        throw await apiError(response, "The cart could not be priced.");
      const body = (await response.json()) as {
        data?: {
          lines?: Array<{
            variantId: string;
            unitPriceMinor: number;
            grossMinor: number;
            taxMinor: number;
            netMinor: number;
          }>;
        };
      };
      const quoted = new Map(
        (body.data?.lines ?? []).map((line) => [line.variantId, line]),
      );
      setCart((current) =>
        current.map((line) => ({ ...line, ...quoted.get(line.variantId) })),
      );
      setNotice("Cart pricing is current for this price book.");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The cart could not be priced.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function finalize(): Promise<void> {
    const registerId = window.localStorage.getItem("nova.register_id");
    const warehouseId = window.localStorage.getItem("nova.warehouse_id");
    if (!registerId || !warehouseId || !bookId || cart.length === 0) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/sales/finalize", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": crypto.randomUUID(),
        },
        body: JSON.stringify({
          register_id: registerId,
          warehouse_id: warehouseId,
          price_book_id: bookId,
          currency_code: currency,
          occurred_at: new Date().toISOString().replace(/\.\d{3}Z$/, "+00:00"),
          lines: cart.map((line) => ({
            variant_id: line.variantId,
            quantity: line.quantity,
          })),
        }),
      });
      if (!response.ok)
        throw await apiError(response, "The sale could not be finalized.");
      const body = (await response.json()) as {
        data?: {
          sale_id?: string;
          gross_minor?: number;
          currency_code?: string;
        };
      };
      if (!body.data?.sale_id || body.data.gross_minor === undefined)
        throw new Error("The sale response was incomplete.");
      setFinalizedSale({
        saleId: body.data.sale_id,
        grossMinor: body.data.gross_minor,
        currencyCode: body.data.currency_code ?? currency,
      });
      setNotice("Sale finalized. Choose a payment method to complete it.");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The sale could not be finalized.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function completeCash(): Promise<void> {
    if (!finalizedSale) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch(
        `/api/v1/sales/${finalizedSale.saleId}/cash-complete`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Idempotency-Key": crypto.randomUUID(),
          },
          body: JSON.stringify({
            completed_at: new Date()
              .toISOString()
              .replace(/\.\d{3}Z$/, "+00:00"),
          }),
        },
      );
      if (!response.ok)
        throw await apiError(
          response,
          "Cash completion could not be completed.",
        );
      const body = (await response.json()) as {
        data?: { receipt_id?: string; receipt_number?: number };
      };
      if (!body.data?.receipt_id || body.data.receipt_number === undefined)
        throw new Error("The receipt response was incomplete.");
      setReceipt({
        receiptId: body.data.receipt_id,
        receiptNumber: body.data.receipt_number,
      });
      setNotice("Cash payment completed and receipt issued.");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "Cash completion could not be completed.",
      );
    } finally {
      setBusy(false);
    }
  }

  if (loading)
    return (
      <main className="min-h-screen bg-[#f7f9fd] p-6 text-sm text-slate-500">
        Loading sales workspace…
      </main>
    );
  if (receipt)
    return (
      <main className="min-h-screen bg-[#f7f9fd] px-4 py-8 text-slate-900">
        <div className="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-[0_4px_16px_rgba(15,23,42,.04)]">
          <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
            Payment complete
          </p>
          <h1 className="mt-2 text-2xl font-normal tracking-tight">
            Receipt issued
          </h1>
          <p className="mt-2 text-sm text-slate-500">
            Receipt #{receipt.receiptNumber}
          </p>
          <div className="mt-6 grid gap-2">
            <Link
              className="flex h-11 items-center justify-center rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700"
              href={`/receipts/${receipt.receiptId}/`}
            >
              View receipt
            </Link>
            <Link
              className="flex h-11 items-center justify-center rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:border-emerald-300"
              href="/sale/"
            >
              Start another sale
            </Link>
          </div>
        </div>
      </main>
    );

  return (
    <main className="min-h-screen bg-[#f7f9fd] text-slate-900">
      <div className="min-h-screen px-4 py-5 sm:px-6">
        <header className="flex items-center justify-between border-b border-slate-200 pb-4">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
              Sales
            </p>
            <h1 className="mt-1 text-xl font-normal tracking-tight">
              New sale
            </h1>
          </div>
          <Link
            className="text-sm font-medium text-slate-500 hover:text-slate-900"
            href="/dashboard/"
          >
            Back to dashboard
          </Link>
        </header>
        {error && (
          <p
            className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"
            role="alert"
          >
            {error}
          </p>
        )}
        {notice && (
          <p
            className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
            role="status"
          >
            {notice}
          </p>
        )}
        {finalizedSale ? (
          <section className="mx-auto mt-8 max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_16px_rgba(15,23,42,.04)]">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
              Payment
            </p>
            <h2 className="mt-2 text-xl font-normal">
              Collect{" "}
              {money(finalizedSale.grossMinor, finalizedSale.currencyCode)}
            </h2>
            <p className="mt-2 text-sm text-slate-500">
              Choose how the customer is paying. Cash completion issues the
              receipt after the drawer movement succeeds.
            </p>
            <button
              className="mt-6 h-11 w-full rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              disabled={busy}
              onClick={completeCash}
              type="button"
            >
              Complete cash payment
            </button>
            <p className="mt-4 text-center text-xs text-slate-400">
              M-Pesa completion is provider-confirmed and will remain pending
              until its signed result is received.
            </p>
          </section>
        ) : (
          <div className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_4px_16px_rgba(15,23,42,.04)]">
              <div className="flex flex-wrap items-end justify-between gap-3">
                <label className="block min-w-56 flex-1 text-xs font-medium text-slate-700">
                  Price book
                  <select
                    className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    value={bookId}
                    onChange={(event) => setBookId(event.target.value)}
                  >
                    <option value="">Select price book</option>
                    {books.map((book) => (
                      <option key={book.id} value={book.id}>
                        {book.name} · {book.currencyCode}
                      </option>
                    ))}
                  </select>
                </label>
                <span className="pb-3 text-xs text-slate-500">
                  {cart.length} line{cart.length === 1 ? "" : "s"}
                </span>
              </div>
              <form className="mt-5 flex gap-2" onSubmit={scan}>
                <input
                  className="h-11 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                  value={scanValue}
                  onChange={(event) => setScanValue(event.target.value)}
                  placeholder="Scan or enter a barcode"
                  aria-label="Barcode"
                />
                <button
                  className="h-11 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                  disabled={busy || !scanValue.trim()}
                  type="submit"
                >
                  Add
                </button>
              </form>
              {cart.length === 0 ? (
                <div className="mt-5 rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                  Scan an active product to begin this sale.
                </div>
              ) : (
                <div className="mt-5 divide-y divide-slate-100">
                  {cart.map((line) => (
                    <div
                      className="flex items-center justify-between gap-3 py-3"
                      key={line.variantId}
                    >
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-slate-800">
                          {line.name}
                        </p>
                        <p className="truncate text-xs text-slate-500">
                          {line.variantName} · {line.sku}
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-4 text-right">
                        <span className="text-xs text-slate-500">
                          ×{line.quantity}
                        </span>
                        <span className="text-sm font-medium text-slate-800">
                          {money(line.grossMinor, currency)}
                        </span>
                        <button
                          className="text-xs text-slate-400 hover:text-red-600"
                          onClick={() =>
                            setCart((current) =>
                              current.filter(
                                (item) => item.variantId !== line.variantId,
                              ),
                            )
                          }
                          type="button"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </section>
            <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_4px_16px_rgba(15,23,42,.04)] lg:sticky lg:top-0 lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto">
              <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
                Summary
              </p>
              <div className="mt-5 flex items-baseline justify-between">
                <span className="text-sm text-slate-500">Total</span>
                <strong className="text-2xl font-medium tracking-tight">
                  {money(total, currency)}
                </strong>
              </div>
              <button
                className="mt-5 h-11 w-full rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:border-emerald-300 hover:text-emerald-700 disabled:opacity-50"
                disabled={busy || !bookId || cart.length === 0}
                onClick={quote}
                type="button"
              >
                Refresh price
              </button>
              <button
                className="mt-2 h-11 w-full rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                disabled={
                  busy ||
                  !bookId ||
                  cart.length === 0 ||
                  cart.some((line) => line.grossMinor === undefined)
                }
                onClick={finalize}
                type="button"
              >
                Finalize sale{" "}
                <span className="ml-1" aria-hidden="true">
                  →
                </span>
              </button>
              <p className="mt-3 text-[11px] leading-5 text-slate-400">
                Finalization remains subject to the selected register&apos;s
                open shift and current inventory.
              </p>
            </aside>
          </div>
        )}
      </div>
    </main>
  );
}

export default function SalePage() {
  return (
    <PosShell activePath="/sale/" railMode="compact">
      <SalePageContent />
    </PosShell>
  );
}
