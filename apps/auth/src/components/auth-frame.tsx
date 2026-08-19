/** Shared photo-led shell for sign-in, recovery, and access-request flows. */
import Image from "next/image";
import Link from "next/link";

import { Brand } from "@/components/brand";

export function AuthFrame({
  eyebrow,
  title,
  description,
  children,
  footer,
}: {
  eyebrow?: string;
  title: string;
  description: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}) {
  return (
    <main className="grid min-h-screen bg-[#f6f8fb] text-slate-950 lg:grid-cols-[1.08fr_0.92fr]">
      <aside className="relative hidden min-h-screen overflow-hidden bg-[#08090c] text-white lg:flex lg:flex-col lg:px-[clamp(32px,6vw,96px)] lg:py-9">
        <Image alt="Customer service agent wearing a headset at a workstation" className="object-cover object-[center_38%]" fill priority src="/customer-service.png" />
        <div aria-hidden="true" className="absolute inset-0 bg-[linear-gradient(90deg,rgba(8,9,12,.72)_0%,rgba(8,9,12,.38)_48%,rgba(8,9,12,.08)_100%),linear-gradient(180deg,rgba(8,9,12,.18)_0%,rgba(8,9,12,.02)_48%,rgba(8,9,12,.34)_100%)]" />
        <div className="relative z-10 flex min-h-[calc(100vh-4.5rem)] flex-col">
          <div className="auth-fade auth-fade-1"><Brand inverse label="Niu Connect" /></div>
          <div className="my-auto max-w-[31rem] py-[14vh] auth-fade auth-fade-2">
            <h2 className="text-[clamp(2.5rem,4.1vw,4rem)] font-semibold leading-[1.02] tracking-[-0.055em]">
              Your customers, <span className="auth-script auth-typewriter">connected.</span>
            </h2>
            <p className="mt-7 max-w-[21rem] text-[15px] leading-6 text-white/75">
              Calls, conversations, and the people behind them — in one focused workspace.
            </p>
          </div>
          <div className="flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/60 auth-fade auth-fade-3">
            <span>Live calling</span><span>Agent-ready</span><span>Protected access</span>
          </div>
        </div>
      </aside>
      <section className="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-[clamp(32px,7vw,112px)]">
        <div className="w-full max-w-[38.5rem]">
          <div className="mb-10 flex items-center justify-between lg:hidden">
            <Brand label="Niu Connect" />
            <Link className="text-xs text-slate-500" href="/">Identity</Link>
          </div>
          <header className="mb-11">
            {eyebrow && <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-600">{eyebrow}</p>}
            <h1 className="mt-5 text-[2.35rem] font-medium leading-none tracking-[-0.055em]">{title}</h1>
            <p className="mt-5 max-w-[38rem] text-[1.05rem] leading-7 text-slate-500">{description}</p>
          </header>
          {children}
          {footer && <div className="mt-11 border-t border-slate-200 pt-8 text-center text-[0.95rem] text-slate-500">{footer}</div>}
        </div>
      </section>
    </main>
  );
}
