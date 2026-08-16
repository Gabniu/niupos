"use client";

export const dynamic = "force-dynamic";

import { useEffect, useState } from "react";

import { AuthFrame } from "@/components/auth-frame";
import { primaryButton } from "@/components/field";
import { authClient } from "@/lib/auth-client";

export default function ConsentPage() {
  const [clientId, setClientId] = useState("Unknown application");
  const [scope, setScope] = useState("openid");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => { const timer = window.setTimeout(() => { const query = new URLSearchParams(window.location.search); setClientId(query.get("client_id") ?? "Unknown application"); setScope(query.get("scope") ?? "openid"); }, 0); return () => window.clearTimeout(timer); }, []);

  async function decide(accept: boolean) {
    setPending(true); setError(null);
    const result = await authClient.oauth2.consent({ accept, scope });
    setPending(false);
    if (result.error) setError(result.error.message ?? "Consent could not be recorded.");
  }

  return <AuthFrame eyebrow="Application consent" title="Allow access?" description="Review the identity information this application is requesting.">
    <div className="rounded-xl border border-slate-200 bg-white p-4"><p className="text-xs text-slate-500">Application</p><p className="mt-1 break-all text-sm font-medium text-slate-800">{clientId}</p><p className="mt-4 text-xs text-slate-500">Requested permissions</p><div className="mt-2 flex flex-wrap gap-2">{scope.split(" ").filter(Boolean).map((item) => <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600" key={item}>{item}</span>)}</div></div>
    {error && <p className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}
    <div className="mt-4 grid grid-cols-2 gap-3"><button className="h-11 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 transition hover:bg-slate-50" disabled={pending} onClick={() => decide(false)} type="button">Deny</button><button className={primaryButton} disabled={pending} onClick={() => decide(true)} type="button">Allow</button></div>
  </AuthFrame>;
}
