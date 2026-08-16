"use client";

import Link from "next/link";
import { ReactNode, useEffect, useState } from "react";
import { apiFetch, selectedTenantId } from "@/lib/api";

const navigation = [
  { label: "Setup", icon: "setup", path: "/onboarding/" },
  { label: "Dashboard", icon: "dashboard", path: "/dashboard/" },
  { label: "Sales", icon: "sales", path: "/sale/" },
  { label: "Products", icon: "products", path: "/products/" },
  { label: "Inventory", icon: "inventory", path: "/inventory/" },
  { label: "Reports", icon: "reports", path: "/reports/" },
  { label: "Settings", icon: "settings", path: "/settings/" },
];

function Icon({ name }: { name: string }) {
  const paths: Record<string, string> = {
    dashboard: "M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z",
    setup: "M12 3v4m0 10v4M3 12h4m10 0h4M5.6 5.6l2.8 2.8m7.2 7.2 2.8 2.8m0-12.8-2.8 2.8M8.4 15.6l-2.8 2.8",
    sales: "M5 19 19 5M9 5h10v10",
    products: "m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7",
    inventory: "M4 5h16v14H4zM8 9h8M8 13h5",
    reports: "M5 19V9m7 10V5m7 14v-7",
    settings:
      "M12 8a4 4 0 1 0 0 8 4 4 0 0 0-8 0Zm0-5v3m0 12v3m9-9h-3M6 12H3m15.36-6.36-2.12 2.12M7.76 16.24l-2.12 2.12m12.72 0-2.12-2.12M7.76 7.76 5.64 5.64",
    search: "m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0",
    bell: "M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4",
  };
  return (
    <svg
      aria-hidden="true"
      className="size-4"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth="1.7"
    >
      <path strokeLinecap="round" strokeLinejoin="round" d={paths[name]} />
    </svg>
  );
}

