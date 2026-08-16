"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";
import { PosShell } from "@/components/PosShell";

type Receipt = {
  receipt_number: number;
  currency_code: string;
  net_minor: number;
  tax_minor: number;
  gross_minor: number;
  issued_at: string;
  lines: Array<{
    line_number: number;
    description: string;
    quantity: number;
    gross_minor: number;
  }>;
};

function ReceiptPageContent() {
  const params = useParams<{ receipt: string }>();
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    apiFetch(`/api/v1/receipts/${params.receipt}`)
      .then(async (response) => {
        if (!response.ok)
          throw await apiError(response, "The receipt could not be loaded.");
        const body = (await response.json()) as { data?: Receipt };
        if (!body.data) throw new Error("The receipt response was incomplete.");
        setReceipt(body.data);
      })
      .catch((cause: unknown) =>
        setError(
          cause instanceof Error
            ? cause.message
            : "The receipt could not be loaded.",
        ),
      );
  }, [params.receipt]);
  if (error)
    return (
      <main className="min-h-screen bg-[#f7f9fd] p-6 text-sm text-red-800">
        {error}
      </main>
    );
  if (!receipt)
    return (
      <main className="min-h-screen bg-[#f7f9fd] p-6 text-sm text-slate-500">
        Loading receipt…
      </main>
    );
  const money = (minor: number) =>
    new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: receipt.currency_code,
    }).format(minor / 100);
  return (
    <main className="min-h-screen bg-[#f7f9fd] px-4 py-8 text-slate-900">
      <div className="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_16px_rgba(15,23,42,.04)]">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
              NIU POS
            </p>
            <h1 className="mt-2 text-2xl font-normal tracking-tight">
              Receipt #{receipt.receipt_number}
            </h1>
          </div>
          <Link
            className="text-sm text-slate-500 hover:text-slate-900"
            href="/dashboard/"
          >
            Close
          </Link>
        </div>
        <div className="mt-6 divide-y divide-slate-100">
          {receipt.lines.map((line) => (
            <div
              className="flex justify-between gap-4 py-3 text-sm"
              key={line.line_number}
            >
              <span>
                {line.description} ×{line.quantity}
              </span>
              <span className="font-medium">{money(line.gross_minor)}</span>
            </div>
          ))}
        </div>
        <dl className="mt-5 space-y-2 border-t border-slate-200 pt-4 text-sm">
          <div className="flex justify-between">
            <dt className="text-slate-500">Net</dt>
            <dd>{money(receipt.net_minor)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Tax</dt>
            <dd>{money(receipt.tax_minor)}</dd>
          </div>
          <div className="flex justify-between text-base font-medium">
            <dt>Total</dt>
            <dd>{money(receipt.gross_minor)}</dd>
          </div>
        </dl>
        <p className="mt-5 text-xs text-slate-400">
          Issued {new Date(receipt.issued_at).toLocaleString()}
        </p>
      </div>
    </main>
  );
}

export default function ReceiptPage() {
  return (
    <PosShell activePath="/sale/">
      <ReceiptPageContent />
    </PosShell>
  );
}
