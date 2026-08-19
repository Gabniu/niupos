/** Compact ten-dot product mark shared by the auth and admin shells. */
export function Brand({ inverse = false, label = "NIU IDENTITY" }: { inverse?: boolean; label?: string }) {
  return (
    <span className={`inline-flex items-center gap-3 font-semibold tracking-[-0.02em] ${inverse ? "text-white" : "text-slate-950"}`}>
      <span aria-hidden="true" className={`grid size-10 shrink-0 grid-cols-3 auto-rows-[4px] place-content-center gap-1 rounded-full border ${inverse ? "border-white/70" : "border-slate-400/80"}`}>
        {Array.from({ length: 10 }, (_, index) => <i className={`size-1 rounded-full ${inverse ? "bg-white" : "bg-slate-700"} ${index === 9 ? "col-start-2" : ""}`} key={index} />)}
      </span>
      <span className="text-lg" style={{ fontFamily: "var(--font-hanken)" }}>{label}</span>
    </span>
  );
}
