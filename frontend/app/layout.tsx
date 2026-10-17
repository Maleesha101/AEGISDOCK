import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AegisDock | Port Operations",
  description: "Local port operations security training environment",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
