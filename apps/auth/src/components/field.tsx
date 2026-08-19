import type { InputHTMLAttributes } from "react";

export function Field({ label, ...props }: InputHTMLAttributes<HTMLInputElement> & { label: string }) {
  return <label className="block text-xs font-medium text-slate-700">{label}<input {...props} className="mt-1.5 h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50" /></label>;
}

export const primaryButton = "flex h-11 w-full items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition-[transform,box-shadow,background-color] duration-200 hover:-translate-y-px hover:bg-emerald-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0";
