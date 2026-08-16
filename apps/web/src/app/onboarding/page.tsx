"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { PosShell } from "@/components/PosShell";
import { apiError, apiFetch } from "@/lib/api";
import { channelForOnboardingStep } from "./channel-step";
import { SetupNotificationsPanel, type SetupNotification } from "./SetupNotificationsPanel";
import { NotificationPreferencesPanel, type NotificationPreferences } from "./NotificationPreferencesPanel";
import { ProvisioningCapabilitiesPanel, type ProvisioningCapability } from "./ProvisioningCapabilitiesPanel";
import { NotificationDeliveryPanel, type NotificationDelivery } from "./NotificationDeliveryPanel";

type Channel = "pos" | "web" | "mobile" | "web_mobile";
type Industry = "grocery" | "supermarket" | "bakery" | "restaurant" | "apparel" | "salon" | "wholesale";
type Draft = {
  id: string;
  channelSelection: Channel | null;
  industryProfile: Industry | null;
  answers: Record<string, unknown>;
  revision: number;
  status: string;
  nextStep: string;
  plan: { automated: string[]; ownerApprovals: string[] };
  tenantId: string | null;
  completedAt?: string | null;
  location?: { companyId: string | null; branchId: string | null; warehouseId: string | null; registerId: string | null };
};
type LocationSetup = {
  companyName: string;
  branchCode: string;
  branchName: string;
  warehouseCode: string;
  warehouseName: string;
  registerCode: string;
  registerName: string;
};
type ChannelRegistration = {
  id: string;
  channel: "web" | "mobile";
  environment: string;
  displayName: string;
  clientId: string;
  status: string;
  configuration: Record<string, unknown>;
  redirectUris: string[];
  secretAvailable: boolean;
};
type ProvisioningRun = {
  id: string;
  status: string;
  dryRun: boolean;
  approvalRequired: boolean;
  correlationId: string;
  plan: { automated?: string[]; ownerApprovals?: string[] };
  actions: Array<{ code: string; status: string; requiresApproval: boolean; details?: { reason?: string } }>;
};
type TimelineEvent = { id: string; type: string; status: string; message: string; occurredAt: string | null };

const channels: Array<{ value: Channel; label: string; detail: string; icon: string }> = [
  { value: "pos", label: "POS only", detail: "Run sales, stock, registers, and shifts in-store.", icon: "▦" },
  { value: "web", label: "Web ecommerce", detail: "Add a customer storefront alongside your POS.", icon: "⌁" },
  { value: "mobile", label: "Mobile ecommerce", detail: "Prepare a customer mobile experience and release plan.", icon: "⌁" },
  { value: "web_mobile", label: "Web and mobile", detail: "Use one catalogue across both customer channels.", icon: "◈" },
];

const industries: Array<{ value: Industry; label: string; detail: string }> = [
  { value: "grocery", label: "Grocery or convenience", detail: "Barcodes, weighted items, replenishment, and pickup." },
  { value: "bakery", label: "Bakery or cake shop", detail: "Pre-orders, pickup dates, and custom order notes." },
  { value: "supermarket", label: "Supermarket", detail: "Branches, warehouses, transfers, and approvals." },
  { value: "restaurant", label: "Restaurant or cafe", detail: "Menus, modifiers, tables, and kitchen routing." },
  { value: "apparel", label: "Apparel", detail: "Variants, sizes, colours, transfers, and returns." },
  { value: "salon", label: "Salon or services", detail: "Appointments, staff calendars, and deposits." },
  { value: "wholesale", label: "Wholesale or B2B", detail: "Accounts, price lists, terms, and invoices." },
];

function ArrowIcon() {
  return <span aria-hidden="true" className="text-base leading-none">→</span>;
}

