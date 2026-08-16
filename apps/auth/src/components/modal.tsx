"use client";

import { useEffect, useRef } from "react";

export function Modal({ title, description, onClose, children }: { title: string; description?: string; onClose: () => void; children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKeyDown = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); };
    document.addEventListener("keydown", onKeyDown);
    ref.current?.focus();
    return () => { document.body.style.overflow = previous; document.removeEventListener("keydown", onKeyDown); };
  }, [onClose]);
  return <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/35 p-4 backdrop-blur-[2px]" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <div aria-modal="true" className="max-h-[min(720px,calc(100vh-2rem))] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl" ref={ref} role="dialog" tabIndex={-1}>
      <div className="mb-5 flex items-start justify-between gap-4"><div><h2 className="text-base font-medium tracking-tight text-slate-950">{title}</h2>{description && <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>}</div><button aria-label="Close dialog" className="grid size-8 shrink-0 place-items-center rounded-lg text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" onClick={onClose} type="button">×</button></div>
      {children}
    </div>
  </div>;
}
