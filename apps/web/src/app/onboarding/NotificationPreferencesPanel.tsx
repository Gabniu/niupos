export type NotificationPreferences = {
  inAppEnabled: boolean;
  emailEnabled: boolean;
  smsEnabled: boolean;
  pushEnabled: boolean;
  quietStart: string | null;
  quietEnd: string | null;
  externalDeliveryAvailable: boolean;
};

type Props = {
  preferences: NotificationPreferences;
  saving: boolean;
  onChange: (next: NotificationPreferences) => void;
  onSave: () => void;
};

export function NotificationPreferencesPanel({ preferences, saving, onChange, onSave }: Props) {
  const toggle = (key: keyof Pick<NotificationPreferences, "inAppEnabled" | "emailEnabled" | "smsEnabled" | "pushEnabled">) => {
    onChange({ ...preferences, [key]: !preferences[key] });
  };

  return (
    <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Notification preferences</p>
          <p className="mt-1 text-xs leading-5 text-slate-500">Choose where you want to receive setup updates. Email is available when connected; SMS and push are still being prepared.</p>
        </div>
        <button type="button" onClick={onSave} disabled={saving} className="inline-flex h-9 items-center rounded-lg bg-black px-3 text-xs font-medium text-white disabled:opacity-60">{saving ? "Saving…" : "Save"}</button>
      </div>
      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {(["inAppEnabled", "emailEnabled", "smsEnabled", "pushEnabled"] as const).map((key) => (
          <label key={key} className="flex items-center gap-2 text-xs text-slate-700">
            <input type="checkbox" checked={preferences[key]} onChange={() => toggle(key)} className="size-4 rounded border-slate-300 accent-black" />
            {key === "inAppEnabled" ? "In-app" : key === "emailEnabled" ? (preferences.externalDeliveryAvailable ? "Email" : "Email (waiting for connection)") : key === "smsEnabled" ? "SMS (being prepared)" : "Push (being prepared)"}
          </label>
        ))}
      </div>
    </div>
  );
}
