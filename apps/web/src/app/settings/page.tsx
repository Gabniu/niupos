"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PosShell } from "@/components/PosShell";
import { apiError, apiFetch } from "@/lib/api";

type Preferences = { sidePanelVisible: boolean; kioskMode: boolean };

export default function SettingsPage() {
  const [preferences, setPreferences] = useState<Preferences>({
    sidePanelVisible: true,
    kioskMode: false,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    queueMicrotask(() => {
      apiFetch("/api/v1/workspace/preferences")
        .then(async (response) => {
          if (!response.ok)
            throw await apiError(
              response,
              "Workspace settings could not be loaded.",
            );
          const body = (await response.json()) as { data?: Preferences };
          if (body.data) setPreferences(body.data);
        })
        .catch((cause: unknown) =>
          setError(
            cause instanceof Error
              ? cause.message
              : "Workspace settings could not be loaded.",
          ),
        )
        .finally(() => setLoading(false));
    });
  }, []);

  async function save(): Promise<void> {
    setSaving(true);
    setSaved(false);
    setError(null);
    try {
      const response = await apiFetch("/api/v1/workspace/preferences", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(preferences),
      });
      if (!response.ok)
        throw await apiError(
          response,
          "Workspace settings could not be saved.",
        );
      const body = (await response.json()) as { data?: Preferences };
      if (body.data) setPreferences(body.data);
      setSaved(true);
    } catch (cause: unknown) {
      setError(
        cause instanceof Error
          ? cause.message
          : "Workspace settings could not be saved.",
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <PosShell activePath="/settings/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] px-4 py-6 text-slate-900">
        <div className="mx-auto max-w-3xl">
          <header className="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-700">
                Workspace
              </p>
              <h1 className="mt-1 text-xl font-normal tracking-tight">
                Settings
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Control how this organisation presents the POS workspace.
              </p>
            </div>
            <Link
              className="text-sm text-slate-500 hover:text-slate-900"
              href="/dashboard/"
            >
              Back
            </Link>
          </header>
          {loading ? (
            <p className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
              Loading workspace settings…
            </p>
          ) : (
            <section className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_16px_rgba(15,23,42,.04)]">
              <div className="divide-y divide-slate-100">
                <label className="flex cursor-pointer items-start justify-between gap-6 p-5">
                  <span>
                    <span className="block text-sm font-medium text-slate-800">
                      Show side panel
                    </span>
                    <span className="mt-1 block max-w-xl text-xs leading-5 text-slate-500">
                      Keep the full navigation rail visible for operators moving
                      between dashboard, sales, products, and inventory.
                    </span>
                  </span>
                  <input
                    className="mt-1 size-5 shrink-0 accent-emerald-600"
                    type="checkbox"
                    checked={preferences.sidePanelVisible}
                    onChange={(event) =>
                      setPreferences((current) => ({
                        ...current,
                        sidePanelVisible: event.target.checked,
                      }))
                    }
                  />
                </label>
                <label className="flex cursor-pointer items-start justify-between gap-6 p-5">
                  <span>
                    <span className="block text-sm font-medium text-slate-800">
                      Kiosk mode
                    </span>
                    <span className="mt-1 block max-w-xl text-xs leading-5 text-slate-500">
                      Hide navigation chrome for a dedicated register device.
                      Browser or device lockdown must still be configured
                      separately by your administrator.
                    </span>
                  </span>
                  <input
                    className="mt-1 size-5 shrink-0 accent-emerald-600"
                    type="checkbox"
                    checked={preferences.kioskMode}
                    onChange={(event) =>
                      setPreferences((current) => ({
                        ...current,
                        kioskMode: event.target.checked,
                        sidePanelVisible: event.target.checked
                          ? false
                          : current.sidePanelVisible,
                      }))
                    }
                  />
                </label>
              </div>
              <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-4">
                <p className="text-xs text-slate-500">
                  Changes apply to every authenticated workspace view for this
                  organisation.
                </p>
                <button
                  className="h-11 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                  disabled={saving}
                  onClick={() => void save()}
                  type="button"
                >
                  {saving ? "Saving…" : "Save settings"}
                </button>
              </div>
            </section>
          )}
          {saved && (
            <p className="mt-3 text-xs text-emerald-700" role="status">
              Workspace settings saved.
            </p>
          )}
          {error && (
            <p
              className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"
              role="alert"
            >
              {error}
            </p>
          )}
        </div>
      </main>
    </PosShell>
  );
}
