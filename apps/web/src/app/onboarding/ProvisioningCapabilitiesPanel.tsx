export type ProvisioningCapability = {
  code: string;
  executor: string | null;
  available: boolean;
  externalSideEffects: boolean;
};

type Props = { capabilities: ProvisioningCapability[] };

export function ProvisioningCapabilitiesPanel({ capabilities }: Props) {
  if (capabilities.length === 0) return null;

  return (
    <div className="mt-5 rounded-xl border border-slate-200 bg-white p-5">
      <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">What NIU can set up</p>
      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        {capabilities.map((capability) => (
          <div key={capability.code} className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
            <span className="text-[11px] text-slate-700">{capability.code.replaceAll(".", " · ")}</span>
            <span className={capability.available ? "text-[11px] font-medium text-emerald-700" : "text-[11px] font-medium text-amber-700"}>
              {capability.available ? "Ready" : capability.externalSideEffects ? "Waiting" : "Not available"}
            </span>
          </div>
        ))}
      </div>
      <p className="mt-3 text-[11px] leading-4 text-slate-400">Some items stay paused until the required connection is ready and tested.</p>
    </div>
  );
}
