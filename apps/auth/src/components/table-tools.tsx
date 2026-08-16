"use client";

export function SearchBox({ value, onChange, placeholder }: { value: string; onChange: (value: string) => void; placeholder: string }) {
  return <label className="relative block w-full sm:w-64"><span className="sr-only">Search</span><svg aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="m21 21-4.3-4.3m2.3-5.2a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg><input className="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-xs outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} /></label>;
}

export function Pagination({ page, pages, onChange }: { page: number; pages: number; onChange: (page: number) => void }) {
  if (pages <= 1) return null;
  return <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500"><span>Page {page} of {pages}</span><div className="flex gap-1"><button className="rounded-md border border-slate-200 px-2.5 py-1.5 disabled:opacity-40" disabled={page === 1} onClick={() => onChange(page - 1)} type="button">Previous</button><button className="rounded-md border border-slate-200 px-2.5 py-1.5 disabled:opacity-40" disabled={page === pages} onClick={() => onChange(page + 1)} type="button">Next</button></div></div>;
}
