import type { Metadata } from "next";
import { Hanken_Grotesk } from "next/font/google";

import "./globals.css";

const hanken = Hanken_Grotesk({ subsets: ["latin"], variable: "--font-hanken" });

export const metadata: Metadata = {
  title: "NIU Auth",
  description: "Shared identity and application access management",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en" className={hanken.variable}><body>{children}</body></html>;
}
