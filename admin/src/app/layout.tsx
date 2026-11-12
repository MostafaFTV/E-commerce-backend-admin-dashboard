import type { Metadata } from "next";
import "./globals.css";


export const metadata: Metadata = {
  title: "پنل مدیریت کیچن بوتیک",
  description:
    "پنل مدیریت کیچن بوتیک",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="fa"
      dir="rtl"

      suppressHydrationWarning
    >
      <body>
        {children}
      </body>
    </html>
  );
}
