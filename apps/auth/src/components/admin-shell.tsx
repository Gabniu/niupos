"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { Brand } from "@/components/brand";
import { authClient } from "@/lib/auth-client";

type IconName = "overview" | "people" | "organizations" | "applications" | "consents" | "capabilities" | "configuration" | "audit";
const items: { href: string; label: string; icon: IconName }[] = [
  { href: "/admin", label: "Overview", icon: "overview" },
  { href: "/admin/users", label: "People", icon: "people" },
  { href: "/admin/organizations", label: "Organizations", icon: "organizations" },
  { href: "/admin/apps", label: "Applications", icon: "applications" },
  { href: "/admin/consents", label: "Consents", icon: "consents" },
  { href: "/admin/capabilities", label: "Capabilities", icon: "capabilities" },
  { href: "/admin/settings", label: "Configuration", icon: "configuration" },
  { href: "/admin/audit", label: "Audit", icon: "audit" },
];

function NavIcon({ name }: { name: IconName }) {
  const common = { className: "size-4 shrink-0", fill: "none", stroke: "currentColor", strokeLinecap: "round" as const, strokeLinejoin: "round" as const, strokeWidth: 1.7, viewBox: "0 0 24 24" };
  const paths: Record<IconName, React.ReactNode> = {
    overview: <><rect height="5" rx="1" width="5" x="4" y="4" /><rect height="5" rx="1" width="5" x="15" y="4" /><rect height="5" rx="1" width="5" x="4" y="15" /><rect height="5" rx="1" width="5" x="15" y="15" /></>,
    people: <><circle cx="9" cy="8" r="3" /><path d="M3.5 20c.6-3 2.5-4.5 5.5-4.5s4.9 1.5 5.5 4.5" /><path d="M16 5.5a3 3 0 0 1 0 5.8M16 15.5c2.5.3 4 1.8 4.5 4.5" /></>,
    organizations: <><rect height="15" rx="1.5" width="14" x="5" y="5" /><path d="M9 9h2m2 0h2M9 13h2m2 0h2M9 17h2m2 0h2" /></>,
    applications: <><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="m4.5 7.5 7.5 4 7.5-4M12 12v9" /></>,
    consents: <><path d="M6 4h12v16H6z" /><path d="M9 8h6M9 12h6M9 16h4" /></>,
    capabilities: <><path d="M4 7h16M4 12h16M4 17h16" /><circle cx="9" cy="7" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="11" cy="17" r="1.5" /></>,
    configuration: <><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" /><circle cx="12" cy="12" r="4" /></>,
    audit: <><path d="M5 4h14v16H5z" /><path d="M8 8h8M8 12h8M8 16h5" /></>,
  };
  return <svg aria-hidden="true" {...common}>{paths[name]}</svg>;
}

export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [session, setSession] = useState<Awaited<ReturnType<typeof authClient.getSession>>["data"]>(null);
  const [isPending, setIsPending] = useState(true);
  const [profileOpen, setProfileOpen] = useState(false);
  useEffect(() => { const timer = window.setTimeout(() => { void authClient.getSession().then((result) => { setSession(result.data); setIsPending(false); }); }, 0); return () => window.clearTimeout(timer); }, []);

  if (isPending) return <main className="grid min-h-screen place-items-center bg-[#f6f8fb] text-sm text-slate-500">Loading identity workspace…</main>;
  if (!session) return <main className="grid min-h-screen place-items-center bg-[#f6f8fb] px-4"><div className="text-center"><p className="text-sm text-slate-600">Sign in to manage authentication.</p><Link className="mt-4 inline-block text-sm font-medium text-emerald-700" href="/sign-in">Go to sign in</Link></div></main>;

  return <main className="min-h-screen bg-[#f6f8fb] text-slate-950 lg:flex"><aside className="hidden w-56 shrink-0 flex-col border-r border-emerald-950/30 bg-[#064e3b] text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:max-h-screen lg:overflow-hidden"><div className="flex h-16 shrink-0 items-center border-b border-white/10 px-4"><Brand inverse /></div><nav className="mt-2 space-y-0.5 px-3">{items.map((item) => <Link className={`group flex h-9 items-center gap-3 rounded-lg px-3 text-sm transition-colors ${pathname === item.href ? "bg-white/10 font-medium text-white" : "text-white/65 hover:bg-white/10 hover:text-white"}`} href={item.href} key={item.href}><NavIcon name={item.icon} /><span>{item.label}</span></Link>)}</nav><div className="mt-auto border-t border-white/10 p-3"><button className="flex h-9 w-full items-center gap-3 rounded-lg border border-white/15 px-3 text-left text-xs text-white/70 transition-colors hover:bg-white/10 hover:text-white" onClick={async () => { await authClient.signOut(); router.push("/sign-in"); }} type="button"><NavIcon name="audit" /><span>Sign out</span></button></div></aside><section className="min-w-0 flex-1"><header className="relative flex h-16 items-center justify-between border-b border-slate-200 px-4 sm:px-6"><div className="lg:hidden"><Brand /></div><p className="hidden text-sm text-slate-500 lg:block">Identity workspace</p><div className="relative"><button aria-expanded={profileOpen} aria-haspopup="menu" className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-slate-100" onClick={() => setProfileOpen((value) => !value)} type="button"><span className="grid size-7 place-items-center rounded-full bg-emerald-100 text-xs font-medium text-emerald-800">{session.user.name?.slice(0, 1).toUpperCase() ?? "A"}</span><span className="hidden max-w-[12rem] truncate text-xs text-slate-600 sm:block">{session.user.email}</span><svg aria-hidden="true" className="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="m6 9 6 6 6-6" /></svg></button>{profileOpen && <div className="absolute right-0 top-11 z-20 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl" role="menu"><div className="border-b border-slate-100 px-3 py-2"><p className="truncate text-xs font-medium text-slate-800">{session.user.name}</p><p className="truncate text-[11px] text-slate-500">{session.user.email}</p><span className="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Administrator</span></div><button className="mt-2 flex w-full rounded-lg px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50" onClick={async () => { await authClient.signOut(); router.push("/sign-in"); }} role="menuitem" type="button">Sign out</button></div>}</div></header><nav className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-3 py-2 lg:hidden">{items.map((item) => <Link className={`flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-xs ${pathname === item.href ? "bg-emerald-50 font-medium text-emerald-800" : "text-slate-500"}`} href={item.href} key={item.href}><NavIcon name={item.icon} /><span>{item.label}</span></Link>)}</nav><div className="mx-auto max-w-6xl px-4 py-5 sm:px-6">{children}</div></section></main>;
}