export function PosShell({
  children,
  activePath,
  railMode = "full",
}: {
  children: ReactNode;
  activePath: string;
  railMode?: "full" | "compact";
}) {
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [preferences, setPreferences] = useState({
    sidePanelVisible: true,
    kioskMode: false,
  });
  useEffect(() => {
    const token = window.localStorage.getItem("nova.access_token");
    if (!token || !selectedTenantId()) return;
    apiFetch("/api/v1/workspace/preferences")
      .then((response) =>
        response.ok
          ? (response.json() as Promise<{
              data?: { sidePanelVisible?: boolean; kioskMode?: boolean };
            }>)
          : null,
      )
      .then((body) => {
        if (body?.data)
          setPreferences({
            sidePanelVisible: body.data.sidePanelVisible !== false,
            kioskMode: body.data.kioskMode === true,
          });
      })
      .catch(() => undefined);
  }, []);
  const showRail = preferences.sidePanelVisible && !preferences.kioskMode;
  const compact = railMode === "compact";
  return (
    <div className="min-h-screen bg-[#f7f9fd] text-slate-900 lg:flex">
      {showRail && (
        <aside
          className={`sticky top-0 hidden h-screen max-h-screen shrink-0 flex-col overflow-hidden bg-[#09090b] text-white lg:flex ${compact ? "w-14" : "w-52"}`}
        >
          <div
            className={`flex h-16 shrink-0 items-center border-b border-white/10 ${compact ? "justify-center px-1" : "px-3"}`}
          >
            <Link
              aria-label="NIU POS dashboard"
              className={`font-[var(--font-hanken)] font-medium ${compact ? "text-[10px] tracking-[0.04em]" : "text-sm tracking-[0.04em]"}`}
              href="/dashboard/"
            >
              {compact ? (
                <span aria-hidden="true" className="grid size-5 place-items-center rounded-full border border-emerald-200/70 bg-emerald-400/55 text-white"><svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M17 10H7l1-4h8zM6 10h12v9H6zM9 14h6" /></svg></span>
              ) : (
                <>
                  <span className="tracking-[0.01em]">NIU <span className="text-emerald-300">POS</span></span>
                </>
              )}
            </Link>
          </div>
          <nav
            className={`mt-2 space-y-0.5 ${compact ? "px-1" : "px-1.5"}`}
            aria-label="Main navigation"
          >
            {navigation.map((item) => (
              <Link
                aria-label={compact ? item.label : undefined}
                className={`group flex items-center rounded-lg py-2 text-[13px] transition-colors duration-200 ${compact ? "justify-center px-1" : "gap-2.5 px-2"} ${item.path === activePath ? "bg-white/10 font-medium text-white" : "text-white/65 hover:bg-white/10 hover:text-white"}`}
                href={item.path}
                key={item.label}
              >
                <span className="grid size-5 place-items-center transition-transform duration-200 ease-out group-hover:-translate-y-px group-hover:scale-105">
                  <Icon name={item.icon} />
                </span>
                {!compact && item.label}
              </Link>
            ))}
          </nav>
          {!compact && (
            <div className="m-3 mt-auto rounded-xl border border-white/10 bg-white/5 p-2.5 text-[11px] text-white/70">
              Authenticated workspace
            </div>
          )}
        </aside>
      )}
      <section className="min-w-0 flex-1">
        <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-[#f7f9fd] px-5 sm:px-8">
          {showRail && (
            <div className="flex items-center gap-2 lg:hidden">
              <button
                className="grid size-9 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100"
                type="button"
                aria-label={
                  mobileNavOpen ? "Close navigation" : "Open navigation"
                }
                aria-expanded={mobileNavOpen}
                onClick={() => setMobileNavOpen((open) => !open)}
              >
                <svg
                  aria-hidden="true"
                  className="size-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth="1.7"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d={
                      mobileNavOpen
                        ? "M6 6l12 12M18 6 6 18"
                        : "M4 7h16M4 12h16M4 17h16"
                    }
                  />
                </svg>
              </button>
              <Link
                className="font-[var(--font-hanken)] text-base font-medium tracking-[0.04em]"
                href="/dashboard/"
              >
                <span className="tracking-[0.01em]">NIU <span className="text-emerald-600">POS</span></span>
              </Link>
            </div>
          )}
          <span className="hidden text-sm text-slate-500 lg:block">
            Owner workspace
          </span>
          <div className="flex items-center gap-4 text-slate-500">
            <button
              className="grid size-9 place-items-center rounded-full hover:bg-slate-100"
              type="button"
              aria-label="Search"
            >
              <Icon name="search" />
            </button>
            <button
              className="relative grid size-9 place-items-center rounded-full hover:bg-slate-100"
              type="button"
              aria-label="Notifications"
            >
              <Icon name="bell" />
              <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-red-500" />
            </button>
            <span className="flex items-center gap-2 text-xs">
              <span className="size-2 rounded-full bg-emerald-500" />
              Connected
            </span>
          </div>
        </header>
        {showRail && mobileNavOpen && (
          <>
            <button
              className="fixed inset-0 z-40 bg-slate-950/25 lg:hidden"
              type="button"
              aria-label="Close navigation"
              onClick={() => setMobileNavOpen(false)}
            />
            <nav
              className="fixed inset-y-0 left-0 z-50 w-[min(18rem,86vw)] overflow-y-auto border-r border-slate-200 bg-white px-4 pb-6 pt-20 shadow-xl lg:hidden"
              aria-label="Mobile navigation"
            >
              {navigation.map((item) => (
                <Link
                  className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm ${item.path === activePath ? "bg-emerald-50 font-medium text-emerald-800" : "text-slate-600 hover:bg-slate-50"}`}
                  href={item.path}
                  key={item.label}
                  onClick={() => setMobileNavOpen(false)}
                >
                  <span className="grid size-5 place-items-center transition-transform duration-200 group-hover:-translate-y-px">
                    <Icon name={item.icon} />
                  </span>
                  {item.label}
                </Link>
              ))}
            </nav>
          </>
        )}
        {children}
      </section>
    </div>
  );
}
