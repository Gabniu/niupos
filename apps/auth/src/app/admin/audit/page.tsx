"use client";

import { useEffect, useState } from "react";

type AuditEntry = { id: string; actor_user_id: string | null; event_type: string; subject_key: string; previous_value: unknown; next_value: unknown; occurredAt: string };

export default function AuditPage() {
  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [pending, setPending] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void fetch("/api/control/audit?limit=100", { cache: "no-store" }).then(async (response) => {
        const body = await response.json() as { data?: AuditEntry[]; message?: string };
        if (!response.ok) throw new Error(body.message ?? "Audit entries could not be loaded.");
        setEntries(body.data ?? []);
      }).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : "Audit entries could not be loaded.")).finally(() => setPending(false));
    }, 0);
    return () => window.clearTimeout(timer);
  }, []);

  return <><p className="text-[10px] font-medium uppercase tracking-[0.18em] text-emerald-700">Audit</p><h1 className="mt-1.5 text-xl font-normal tracking-tight">Configuration history</h1><p className="mt-1.5 text-sm text-slate-500">Immutable control-plane changes, loaded from the authentication database.</p><section className="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white"><div className="border-b border-slate-100 px-4 py-3 text-xs font-medium text-slate-600">{pending ? "Loading audit history…" : `${entries.length} recorded events`}</div>{!pending && entries.length === 0 && <p className="p-5 text-sm text-slate-500">No configuration events have been recorded.</p>}{entries.map((entry) => <div className="grid min-w-[42rem] gap-3 border-b border-slate-100 px-4 py-3 text-xs last:border-0 sm:grid-cols-[10rem_1fr_9rem]" key={entry.id}><div><p className="font-medium text-slate-700">{entry.event_type}</p><p className="mt-1 text-slate-400">{new Date(entry.occurredAt).toLocaleString()}</p></div><div><p className="text-slate-700">{entry.subject_key}</p><p className="mt-1 break-all text-slate-400">{entry.actor_user_id ? `Actor ${entry.actor_user_id}` : "System"}</p></div><div className="text-right text-slate-400"><p>Previous → next</p><p className="mt-1 break-all">{JSON.stringify(entry.next_value)}</p></div></div>)}</section>{error && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}</>;
}
