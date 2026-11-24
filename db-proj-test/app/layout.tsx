import type { ReactNode } from "react";

export const metadata = { title: "DB2026 테이블 뷰어" };

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="ko">
      <body style={{ fontFamily: "system-ui, sans-serif", background: "#f4f5f7", margin: 0, color: "#1f2430" }}>
        {children}
      </body>
    </html>
  );
}
