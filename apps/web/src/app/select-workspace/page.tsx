"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiError, apiFetch, selectedTenantId } from "@/lib/api";

type Option = { id: string; code: string; name: string };
type Branch = { id: string; code: string; name: string; warehouses: Option[]; registers: Option[] };

export default function SelectWorkspacePage() {
  const router = useRouter();
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [registerId, setRegisterId] = useState("");
  const [state, setState] = useState<"loading" | "ready" | "empty" | "error">("loading");
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!window.localStorage.getItem("nova.access_token") || !selectedTenantId()) {
      queueMicrotask(() => {
        setState("error");
        setMessage("Select a store first to view its work locations.");
      });
      return;
    }
    apiFetch("/api/v1/workspace/locations")
      .then(async (response) => {
        if (!response.ok) throw await apiError(response, "Work locations could not be loaded.");
        const body = (await response.json()) as { data?: Branch[] };
        const rows = body.data ?? [];
        setBranches(rows);
        setBranchId(rows[0]?.id ?? "");
        setState(rows.length === 0 ? "empty" : "ready");
      })
      .catch((cause: unknown) => {
        setState("error");
        setMessage(cause instanceof Error ? cause.message : "Work locations could not be loaded.");
      });
  }, []);

  const branch = branches.find((item) => item.id === branchId);
  function chooseBranch(id: string): void {
    setBranchId(id);
    setWarehouseId("");
    setRegisterId("");
  }
  function continueToDashboard(): void {
    if (!branchId || !warehouseId || !registerId) return;
    window.localStorage.setItem("nova.branch_id", branchId);
    window.localStorage.setItem("nova.warehouse_id", warehouseId);
    window.localStorage.setItem("nova.register_id", registerId);
    router.push("/dashboard/");
  }

  return <main className="min-h-screen bg-[#f7f9fd] text-slate-900"><div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 py-8 sm:px-8 sm:py-12"><header className="mb-8 flex items-start justify-between gap-4"><div><p className="mb-2 text-[11px] font-medium uppercase tracking-[0.18em] text-emerald-700">Workspace access</p><h1 className="text-2xl font-normal tracking-tight sm:text-[1.75rem]">Choose your work location</h1><p className="mt-2 max-w-lg text-sm text-slate-500">Select the branch, warehouse, and register you are operating today.</p></div><span className="hidden rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium tracking-[0.04em] text-slate-900 sm:inline-flex">NIU <span className="text-emerald-600">POS</span></span></header>
    {state === "loading" && <p className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">Loading your work locations…</p>}
    {state === "error" && <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900" role="alert">{message}</div>}
    {state === "empty" && <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">No active branches are available for this store.</div>}
    {state === "ready" && <div className="space-y-4"><div className="flex flex-wrap gap-2" role="tablist" aria-label="Branches">{branches.map((item) => <button className={`rounded-lg border px-3 py-2 text-sm transition ${item.id === branchId ? "border-emerald-500 bg-emerald-50 font-medium text-emerald-800" : "border-slate-200 bg-white text-slate-600 hover:border-emerald-300"}`} key={item.id} onClick={() => chooseBranch(item.id)} role="tab" aria-selected={item.id === branchId} type="button">{item.name}<span className="ml-1.5 text-xs text-slate-400">{item.code}</span></button>)}</div><section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_16px_rgba(15,23,42,.04)]"><p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">{branch?.name ?? "Branch"}</p><div className="mt-4 grid gap-4 sm:grid-cols-2"><label className="block text-xs font-medium text-slate-700">Warehouse<select className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)}><option value="">Select warehouse</option>{branch?.warehouses.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}</select></label><label className="block text-xs font-medium text-slate-700">Register<select className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" value={registerId} onChange={(event) => setRegisterId(event.target.value)}><option value="">Select register</option>{branch?.registers.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}</select></label></div>{branch && (branch.warehouses.length === 0 || branch.registers.length === 0) && <p className="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">This branch needs an active warehouse and register before a sale can begin.</p>}<button className="mt-5 flex h-11 w-full items-center justify-center rounded-lg bg-emerald-600 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" disabled={!warehouseId || !registerId} onClick={continueToDashboard} type="button">Continue to workspace <span className="ml-2" aria-hidden="true">→</span></button></section></div>}
    <div className="mt-auto pt-8 text-sm"><Link className="font-medium text-slate-500 hover:text-slate-900" href="/select-store/">← Back to stores</Link></div></div></main>;
}
