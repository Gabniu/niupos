import type { InputHTMLAttributes } from "react";

export function Field({ label, ...props }: InputHTMLAttributes<HTMLInputElement> & { label: string }) {
  return <label className="block text-sm font-medium text-slate-800">{label}<input {...props} className="mt-2 h-[4.35rem] w-full rounded-xl border border-slate-200 bg-white px-5 text-base text-slate-900 outline-none transition-[border-color,box-shadow] duration-200 placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 disabled:bg-slate-50" /></label>;
}

export const primaryButton = "flex h-[4.35rem] w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-base font-semibold text-white shadow-[0_12px_26px_rgba(37,99,235,0.18)] transition-[transform,box-shadow,background-color] duration-200 hover:-translate-y-px hover:bg-blue-700 hover:shadow-[0_16px_32px_rgba(37,99,235,0.24)] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0";
