import Link from "next/link";
import Image from "next/image";

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
    <main className="grid min-h-screen bg-[#f6f8fb] text-slate-950 lg:grid-cols-[0.8fr_1.2fr]">
      <aside className="hidden min-h-screen flex-col justify-between bg-[#064e3b] p-10 text-white lg:flex">
        <div className="flex items-center">
          <Brand inverse />
        </div>
        <div className="max-w-sm">
          <Image alt="Online security illustration" className="mb-7 h-auto w-52 opacity-90" height={220} priority src="/online-security.svg" width={280} />
          <h2 className="text-[1.65rem] font-normal leading-tight tracking-tight">
            Secure access across every application you operate.
          </h2>
          <p className="mt-4 text-sm leading-6 text-white/65">
            Centralize people, sessions, organizations, and application trust
            while each product keeps control of its own business permissions.
          </p>
        </div>
        <p className="text-xs text-white/55">NIU Auth</p>
      </aside>
      <section className="flex min-h-screen items-center justify-center px-4 py-8 sm:px-8">
        <div className="w-full max-w-sm">
          <div className="mb-10 flex items-center justify-between lg:hidden">
            <Brand />
            <Link className="text-xs text-slate-500" href="/">
              Identity
            </Link>
          </div>
          <header className="mb-7">
            {eyebrow && (
              <p className="text-[10px] font-medium uppercase tracking-[0.18em] text-emerald-700">
                {eyebrow}
              </p>
            )}
            <h1 className="mt-2 text-[1.55rem] font-normal tracking-[-0.025em]">
              {title}
            </h1>
            <p className="mt-2 text-sm leading-5 text-slate-500">
              {description}
            </p>
          </header>
          {children}
          {footer && (
            <div className="mt-6 border-t border-slate-200 pt-5 text-center text-xs text-slate-500">
              {footer}
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
