"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";

import { Field } from "@/components/field";
import { Modal } from "@/components/modal";
import { Pagination, SearchBox } from "@/components/table-tools";
import { authClient } from "@/lib/auth-client";

type ManagedUser = { id: string; name: string; email: string; role?: string | null; banned?: boolean | null; createdAt?: Date | string };
type ManagedSession = { id: string; expiresAt: Date | string; ipAddress?: string | null; userAgent?: string | null };

const actionButton = "h-9 rounded-lg bg-emerald-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700";

function UserIcon() {
  return <svg aria-hidden="true" className="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="8" r="3.2" strokeWidth="1.7" /><path strokeLinecap="round" strokeWidth="1.7" d="M5 20c.8-3.6 3.1-5.4 7-5.4s6.2 1.8 7 5.4" /></svg>;
}

export default function UsersPage() {
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [pending, setPending] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedUser, setSelectedUser] = useState<ManagedUser | null>(null);
  const [sessions, setSessions] = useState<ManagedSession[]>([]);
  const [sessionsPending, setSessionsPending] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);

  async function load() {
    setPending(true);
    const result = await authClient.admin.listUsers({ query: { limit: 100, offset: 0, sortBy: "createdAt", sortDirection: "desc" } });
    setPending(false);
    if (result.error) {
      setError(result.error.message ?? "Users could not be loaded.");
      return;
    }
    setUsers((result.data?.users ?? []) as ManagedUser[]);
  }

  useEffect(() => {
    const timer = window.setTimeout(() => { void load(); }, 0);
    return () => window.clearTimeout(timer);
  }, []);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    return term ? users.filter((user) => `${user.name} ${user.email} ${user.role ?? "user"}`.toLowerCase().includes(term)) : users;
  }, [users, query]);
  const pageSize = 10;
  const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const visible = filtered.slice((page - 1) * pageSize, page * pageSize);

  function search(value: string) {
    setQuery(value);
    setPage(1);
  }

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const form = new FormData(event.currentTarget);
    const result = await authClient.admin.createUser({ name: String(form.get("name") ?? ""), email: String(form.get("email") ?? ""), password: String(form.get("password") ?? ""), role: String(form.get("role") ?? "user") as "user" | "admin" });
    if (result.error) {
      setError(result.error.message ?? "User could not be created.");
      return;
    }
    event.currentTarget.reset();
    setCreateOpen(false);
    await load();
  }

  async function inspectSessions(user: ManagedUser) {
    setSelectedUser(user);
    setSessionsPending(true);
    const result = await authClient.admin.listUserSessions({ userId: user.id });
    setSessionsPending(false);
    if (result.error) {
      setError(result.error.message ?? "Sessions could not be loaded.");
      return;
    }
    setSessions((result.data?.sessions ?? []) as ManagedSession[]);
  }

  return <>
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-emerald-700">People</p>
        <h1 className="mt-1.5 text-xl font-normal tracking-tight">Users and platform roles</h1>
        <p className="mt-1.5 text-sm text-slate-500">Live users and roles returned by Better Auth.</p>
      </div>
      <div className="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
        <SearchBox onChange={search} placeholder="Search people" value={query} />
        <button className={actionButton} onClick={() => setCreateOpen(true)} type="button">+ Add user</button>
      </div>
    </div>
    <div className="mt-4 flex items-center justify-between px-1 text-xs text-slate-500">
      <span>{pending ? "Loading users..." : `${filtered.length} users`}</span>
      {query && <button className="text-emerald-700 hover:text-emerald-800" onClick={() => search("")} type="button">Clear search</button>}
    </div>
    <section className="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      {!pending && filtered.length === 0 && <div className="grid place-items-center px-6 py-12 text-center"><img alt="Add your first user" className="mb-4 h-36 w-36 object-contain" src="/empty-drag-add.svg" /><p className="text-sm font-medium text-slate-700">{query ? "No matching users" : "No users yet"}</p>{!query && <><p className="mt-1 max-w-xs text-xs leading-5 text-slate-500">Add a person to begin managing access.</p><button className="mt-4 h-9 rounded-lg bg-emerald-600 px-3 text-xs font-medium text-white" onClick={() => setCreateOpen(true)} type="button">Add user</button></>}</div>}
      {visible.length > 0 && <div className="overflow-x-auto"><table className="w-full min-w-[640px] text-left text-xs"><thead className="bg-slate-50 text-[10px] uppercase tracking-[0.12em] text-slate-400"><tr><th className="px-4 py-3 font-medium">Person</th><th className="px-4 py-3 font-medium">Role</th><th className="px-4 py-3 font-medium">Status</th><th className="px-4 py-3 font-medium">Created</th><th className="px-4 py-3 text-right font-medium">Actions</th></tr></thead><tbody className="divide-y divide-slate-100">{visible.map((user) => <tr className="transition hover:bg-slate-50/70" key={user.id}><td className="px-4 py-3"><div className="flex items-center gap-3"><span aria-hidden="true" className="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-700"><UserIcon /></span><div className="min-w-0"><p className="truncate text-sm font-medium text-slate-800">{user.name}</p><p className="truncate text-xs text-slate-500">{user.email}</p></div></div></td><td className="px-4 py-3"><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] uppercase tracking-wide text-slate-600">{user.role ?? "user"}</span></td><td className="px-4 py-3 text-emerald-700">{user.banned ? "Banned" : "Active"}</td><td className="px-4 py-3 text-slate-500">{user.createdAt ? new Date(user.createdAt).toLocaleDateString() : "-"}</td><td className="px-4 py-3 text-right"><button className="rounded-md px-2 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50" onClick={() => void inspectSessions(user)} type="button">Inspect sessions</button></td></tr>)}</tbody></table></div>}
      <Pagination onChange={setPage} page={page} pages={pages} />
    </section>
    {createOpen && <Modal description="Administrative provisioning only." onClose={() => setCreateOpen(false)} title="Add user"><form className="space-y-4" onSubmit={create}><Field label="Full name" name="name" required maxLength={100} /><Field label="Email" name="email" type="email" required /><Field label="Temporary password" name="password" type="password" required minLength={12} /><label className="block text-xs font-medium text-slate-700">Platform role<select className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm" name="role"><option value="user">User</option><option value="admin">Administrator</option></select></label><button className={actionButton} type="submit">Create user</button></form></Modal>}
    {selectedUser && <Modal description="Live sessions returned by Better Auth; tokens are never displayed." onClose={() => setSelectedUser(null)} title={`Sessions for ${selectedUser.email}`}>{sessionsPending && <p className="text-sm text-slate-500">Loading sessions...</p>}{!sessionsPending && sessions.length === 0 && <p className="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No sessions found.</p>}{sessions.map((session) => <div className="grid gap-2 border-b border-slate-100 py-3 text-xs last:border-0 sm:grid-cols-3" key={session.id}><span>{session.ipAddress ?? "Unknown address"}</span><span className="truncate text-slate-500">{session.userAgent ?? "Unknown client"}</span><span className="text-right text-slate-400">Expires {new Date(session.expiresAt).toLocaleString()}</span></div>)}</Modal>}
    {error && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">{error}</p>}
  </>;
}
