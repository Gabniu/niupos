"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";

import { Field, primaryButton } from "@/components/field";
import { Modal } from "@/components/modal";
import { Pagination, SearchBox } from "@/components/table-tools";
import { authClient } from "@/lib/auth-client";

type OAuthClient = {
  client_id: string;
  client_name?: string | null;
  redirect_uris?: string[];
  token_endpoint_auth_method?: string;
  type?: string;
  disabled?: boolean;
};

type CreatedSecret = { name: string; clientId: string; secret: string };

function errorMessage(error: unknown, fallback: string) {
  return error instanceof Error && error.message ? error.message : fallback;
}

export default function ApplicationsPage() {
  const [clients, setClients] = useState<OAuthClient[]>([]);
  const [createdSecret, setCreatedSecret] = useState<CreatedSecret | null>(null);
  const [pending, setPending] = useState(true);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [rotating, setRotating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [createError, setCreateError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [rotateClient, setRotateClient] = useState<OAuthClient | null>(null);
  const [rotateCode, setRotateCode] = useState("");
  const [rotateError, setRotateError] = useState<string | null>(null);
  const [selected, setSelected] = useState<OAuthClient | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);

  async function load() {
    setPending(true);
    setError(null);
    try {
      const result = await authClient.oauth2.getClients();
      if (result.error) {
        setError(result.error.message ?? "Applications could not be loaded.");
        return;
      }
      setClients((result.data ?? []) as OAuthClient[]);
    } catch (loadError) {
      setError(errorMessage(loadError, "Applications could not be loaded."));
    } finally {
      setPending(false);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => { void load(); }, 0);
    return () => window.clearTimeout(timer);
  }, []);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return clients;
    return clients.filter((client) => `${client.client_name ?? ""} ${client.client_id} ${(client.redirect_uris ?? []).join(" ")}`.toLowerCase().includes(term));
  }, [clients, query]);
  const pageSize = 10;
  const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const visible = filtered.slice((page - 1) * pageSize, page * pageSize);
  const selectedClients = clients.filter((client) => selectedIds.has(client.client_id));
  const allVisibleSelected = visible.length > 0 && visible.every((client) => selectedIds.has(client.client_id));

  function openCreate() {
    setCreateError(null);
    setError(null);
    setSuccess(null);
    setCreateOpen(true);
  }

  function openRotate(client: OAuthClient) {
    setRotateError(null);
    setRotateCode("");
    setRotateClient(client);
    setSelected(null);
  }

  function toggleSelected(clientId: string) {
    setSelectedIds((current) => {
      const next = new Set(current);
      if (next.has(clientId)) next.delete(clientId); else next.add(clientId);
      return next;
    });
  }

  function toggleVisible() {
    setSelectedIds((current) => {
      const next = new Set(current);
      if (allVisibleSelected) visible.forEach((client) => next.delete(client.client_id));
      else visible.forEach((client) => next.add(client.client_id));
      return next;
    });
  }

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (creating) return;
    const formElement = event.currentTarget;
    setCreating(true);
    setCreateError(null);
    setError(null);
    setSuccess(null);
    try {
      const form = new FormData(formElement);
      const isPublic = form.get("kind") === "public";
      const name = String(form.get("name") ?? "").trim();
      const result = await authClient.oauth2.createClient({
        client_name: name,
        redirect_uris: String(form.get("redirectUri") ?? "").split("\n").map((value) => value.trim()).filter(Boolean),
        token_endpoint_auth_method: isPublic ? "none" : "client_secret_basic",
      });
      if (result.error) {
        setCreateError(result.error.message ?? "Application could not be registered.");
        return;
      }
      const data = result.data as { client_id?: string; client_secret?: string } | undefined;
      const secret = data?.client_secret;
      if (typeof secret === "string" && data?.client_id) {
        setCreatedSecret({ name, clientId: data.client_id, secret });
      } else {
        setSuccess(`${name || "Application"} registered successfully.`);
      }
      formElement.reset();
      setCreateOpen(false);
      await load();
    } catch (createErrorValue) {
      setCreateError(errorMessage(createErrorValue, "Application could not be registered. Try again."));
    } finally {
      setCreating(false);
    }
  }

  async function deleteSelected() {
    if (deleting || selectedIds.size === 0) return;
    setDeleting(true);
    setError(null);
    setSuccess(null);
    let deleted = 0;
    const remaining = new Set(selectedIds);
    try {
      for (const clientId of selectedIds) {
        const result = await authClient.oauth2.deleteClient({ client_id: clientId });
        if (result.error) throw new Error(result.error.message ?? "Application could not be deleted.");
        deleted += 1;
        remaining.delete(clientId);
      }
      setSelectedIds(new Set());
      setDeleteOpen(false);
      setSuccess(`${deleted} application${deleted === 1 ? "" : "s"} deleted.`);
      await load();
    } catch (deleteErrorValue) {
      setSelectedIds(remaining);
      setError(deleted > 0 ? `${deleted} application${deleted === 1 ? "" : "s"} deleted, but the rest could not be deleted.` : errorMessage(deleteErrorValue, "Applications could not be deleted."));
    } finally {
      setDeleting(false);
    }
  }

  async function rotateSecret(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!rotateClient || rotating) return;
    setRotating(true);
    setRotateError(null);
    try {
      const response = await fetch("/api/control/applications/rotate-secret", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ client_id: rotateClient.client_id }) });
      const body = await response.json() as { data?: { client_id?: string; client_secret?: string } | { data?: { client_id?: string; client_secret?: string } }; message?: string };
      if (!response.ok) throw new Error(body.message ?? "The client secret could not be rotated.");
      const raw = body.data as unknown;
      const result = raw && typeof raw === "object" && "data" in raw
        ? ((raw as { data?: { client_id?: string; client_secret?: string } }).data ?? {})
        : ((raw as { client_id?: string; client_secret?: string } | undefined) ?? {});
      if (!result?.client_secret) throw new Error("The server did not return a replacement client secret.");
      setRotateClient(null);
      setSelected(null);
      setRotateCode("");
      setCreatedSecret({ name: rotateClient.client_name ?? "Application", clientId: result.client_id ?? rotateClient.client_id, secret: result.client_secret });
    } catch (rotateErrorValue) {
      setRotateError(errorMessage(rotateErrorValue, "The client secret could not be rotated."));
    } finally {
      setRotating(false);
    }
  }

  return <>
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div><p className="text-[10px] font-medium uppercase tracking-[0.18em] text-emerald-700">Applications</p><h1 className="mt-1.5 text-xl font-normal tracking-tight">Connected authentication clients</h1><p className="mt-1.5 text-sm text-slate-500">OAuth 2.1 and OpenID Connect clients registered in Better Auth.</p></div>
      <div className="flex items-center gap-2">
        {selectedIds.size > 0 && <button className="h-9 rounded-lg border border-red-200 px-3 text-xs font-medium text-red-700 transition hover:bg-red-50" onClick={() => setDeleteOpen(true)} type="button">Delete {selectedIds.size}</button>}
        <button className="h-9 rounded-lg bg-emerald-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700 hover:shadow-md" onClick={openCreate} type="button">+ Register app</button>
      </div>
    </div>

    {success && <p aria-live="polite" className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800" role="status">{success}</p>}
    {error && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}

    <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"><p className="text-xs font-medium text-slate-600">{pending ? "Loading applications…" : `${filtered.length} applications`}</p><SearchBox onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search applications" value={query} /></div>
      {!pending && filtered.length === 0 && <div className="grid place-items-center px-6 py-12 text-center"><img alt="Connect an application" className="mb-4 h-36 w-36 object-contain" src="/connected-application.svg" /><p className="text-sm font-medium text-slate-700">{query ? "No matching applications" : "No applications registered"}</p>{!query && <button className="mt-4 h-9 rounded-lg bg-emerald-600 px-3 text-xs font-medium text-white" onClick={openCreate} type="button">Register app</button>}</div>}
      {visible.length > 0 && <div className="overflow-x-auto"><table className="w-full min-w-[740px] text-left text-xs"><thead className="bg-slate-50 text-[10px] uppercase tracking-[0.12em] text-slate-400"><tr><th className="w-10 px-4 py-3"><input aria-label="Select all visible applications" checked={allVisibleSelected} onChange={toggleVisible} type="checkbox" /></th><th className="px-2 py-3 font-medium">Application</th><th className="px-4 py-3 font-medium">Type</th><th className="px-4 py-3 font-medium">Redirect URIs</th><th className="px-4 py-3 text-right font-medium">Actions</th></tr></thead><tbody className="divide-y divide-slate-100">{visible.map((client) => <tr className="transition hover:bg-slate-50/70" key={client.client_id}><td className="px-4 py-3"><input aria-label={`Select ${client.client_name || client.client_id}`} checked={selectedIds.has(client.client_id)} onChange={() => toggleSelected(client.client_id)} type="checkbox" /></td><td className="px-2 py-3"><p className="text-sm font-medium text-slate-800">{client.client_name || "Unnamed application"}</p><p className="mt-0.5 break-all text-[11px] text-slate-500">{client.client_id}</p></td><td className="px-4 py-3"><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] text-slate-600">{client.token_endpoint_auth_method === "none" ? "Public" : "Confidential"}</span></td><td className="max-w-[20rem] px-4 py-3"><p className="truncate text-slate-500">{client.redirect_uris?.join(" · ") || "—"}</p></td><td className="px-4 py-3 text-right"><div className="flex justify-end gap-1"><button className="rounded-md px-2 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50" onClick={() => setSelected(client)} type="button">Inspect</button>{client.token_endpoint_auth_method !== "none" && <button className="rounded-md px-2 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-50" onClick={() => openRotate(client)} type="button">Rotate</button>}</div></td></tr>)}</tbody></table></div>}
      <Pagination onChange={setPage} page={page} pages={pages} />
    </section>

    {createOpen && <Modal description="Redirect URIs must be exact and pre-registered. Confidential clients receive a secret after creation; public clients use PKCE and do not have one." onClose={() => { if (!creating) setCreateOpen(false); }} title="Register application"><form className="space-y-4" onSubmit={create}><Field label="Application name" name="name" required maxLength={100} /><label className="block text-xs font-medium text-slate-700">Redirect URIs<textarea className="mt-1.5 min-h-24 w-full resize-y rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50" disabled={creating} name="redirectUri" required /></label><label className="block text-xs font-medium text-slate-700">Client type<select className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50" disabled={creating} name="kind"><option value="confidential">Confidential web/service client — generates a secret</option><option value="public">Public mobile/browser client — no secret</option></select></label>{createError && <p aria-live="assertive" className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{createError}</p>}<button aria-busy={creating} className={primaryButton} disabled={creating} type="submit">{creating ? <><span aria-hidden="true" className="mr-2 size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />Registering application…</> : "Register application"}</button></form></Modal>}

    {deleteOpen && <Modal description="This permanently removes the selected OAuth clients and prevents new sign-ins through them." onClose={() => { if (!deleting) setDeleteOpen(false); }} title={`Delete ${selectedIds.size} application${selectedIds.size === 1 ? "" : "s"}`}><div className="space-y-4"><div className="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">{selectedClients.map((client) => <p className="truncate py-1" key={client.client_id}>{client.client_name || client.client_id}</p>)}</div><p className="text-xs leading-5 text-slate-500">Continue only if these clients are no longer used by any application. Existing tokens may remain valid until their normal expiry.</p><div className="flex justify-end gap-2"><button className="h-10 rounded-lg border border-slate-200 px-3 text-xs font-medium text-slate-700 hover:bg-slate-50" disabled={deleting} onClick={() => setDeleteOpen(false)} type="button">Cancel</button><button aria-busy={deleting} className="h-10 rounded-lg bg-red-600 px-3 text-xs font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" disabled={deleting} onClick={() => void deleteSelected()} type="button">{deleting ? "Deleting…" : "Delete applications"}</button></div></div></Modal>}

    {selected && <Modal description="Registered OAuth client details. The original secret cannot be retrieved; rotate it with MFA to issue a replacement." onClose={() => setSelected(null)} title={selected.client_name || "Unnamed application"}><dl className="divide-y divide-slate-100 text-xs"><div className="py-3"><dt className="text-slate-500">Client ID</dt><dd className="mt-1 break-all font-medium text-slate-800">{selected.client_id}</dd></div><div className="flex justify-between gap-4 py-3"><dt className="text-slate-500">Type</dt><dd className="font-medium text-slate-800">{selected.token_endpoint_auth_method === "none" ? "Public" : "Confidential"}</dd></div><div className="py-3"><dt className="text-slate-500">Redirect URIs</dt><dd className="mt-1 space-y-1 break-all font-medium text-slate-800">{selected.redirect_uris?.map((uri) => <p key={uri}>{uri}</p>)}</dd></div></dl>{selected.token_endpoint_auth_method !== "none" && <button className="mt-4 h-10 w-full rounded-lg border border-amber-200 bg-amber-50 text-xs font-medium text-amber-900 transition hover:bg-amber-100" onClick={() => openRotate(selected)} type="button">Rotate client secret (MFA)</button>}</Modal>}
    {rotateClient && <Modal description="This invalidates the current secret. Enter your six-digit authenticator code to issue a replacement secret." onClose={() => { if (!rotating) setRotateClient(null); }} title="Rotate client secret"><form className="space-y-4" onSubmit={rotateSecret}><label className="block text-xs font-medium text-slate-700">Authenticator code<input autoComplete="one-time-code" className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 px-3 text-center text-sm tracking-[0.3em] outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50" disabled={rotating} inputMode="numeric" maxLength={6} minLength={6} onChange={(event) => setRotateCode(event.target.value.replace(/\D/g, "").slice(0, 6))} pattern="[0-9]{6}" placeholder="000000" required value={rotateCode} /></label>{rotateError && <p aria-live="assertive" className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{rotateError}</p>}<button aria-busy={rotating} className="h-11 w-full rounded-lg bg-amber-700 px-4 text-sm font-medium text-white transition hover:bg-amber-800 disabled:cursor-not-allowed disabled:opacity-60" disabled={rotating || rotateCode.length !== 6} type="submit">{rotating ? "Rotating secret…" : "Verify MFA and rotate"}</button></form></Modal>}
    {createdSecret && <Modal description="Copy this secret now and store it in the consuming app's server-side secret manager. It will not be displayed again." onClose={() => setCreatedSecret(null)} title="Application registered"><div className="space-y-4"><div><p className="text-xs text-slate-500">Client ID</p><code className="mt-1 block break-all rounded-lg bg-slate-50 p-3 text-xs text-slate-800">{createdSecret.clientId}</code></div><div><p className="text-xs text-slate-500">Client secret</p><code className="mt-1 block break-all rounded-lg bg-slate-950 p-3 text-xs text-emerald-200">{createdSecret.secret}</code></div><button className="h-10 w-full rounded-lg border border-slate-200 text-xs font-medium text-slate-700 transition hover:bg-slate-50" onClick={() => void navigator.clipboard.writeText(createdSecret.secret)} type="button">Copy client secret</button></div></Modal>}
  </>;
}
