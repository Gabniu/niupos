"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiError, apiFetch } from "@/lib/api";

type Tenant = { id: string; name: string; jurisdictionCode: string };

export default function SelectStorePage() {
  const router = useRouter();
  const [stores, setStores] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = window.localStorage.getItem("nova.access_token");
    if (!token) {
      queueMicrotask(() => {
        setLoading(false);
        setError("Sign in first to view your available stores.");
      });
      return;
    }
    apiFetch("/api/v1/auth/tenants")
      .then(async (response) => {
        if (!response.ok) throw await apiError(response, "Your store list could not be loaded.");
        const body = (await response.json()) as { data?: Tenant[] };
        setStores(body.data ?? []);
      })
      .catch((cause: unknown) => setError(cause instanceof Error ? cause.message : "Your store list could not be loaded."))
      .finally(() => setLoading(false));
  }, []);

  return (
    <main className="min-h-screen bg-[#f7f9fd] text-slate-900 lg:flex">
      <section className="relative hidden min-h-screen overflow-hidden bg-slate-950 lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:p-12">
        <div className="absolute inset-0 bg-cover bg-center" style={{ backgroundImage: "linear-gradient(180deg, rgba(3, 16, 12, .12), rgba(3, 16, 12, .48)), url('/auth-carousel/grocery.jpg')" }} aria-hidden="true" />
        <div className="relative z-10 flex items-center gap-3 text-white"><span className="inline-flex rounded-lg bg-black/20 px-3 py-2 font-[var(--font-hanken)] text-lg font-medium tracking-[0.04em] backdrop-blur-sm">NIU <span className="text-emerald-300">POS</span></span></div>
        <div className="relative z-10 max-w-md text-white"><p className="mb-3 text-xs font-medium uppercase tracking-[0.2em] text-emerald-200">One account, every store</p><h1 className="text-2xl font-medium leading-tight tracking-tight lg:text-3xl">Choose where you&apos;re working today.</h1><p className="mt-4 max-w-sm text-sm leading-6 text-white/75">Connect to the right store terminal before you begin a sale, review stock, or open a shift.</p></div>
        <p className="relative z-10 text-xs text-white/70">Tenant access is isolated and audited.</p>
      </section>
      <section className="flex min-h-screen w-full items-center justify-center px-4 py-8 sm:px-8 sm:py-10 lg:w-1/2 lg:px-16">
        <div className="w-full max-w-lg">
          <div className="mb-9 lg:hidden"><span className="inline-flex rounded-lg bg-emerald-50 px-3 py-2 font-[var(--font-hanken)] text-lg font-medium tracking-[0.04em]">NIU <span className="text-emerald-600">POS</span></span></div>
          <header className="mb-6"><p className="mb-2 text-xs font-medium uppercase tracking-[0.18em] text-emerald-700">Store access</p><h2 className="text-xl font-medium tracking-tight sm:text-[1.75rem]">Select your store</h2><p className="mt-2 text-sm text-slate-500">Choose an admitted location to continue to the POS workspace.</p></header>
          {loading && <p className="rounded-xl border border-slate-200 bg-white p-5 text-xs text-slate-500">Loading your admitted stores…</p>}
          {!loading && error && <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-xs text-amber-900" role="alert">{error}</div>}
          {!loading && !error && stores.length === 0 && <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-medium text-slate-900">No workspace yet</p><p className="mt-1 text-xs leading-5 text-slate-500">Create your organization and choose how you want to operate before adding a store or register.</p><Link className="mt-4 inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white transition hover:bg-slate-800" href="/onboarding/">Start setup <span className="ml-2" aria-hidden="true">→</span></Link></div>}
          {!loading && !error && stores.length > 0 && <div className="space-y-3" role="list" aria-label="Available stores">
            {stores.map((store, index) => <button className={`group flex w-full items-center gap-3 rounded-xl border bg-white p-3.5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-300 sm:p-4 ${index === 0 ? "border-emerald-400 ring-1 ring-emerald-100" : "border-slate-200"}`} key={store.id} onClick={() => { window.localStorage.setItem("nova.tenant_id", store.id); router.push("/select-workspace/"); }} type="button"><span className={`grid size-10 shrink-0 place-items-center rounded-lg text-sm font-medium ${index === 0 ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-600"}`}>{store.name.slice(0, 1).toUpperCase()}</span><span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium text-slate-900">{store.name}</span><span className="mt-0.5 block truncate text-xs text-slate-500">{store.jurisdictionCode}</span><span className="mt-1.5 flex items-center gap-2 text-[11px] text-slate-500"><span className="size-1.5 rounded-full bg-emerald-500" />Active tenant access</span></span><span className={`hidden rounded-md px-3 py-2 text-xs font-medium sm:block ${index === 0 ? "bg-emerald-600 text-white" : "bg-slate-100 text-slate-600 group-hover:bg-emerald-600 group-hover:text-white"}`}>Continue →</span></button>)}
          </div>}
          <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6 text-sm"><Link className="font-medium text-slate-500 hover:text-slate-900" href="/">← Back to login</Link><span className="text-xs text-slate-400">{stores.length} locations available</span></div>
        </div>
      </section>
    </main>
  );
}
