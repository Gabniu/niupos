"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";
import { PosShell } from "@/components/PosShell";

type Shift = {
  id: string;
  register_id: string;
  status: string;
  currency: string;
  opening_float_minor: number;
  expected_cash_minor: number;
  opened_at: string | null;
  variance_minor: number | null;
};
type PriceBook = { currencyCode: string };

const money = (minor: number, currency: string) =>
  new Intl.NumberFormat(undefined, { style: "currency", currency }).format(
    minor / 100,
  );

function ShiftPageContent() {
  const [shift, setShift] = useState<Shift | null>(null);
  const [currencies, setCurrencies] = useState<string[]>([]);
  const [currency, setCurrency] = useState("");
  const [openingFloat, setOpeningFloat] = useState("");
  const [movementType, setMovementType] = useState<"pay_in" | "pay_out">(
    "pay_in",
  );
  const [movementAmount, setMovementAmount] = useState("");
  const [movementReason, setMovementReason] = useState("");
  const [countedCash, setCountedCash] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const registerId =
    typeof window === "undefined"
      ? null
      : window.localStorage.getItem("nova.register_id");

  useEffect(() => {
    if (!registerId) {
      queueMicrotask(() => {
        setLoading(false);
        setError("Select a register before managing its shift.");
      });
      return;
    }
    Promise.all([
      apiFetch(`/api/v1/shifts/current?register_id=${registerId}`),
      apiFetch("/api/v1/sales/price-books"),
    ])
      .then(async ([shiftResponse, booksResponse]) => {
        if (!shiftResponse.ok)
          throw await apiError(
            shiftResponse,
            "The current shift could not be loaded.",
          );
        if (!booksResponse.ok)
          throw await apiError(
            booksResponse,
            "Currencies could not be loaded.",
          );
        const shiftBody = (await shiftResponse.json()) as {
          data?: Shift | null;
        };
        const bookBody = (await booksResponse.json()) as { data?: PriceBook[] };
        const rows = [
          ...new Set((bookBody.data ?? []).map((book) => book.currencyCode)),
        ];
        setShift(shiftBody.data ?? null);
        setCurrencies(rows);
        setCurrency(rows[0] ?? "");
      })
      .catch((cause: unknown) =>
        setError(
          cause instanceof Error
            ? cause.message
            : "Shift data could not be loaded.",
        ),
      )
      .finally(() => setLoading(false));
  }, [registerId]);

  async function open(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (!registerId || !currency) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/shifts/open", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": crypto.randomUUID(),
        },
        body: JSON.stringify({
          register_id: registerId,
          opening_float_minor: Number(openingFloat),
          currency,
        }),
      });
      if (!response.ok)
        throw await apiError(response, "The shift could not be opened.");
      const body = (await response.json()) as { data?: Shift };
      if (!body.data) throw new Error("The shift response was incomplete.");
      setShift(body.data);
      setOpeningFloat("");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The shift could not be opened.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function movement(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (!shift) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/shifts/cash-movements", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Idempotency-Key": crypto.randomUUID(),
        },
        body: JSON.stringify({
          shift_id: shift.id,
          type: movementType,
          amount_minor: Number(movementAmount),
          reason: movementReason,
        }),
      });
      if (!response.ok)
        throw await apiError(
          response,
          "The cash movement could not be recorded.",
        );
      setMovementAmount("");
      setMovementReason("");
      const refresh = await apiFetch(
        `/api/v1/shifts/current?register_id=${registerId}`,
      );
      if (refresh.ok)
        setShift(((await refresh.json()) as { data?: Shift }).data ?? null);
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The cash movement could not be recorded.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function close(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (!shift) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch(`/api/v1/shifts/${shift.id}/close`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ counted_cash_minor: Number(countedCash) }),
      });
      if (!response.ok)
        throw await apiError(response, "The shift could not be closed.");
      setShift(((await response.json()) as { data?: Shift }).data ?? null);
      setCountedCash("");
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "The shift could not be closed.",
      );
    } finally {
      setBusy(false);
    }
  }

  if (loading)
    return (
      <main className="min-h-screen bg-[#f7f9fd] p-6 text-sm text-slate-500">
        Loading shift workspace…
      </main>
    );
  return (
    <main className="min-h-screen bg-[#f7f9fd] px-4 py-6 text-slate-900">
      <div className="mx-auto max-w-2xl">
        <header className="flex items-center justify-between border-b border-slate-200 pb-4">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
              Register operations
            </p>
            <h1 className="mt-1 text-xl font-normal tracking-tight">
              Shift and cash drawer
            </h1>
          </div>
          <Link
            className="text-sm text-slate-500 hover:text-slate-900"
            href="/dashboard/"
          >
            Back
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
        {!shift ? (
          <form
            className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_16px_rgba(15,23,42,.04)]"
            onSubmit={open}
          >
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
              No open shift
            </p>
            <h2 className="mt-2 text-lg font-normal">Open this register</h2>
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <label className="block text-xs font-medium text-slate-700">
                Currency
                <select
                  className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-emerald-500"
                  value={currency}
                  onChange={(event) => setCurrency(event.target.value)}
                >
                  <option value="">Select currency</option>
                  {currencies.map((item) => (
                    <option key={item}>{item}</option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Opening float (minor units)
                <input
                  className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500"
                  inputMode="numeric"
                  min="0"
                  step="1"
                  value={openingFloat}
                  onChange={(event) => setOpeningFloat(event.target.value)}
                  required
                  type="number"
                />
              </label>
            </div>
            <button
              className="mt-5 h-11 w-full rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              disabled={busy || !currency}
              type="submit"
            >
              Open shift
            </button>
          </form>
        ) : (
          <div className="mt-6 space-y-4">
            <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-medium uppercase tracking-[0.16em] text-emerald-700">
                    Open shift
                  </p>
                  <p className="mt-2 text-2xl font-medium tracking-tight">
                    {money(shift.expected_cash_minor, shift.currency)}
                  </p>
                  <p className="mt-1 text-xs text-emerald-800">
                    Expected cash in drawer
                  </p>
                </div>
                <span className="rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-medium text-white">
                  Open
                </span>
              </div>
            </section>
            <form
              className="rounded-2xl border border-slate-200 bg-white p-5"
              onSubmit={movement}
            >
              <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
                Cash movement
              </p>
              <div className="mt-4 grid gap-3 sm:grid-cols-[.7fr_1fr_1.6fr_auto]">
                <select
                  className="h-11 rounded-lg border border-slate-200 px-3 text-sm"
                  value={movementType}
                  onChange={(event) =>
                    setMovementType(event.target.value as "pay_in" | "pay_out")
                  }
                >
                  <option value="pay_in">Pay in</option>
                  <option value="pay_out">Pay out</option>
                </select>
                <input
                  className="h-11 rounded-lg border border-slate-200 px-3 text-sm"
                  inputMode="numeric"
                  min="1"
                  step="1"
                  placeholder="Minor units"
                  value={movementAmount}
                  onChange={(event) => setMovementAmount(event.target.value)}
                  required
                  type="number"
                />
                <input
                  className="h-11 rounded-lg border border-slate-200 px-3 text-sm"
                  placeholder="Reason"
                  value={movementReason}
                  onChange={(event) => setMovementReason(event.target.value)}
                  required
                />
                <button
                  className="h-11 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                  disabled={busy}
                  type="submit"
                >
                  Record
                </button>
              </div>
            </form>
            <form
              className="rounded-2xl border border-slate-200 bg-white p-5"
              onSubmit={close}
            >
              <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
                Close shift
              </p>
              <div className="mt-4 flex gap-2">
                <input
                  className="h-11 min-w-0 flex-1 rounded-lg border border-slate-200 px-3 text-sm"
                  inputMode="numeric"
                  min="0"
                  step="1"
                  placeholder="Counted cash in minor units"
                  value={countedCash}
                  onChange={(event) => setCountedCash(event.target.value)}
                  required
                  type="number"
                />
                <button
                  className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:border-red-300 hover:text-red-700"
                  disabled={busy}
                  type="submit"
                >
                  Close shift
                </button>
              </div>
            </form>
          </div>
        )}
      </div>
    </main>
  );
}

export default function ShiftPage() {
  return (
    <PosShell activePath="/shift/">
      <ShiftPageContent />
    </PosShell>
  );
}