export default function OnboardingPage() {
  const [draft, setDraft] = useState<Draft | null>(null);
  const [channel, setChannel] = useState<Channel | null>(null);
  const [industry, setIndustry] = useState<Industry | null>(null);
  const [organizationName, setOrganizationName] = useState("");
  const [locationSetup, setLocationSetup] = useState<LocationSetup>({ companyName: "", branchCode: "", branchName: "", warehouseCode: "", warehouseName: "", registerCode: "", registerName: "" });
  const [channelRegistration, setChannelRegistration] = useState<ChannelRegistration | null>(null);
  const [channelDisplayName, setChannelDisplayName] = useState("");
  const [channelEnvironment, setChannelEnvironment] = useState("production");
  const [redirectUris, setRedirectUris] = useState("");
  const [channelSaving, setChannelSaving] = useState(false);
  const [channelIdempotencyKey, setChannelIdempotencyKey] = useState<string | null>(null);
  const [provisioningRun, setProvisioningRun] = useState<ProvisioningRun | null>(null);
  const [provisioningSaving, setProvisioningSaving] = useState(false);
  const [timeline, setTimeline] = useState<TimelineEvent[]>([]);
  const [setupNotifications, setSetupNotifications] = useState<SetupNotification[]>([]);
  const [notificationPreferences, setNotificationPreferences] = useState<NotificationPreferences | null>(null);
  const [notificationPreferencesSaving, setNotificationPreferencesSaving] = useState(false);
  const [provisioningCapabilities, setProvisioningCapabilities] = useState<ProvisioningCapability[]>([]);
  const [notificationDeliveries, setNotificationDeliveries] = useState<NotificationDelivery[]>([]);
  const [sendingDeliveryId, setSendingDeliveryId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [completing, setCompleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  async function loadChannelRegistration(onboardingDraft: Draft): Promise<void> {
    if (!onboardingDraft.tenantId || (onboardingDraft.nextStep !== "web_storefront" && onboardingDraft.nextStep !== "mobile_app")) {
      setChannelRegistration(null);
      return;
    }
    const registrationsResponse = await apiFetch("/api/v1/channels/registrations");
    if (!registrationsResponse.ok) return;
    const registrationsBody = (await registrationsResponse.json()) as { data?: ChannelRegistration[] };
    const requestedChannel = onboardingDraft.nextStep === "mobile_app" || onboardingDraft.channelSelection === "mobile" ? "mobile" : "web";
    const existing = registrationsBody.data?.find((registration) => registration.channel === requestedChannel && registration.environment === "production");
    setChannelRegistration(existing ?? null);
    setChannelDisplayName(existing?.displayName ?? "");
    setChannelEnvironment(existing?.environment ?? "production");
    setRedirectUris(existing?.redirectUris.join("\n") ?? "");
  }

  async function loadTimeline(): Promise<void> {
    const response = await apiFetch("/api/v1/onboarding/setup-timeline");
    if (!response.ok) return;
    const body = (await response.json()) as { data?: TimelineEvent[] };
    setTimeline(body.data ?? []);
  }

  async function loadSetupNotifications(): Promise<void> {
    const response = await apiFetch("/api/v1/onboarding/setup-notifications");
    if (!response.ok) return;
    const body = (await response.json()) as { data?: SetupNotification[] };
    setSetupNotifications(body.data ?? []);
  }

  async function markSetupNotificationRead(notificationId: string): Promise<void> {
    const response = await apiFetch(`/api/v1/onboarding/setup-notifications/${notificationId}/read`, { method: "POST" });
    if (response.ok) await loadSetupNotifications();
  }

  async function loadNotificationPreferences(): Promise<void> {
    const response = await apiFetch("/api/v1/onboarding/notification-preferences");
    if (!response.ok) return;
    const body = (await response.json()) as { data?: NotificationPreferences };
    if (body.data) setNotificationPreferences(body.data);
  }

  async function saveNotificationPreferences(): Promise<void> {
    if (!notificationPreferences) return;
    setNotificationPreferencesSaving(true);
    try {
      const response = await apiFetch("/api/v1/onboarding/notification-preferences", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify(notificationPreferences) });
      if (!response.ok) throw await apiError(response, "Notification preferences could not be saved.");
      const body = (await response.json()) as { data?: NotificationPreferences };
      if (body.data) setNotificationPreferences(body.data);
      setNotice(body.data?.externalDeliveryAvailable ? "Notification preferences saved. Email is ready when you choose to send updates." : "Notification preferences saved. Email, SMS, and push are waiting for their connections.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "Notification preferences could not be saved.");
    } finally {
      setNotificationPreferencesSaving(false);
    }
  }

  async function loadProvisioningCapabilities(): Promise<void> {
    const response = await apiFetch("/api/v1/onboarding/provisioning-capabilities");
    if (!response.ok) return;
    const body = (await response.json()) as { data?: ProvisioningCapability[] };
    setProvisioningCapabilities(body.data ?? []);
  }

  async function loadNotificationDeliveries(): Promise<void> {
    const response = await apiFetch("/api/v1/onboarding/notification-deliveries");
    if (!response.ok) return;
    const body = (await response.json()) as { data?: NotificationDelivery[] };
    setNotificationDeliveries(body.data ?? []);
  }

  async function sendNotificationDelivery(deliveryId: string): Promise<void> {
    setSendingDeliveryId(deliveryId);
    try {
      const response = await apiFetch(`/api/v1/onboarding/notification-deliveries/${deliveryId}/send`, { method: "POST" });
      if (!response.ok) throw await apiError(response, "The message could not be sent.");
      const body = (await response.json()) as { data?: { status?: string; message?: string } };
      setNotice(body.data?.message ?? "The message was sent.");
      await loadNotificationDeliveries();
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The message could not be sent.");
    } finally {
      setSendingDeliveryId(null);
    }
  }

  useEffect(() => {
    apiFetch("/api/v1/onboarding/draft")
      .then(async (response) => {
        if (!response.ok) throw await apiError(response, "Setup could not be loaded.");
        const body = (await response.json()) as { data?: Draft | null };
        if (body.data) {
          const onboardingDraft = body.data;
          setDraft(onboardingDraft);
          setChannel(onboardingDraft.channelSelection);
          setIndustry(onboardingDraft.industryProfile);
          setOrganizationName(typeof onboardingDraft.answers.organizationName === "string" ? onboardingDraft.answers.organizationName : "");
          setLocationSetup((current) => ({ ...current, ...Object.fromEntries(Object.keys(current).map((key) => [key, typeof onboardingDraft.answers[key] === "string" ? onboardingDraft.answers[key] : current[key as keyof LocationSetup]])) }) as LocationSetup);
          if (onboardingDraft.tenantId) window.localStorage.setItem("nova.tenant_id", onboardingDraft.tenantId);
          if (onboardingDraft.tenantId) {
            loadChannelRegistration(onboardingDraft).catch(() => undefined);
            loadTimeline().catch(() => undefined);
            loadSetupNotifications().catch(() => undefined);
            loadNotificationPreferences().catch(() => undefined);
            loadProvisioningCapabilities().catch(() => undefined);
            loadNotificationDeliveries().catch(() => undefined);
          }
        }
      })
      .catch((cause: unknown) => setError(cause instanceof Error ? cause.message : "Setup could not be loaded."))
      .finally(() => setLoading(false));
  }, []);

  const step = draft?.nextStep ?? "channel";
  const stepNumber = step === "channel" ? 1 : step === "industry" ? 2 : step === "organization" ? 3 : 4;
  const selectedChannel = useMemo(() => channels.find((item) => item.value === channel), [channel]);

  async function saveStep(): Promise<void> {
    setError(null);
    setNotice(null);
    if (step === "channel" && channel === null) {
      setError("Choose how you want to operate first.");
      return;
    }
    if (step === "industry" && industry === null) {
      setError("Choose the profile that best fits your business.");
      return;
    }
    if (step === "organization" && organizationName.trim() === "") {
      setError("Enter your organization name to continue.");
      return;
    }
    if (step === "pos_locations" && Object.values(locationSetup).some((value) => value.trim() === "")) {
      setError("Complete the first company, branch, warehouse, and register details before saving.");
      return;
    }

    setSaving(true);
    try {
      const key = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      const response = await apiFetch("/api/v1/onboarding/draft", {
        method: "PUT",
        headers: { "Content-Type": "application/json", "Idempotency-Key": key },
        body: JSON.stringify({
          channelSelection: channel,
          industryProfile: industry,
          answers: step === "pos_locations" ? locationSetup : organizationName.trim() === "" ? undefined : { organizationName: organizationName.trim() },
          currentStep: step,
          revision: draft?.revision ?? 0,
        }),
      });
      if (!response.ok) throw await apiError(response, "Your setup could not be saved.");
      const body = (await response.json()) as { data: Draft };
      setDraft(body.data);
      if (body.data.tenantId) window.localStorage.setItem("nova.tenant_id", body.data.tenantId);
      setChannel(body.data.channelSelection);
      setIndustry(body.data.industryProfile);
      setNotice("Saved. Your next step is ready.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "Your setup could not be saved.");
    } finally {
      setSaving(false);
    }
  }

  async function createChannelRegistration(): Promise<void> {
    if (!draft?.tenantId || (step !== "web_storefront" && step !== "mobile_app")) return;
    setError(null);
    setNotice(null);
    setChannelSaving(true);
    try {
      // The server derives the next branch from real registrations. For a
      // web_mobile tenant the second pass is mobile_app, so do not infer the
      // channel from the original combined selection or we would replay the
      // web registration and never advance.
      const channel = channelForOnboardingStep(step);
      if (!channel) return;
      const key = channelIdempotencyKey ?? (typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
      setChannelIdempotencyKey(key);
      const response = await apiFetch("/api/v1/channels/registrations", { method: "POST", headers: { "Content-Type": "application/json", "Idempotency-Key": key }, body: JSON.stringify({ channel, displayName: channelDisplayName.trim(), environment: channelEnvironment, redirectUris: redirectUris.split("\n").map((uri) => uri.trim()).filter(Boolean), configuration: {} }) });
      if (!response.ok) throw await apiError(response, "The channel registration could not be saved.");
      const body = (await response.json()) as { data: ChannelRegistration };
      setChannelRegistration(body.data);
      setChannelIdempotencyKey(null);
      setNotice("Channel draft saved. Review it before requesting approval.");
      const refreshedResponse = await apiFetch("/api/v1/onboarding/draft");
      if (refreshedResponse.ok) {
        const refreshedBody = (await refreshedResponse.json()) as { data?: Draft | null };
        if (refreshedBody.data) {
          setDraft(refreshedBody.data);
          await loadChannelRegistration(refreshedBody.data);
        }
      }
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The channel registration could not be saved.");
    } finally {
      setChannelSaving(false);
    }
  }

  async function requestChannelApproval(): Promise<void> {
    if (!channelRegistration) return;
    setError(null);
    setNotice(null);
    setChannelSaving(true);
    try {
      const response = await apiFetch(`/api/v1/channels/registrations/${channelRegistration.id}/request-approval`, { method: "POST" });
      if (!response.ok) throw await apiError(response, "The channel approval request could not be submitted.");
      const body = (await response.json()) as { data: ChannelRegistration };
      setChannelRegistration(body.data);
      setNotice("Approval requested. Publishing will wait until it is approved.");
      const refreshedResponse = await apiFetch("/api/v1/onboarding/draft");
      if (refreshedResponse.ok) {
        const refreshedBody = (await refreshedResponse.json()) as { data?: Draft | null };
        if (refreshedBody.data) {
          setDraft(refreshedBody.data);
          await loadChannelRegistration(refreshedBody.data);
        }
      }
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The channel approval request could not be submitted.");
    } finally {
      setChannelSaving(false);
    }
  }

  async function previewProvisioning(): Promise<void> {
    if (!draft?.tenantId) return;
    setError(null);
    setNotice(null);
    setProvisioningSaving(true);
    try {
      const key = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      const response = await apiFetch("/api/v1/onboarding/provisioning-runs/preview", { method: "POST", headers: { "Idempotency-Key": key } });
      if (!response.ok) throw await apiError(response, "The setup plan could not be prepared.");
      const body = (await response.json()) as { data: ProvisioningRun };
      setProvisioningRun(body.data);
      await loadTimeline();
      setNotice("Your setup plan is ready. Nothing has been published.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The setup plan could not be prepared.");
    } finally {
      setProvisioningSaving(false);
    }
  }

  async function approveProvisioning(): Promise<void> {
    if (!provisioningRun) return;
    setError(null);
    setNotice(null);
    setProvisioningSaving(true);
    try {
      const key = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      const response = await apiFetch(`/api/v1/onboarding/provisioning-runs/${provisioningRun.id}/approve`, { method: "POST", headers: { "Idempotency-Key": key } });
      if (!response.ok) throw await apiError(response, "The setup plan could not be approved.");
      const body = (await response.json()) as { data: ProvisioningRun };
      setProvisioningRun(body.data);
      const processed = await processProvisioningRequest(body.data.id);
      setNotice(processed.status === "completed" ? "The approved setup is complete." : "The plan is approved. External publication remains paused until its verified worker is configured.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The setup plan could not be approved.");
    } finally {
      setProvisioningSaving(false);
    }
  }

  async function processProvisioningRequest(runId: string): Promise<ProvisioningRun> {
    const response = await apiFetch(`/api/v1/onboarding/provisioning-runs/${runId}/process`, { method: "POST" });
    if (!response.ok) throw await apiError(response, "The setup actions could not be applied.");
    const body = (await response.json()) as { data: ProvisioningRun };
    setProvisioningRun(body.data);
    await loadTimeline();
    return body.data;
  }

  async function processProvisioning(): Promise<void> {
    if (!provisioningRun || provisioningRun.status !== "queued") return;
    setError(null);
    setNotice(null);
    setProvisioningSaving(true);
    try {
      const processed = await processProvisioningRequest(provisioningRun.id);
      setNotice(processed.status === "completed" ? "Setup is complete." : "The available setup is complete. Publishing is still waiting for its connection.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "The setup actions could not be applied.");
    } finally {
      setProvisioningSaving(false);
    }
  }

  async function completeLocations(): Promise<void> {
    if (!draft?.tenantId || draft.channelSelection !== "pos") return;
    if (Object.values(locationSetup).some((value) => value.trim() === "")) {
      setError("Complete the first company, branch, warehouse, and register details before continuing.");
      return;
    }
    setError(null);
    setNotice(null);
    setCompleting(true);
    try {
      const key = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      const response = await apiFetch("/api/v1/onboarding/pos-locations", { method: "POST", headers: { "Content-Type": "application/json", "Idempotency-Key": key }, body: JSON.stringify({ ...locationSetup, revision: draft.revision }) });
      if (!response.ok) throw await apiError(response, "Your first location could not be created.");
      const body = (await response.json()) as { data: Draft };
      setDraft(body.data);
      setNotice("Your first location and register are ready.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "Your first location could not be created.");
    } finally {
      setCompleting(false);
    }
  }

  async function completeOrganization(): Promise<void> {
    if (!draft || !draft.channelSelection) return;
    setError(null);
    setNotice(null);
    setCompleting(true);
    try {
      const key = typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      const response = await apiFetch("/api/v1/onboarding/complete", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Idempotency-Key": key },
        body: JSON.stringify({ revision: draft.revision }),
      });
      if (!response.ok) throw await apiError(response, "Your workspace could not be created.");
      const body = (await response.json()) as { data: Draft };
      setDraft(body.data);
      setNotice("Your workspace is ready. Continue with the setup steps.");
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : "Your workspace could not be created.");
    } finally {
      setCompleting(false);
    }
  }

  return (
    <PosShell activePath="/onboarding/">
      <main className="min-h-[calc(100vh-4rem)] bg-[#f7f9fd] text-slate-900">
        <div className="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">
          <div className="mb-5 flex items-center justify-between gap-4">
            <div>
              <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-emerald-700">Workspace setup</p>
              <p className="mt-1 text-xs text-slate-500">Step {stepNumber} of 4 · saved to your account</p>
            </div>
            <Link className="text-xs font-medium text-slate-500 transition hover:text-slate-900" href="/select-store/">Exit setup</Link>
          </div>

          <section className="max-w-3xl">
            <h1 className="text-[1.55rem] font-normal tracking-[-0.025em] sm:text-[1.9rem]">Let&apos;s shape your workspace.</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">A few choices will tailor the setup to your business. You can change these decisions later.</p>
          </section>

          {loading && <div className="mt-7 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Loading your saved setup…</div>}
          {!loading && (
            <section className="mt-7 max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_8px_30px_rgba(15,23,42,.04)] sm:p-7">
              {step === "channel" && <>
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">First, your operating surface</p>
                <h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">What do you need to run your business?</h2>
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                  {channels.map((item) => <button key={item.value} type="button" onClick={() => setChannel(item.value)} className={`group rounded-xl border p-4 text-left transition duration-200 hover:-translate-y-px hover:shadow-sm ${channel === item.value ? "border-slate-900 bg-slate-50 ring-1 ring-slate-900" : "border-slate-200 bg-white hover:border-slate-300"}`}><span className={`grid size-9 place-items-center rounded-lg text-base ${channel === item.value ? "bg-black text-white" : "bg-slate-100 text-slate-600"}`}>{item.icon}</span><span className="mt-3 block text-sm font-medium text-slate-900">{item.label}</span><span className="mt-1 block text-xs leading-5 text-slate-500">{item.detail}</span></button>)}
                </div>
              </>}

              {step === "industry" && <>
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Tell us what fits</p>
                <h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">Which profile is closest to your business?</h2>
                <p className="mt-1 text-xs text-slate-500">This changes the questions and defaults, not your tenant boundaries.</p>
                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{industries.map((item) => <button key={item.value} type="button" onClick={() => setIndustry(item.value)} className={`rounded-xl border p-4 text-left transition duration-200 hover:-translate-y-px hover:shadow-sm ${industry === item.value ? "border-slate-900 bg-slate-50 ring-1 ring-slate-900" : "border-slate-200 hover:border-slate-300"}`}><span className="block text-sm font-medium text-slate-900">{item.label}</span><span className="mt-1 block text-xs leading-5 text-slate-500">{item.detail}</span></button>)}</div>
              </>}

              {step === "organization" && <>
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Name your workspace</p>
                <h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">What should your team call this organization?</h2>
                <label className="mt-5 block text-xs font-medium text-slate-700" htmlFor="organizationName">Organization name</label>
                <input id="organizationName" value={organizationName} onChange={(event) => setOrganizationName(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10" placeholder="Your real business name" maxLength={160} />
              </>}

              {step === "pos_locations" && draft?.tenantId && <>
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">First POS location</p>
                <h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">Where will your first register operate?</h2>
                <p className="mt-1 text-xs text-slate-500">These are real records used by your POS. You can add more locations later.</p>
                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                  {[['companyName', 'Company name'], ['branchCode', 'Branch code'], ['branchName', 'Branch name'], ['warehouseCode', 'Warehouse code'], ['warehouseName', 'Warehouse name'], ['registerCode', 'Register code'], ['registerName', 'Register name']].map(([key, label]) => <label className="block text-xs font-medium text-slate-700" htmlFor={key} key={key}>{label}<input id={key} value={locationSetup[key as keyof LocationSetup]} onChange={(event) => setLocationSetup((current) => ({ ...current, [key]: event.target.value }))} className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-normal text-slate-900 outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10" maxLength={160} /></label>)}
                </div>
              </>}

              {(step === "web_storefront" || step === "mobile_app") && draft?.tenantId && <div className="rounded-xl border border-slate-200 bg-slate-50 p-5"><p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">{step === "mobile_app" ? "Mobile channel" : "Web channel"}</p><h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">Prepare the customer channel</h2><p className="mt-1 text-xs leading-5 text-slate-500">This creates metadata only. Secrets, domains, signing, and publication stay server-side and require approval.</p><div className="mt-5 grid gap-4 sm:grid-cols-2"><label className="block text-xs font-medium text-slate-700">Display name<input value={channelDisplayName} onChange={(event) => setChannelDisplayName(event.target.value)} className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-normal text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10" maxLength={160} /></label><label className="block text-xs font-medium text-slate-700">Environment<select value={channelEnvironment} onChange={(event) => setChannelEnvironment(event.target.value)} className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-normal text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"><option value="development">Development</option><option value="staging">Staging</option><option value="production">Production</option></select></label><label className="block text-xs font-medium text-slate-700 sm:col-span-2">Redirect URIs <span className="font-normal text-slate-400">(one per line)</span><textarea value={redirectUris} onChange={(event) => setRedirectUris(event.target.value)} className="mt-2 min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-normal text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10" /></label></div>{channelRegistration && <div className="mt-4 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600"><div className="flex items-center justify-between gap-3"><span>Client ID</span><code className="truncate text-[11px] text-slate-900">{channelRegistration.clientId}</code></div><div className="mt-2 flex items-center justify-between gap-3"><span>Status</span><span className="font-medium text-slate-900">{channelRegistration.status.replaceAll("_", " ")}</span></div><p className="mt-2 text-[11px] text-slate-400">Secret available: no. Configure credentials through the server-side secret manager.</p></div>}<div className="mt-5 flex flex-wrap gap-2"><button type="button" onClick={createChannelRegistration} disabled={channelSaving || channelRegistration !== null} className="inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white disabled:opacity-60">{channelSaving ? "Saving…" : "Save channel draft"}</button>{channelRegistration?.status === "draft" && <button type="button" onClick={requestChannelApproval} disabled={channelSaving} className="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-medium text-slate-700 disabled:opacity-60">{channelSaving ? "Submitting…" : "Request approval"}</button>}</div></div>}

              {step === "ready" && draft?.tenantId && <><div className="rounded-xl border border-slate-200 bg-slate-50 p-5"><p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Setup plan</p><h2 className="mt-2 text-lg font-medium tracking-[-0.015em]">Review what NIU will prepare</h2><p className="mt-1 text-xs leading-5 text-slate-500">This is a dry-run preview. It does not publish a domain, send credentials, sign an app, or create sample business data.</p>{provisioningRun ? <><div className="mt-4 grid gap-2 sm:grid-cols-2">{provisioningRun.actions.map((action) => <div key={action.code} className="rounded-lg border border-slate-200 bg-white p-3"><div className="flex items-center justify-between gap-3"><span className="text-xs font-medium text-slate-800">{action.code.replaceAll(".", " · ")}</span><span className="text-[11px] text-slate-500">{action.status.replaceAll("_", " ")}</span></div><p className="mt-1 text-[11px] leading-4 text-slate-500">{action.details?.reason}</p></div>)}</div><div className="mt-4 flex flex-wrap items-center gap-2"><span className="text-[11px] text-slate-500">Run {provisioningRun.status.replaceAll("_", " ")}</span>{provisioningRun.status === "needs_action" && <button type="button" onClick={approveProvisioning} disabled={provisioningSaving} className="inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white disabled:opacity-60">{provisioningSaving ? "Approving…" : "Approve plan"}</button>}{provisioningRun.status === "queued" && <button type="button" onClick={processProvisioning} disabled={provisioningSaving} className="inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white disabled:opacity-60">{provisioningSaving ? "Preparing…" : "Run safe setup"}</button>}{provisioningRun.status === "completed" && <span className="text-xs font-medium text-emerald-700">Setup complete</span>}</div></> : <button type="button" onClick={previewProvisioning} disabled={provisioningSaving} className="mt-5 inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white disabled:opacity-60">{provisioningSaving ? "Preparing…" : "Review setup plan"}</button>}</div>{timeline.length > 0 && <div className="mt-4 rounded-xl border border-slate-200 bg-white p-5"><p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Setup timeline</p><div className="mt-3 space-y-3">{timeline.map((event) => <div key={event.id} className="flex gap-3"><span className="mt-1 size-2 shrink-0 rounded-full bg-slate-900" /><div><p className="text-xs font-medium text-slate-800">{event.message}</p><p className="mt-0.5 text-[11px] text-slate-500">{event.status.replaceAll("_", " ")} · {event.occurredAt ? new Date(event.occurredAt).toLocaleString() : "pending"}</p></div></div>)}</div></div>}</>}

              {step !== "channel" && step !== "industry" && step !== "organization" && step !== "pos_locations" && step !== "web_storefront" && step !== "mobile_app" && <div className="rounded-xl border border-slate-200 bg-slate-50 p-5"><p className="text-sm font-medium text-slate-900">Next: {selectedChannel?.label ?? "your workspace"} setup</p><p className="mt-1 text-xs leading-5 text-slate-500">Your choices are saved. The next channel step will be added without creating sample records.</p>{draft?.status === "ready" && <Link className="mt-4 inline-flex h-10 items-center rounded-lg bg-black px-4 text-xs font-medium text-white transition hover:bg-slate-800" href="/select-store/">Choose workspace <ArrowIcon /></Link>}</div>}

              {draft?.tenantId && <SetupNotificationsPanel notifications={setupNotifications} onMarkRead={(notificationId) => markSetupNotificationRead(notificationId).catch(() => undefined)} />}
              {draft?.tenantId && notificationPreferences && <NotificationPreferencesPanel preferences={notificationPreferences} saving={notificationPreferencesSaving} onChange={setNotificationPreferences} onSave={() => saveNotificationPreferences().catch(() => undefined)} />}
              {draft?.tenantId && <ProvisioningCapabilitiesPanel capabilities={provisioningCapabilities} />}
              {draft?.tenantId && <NotificationDeliveryPanel deliveries={notificationDeliveries} onSend={(deliveryId) => sendNotificationDelivery(deliveryId).catch(() => undefined)} sendingId={sendingDeliveryId} />}
              {error && <p className="mt-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700" role="alert">{error}</p>}
              {notice && <p className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-800" role="status">{notice}</p>}
              <div className="mt-6 flex items-center justify-between gap-3 border-t border-slate-100 pt-5"><span className="text-[11px] text-slate-400">You can safely resume this setup later.</span>{step === "pos_locations" && draft?.tenantId && draft.status !== "ready" ? <div className="flex items-center gap-2"><button type="button" onClick={saveStep} disabled={saving} className="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-medium text-slate-700 transition hover:border-slate-400 disabled:opacity-60">{saving ? "Saving…" : "Save for later"}</button><button type="button" onClick={completeLocations} disabled={completing} className="inline-flex h-10 items-center gap-2 rounded-lg bg-black px-4 text-xs font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60">{completing ? "Creating…" : "Create first location"} <ArrowIcon /></button></div> : draft?.status === "ready" ? <span className="text-xs font-medium text-emerald-700">POS setup ready</span> : draft?.tenantId ? <span className="text-xs font-medium text-emerald-700">Organization created</span> : step !== "channel" && step !== "industry" && step !== "organization" && draft?.channelSelection ? <button type="button" onClick={completeOrganization} disabled={completing} className="inline-flex h-10 items-center gap-2 rounded-lg bg-black px-4 text-xs font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60">{completing ? "Creating workspace…" : "Create workspace"} <ArrowIcon /></button> : <button type="button" onClick={saveStep} disabled={saving} className="inline-flex h-10 items-center gap-2 rounded-lg bg-black px-4 text-xs font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60">{saving ? "Saving…" : "Continue"} <ArrowIcon /></button>}</div>
            </section>
          )}
        </div>
      </main>
    </PosShell>
  );
}
