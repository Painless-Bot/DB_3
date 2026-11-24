import { NextRequest, NextResponse } from "next/server";
import { derive, loadConfig } from "@/lib/config";

export const dynamic = "force-dynamic";

// 브라우저 -> (여기) Next 서버 -> 실습 웹서버 PHP -> Oracle
// 비밀번호는 서버에서만 사용되고 브라우저로 나가지 않는다.
async function forward(payload: Record<string, unknown>) {
  try {
    const cfg = loadConfig();
    const { user, apiUrl } = derive(cfg);
    const res = await fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ...payload, user, password: cfg.password }),
      cache: "no-store",
      signal: AbortSignal.timeout(20000),
    });
    const text = await res.text();
    try {
      return NextResponse.json({ ...JSON.parse(text), user }, { status: res.status });
    } catch {
      return NextResponse.json(
        { error: `PHP 응답이 JSON이 아닙니다 (HTTP ${res.status}). api.php를 서버에 올렸는지 확인하세요.`, detail: text.slice(0, 300) },
        { status: 502 }
      );
    }
  } catch (e) {
    return NextResponse.json({ error: (e as Error).message }, { status: 500 });
  }
}

// GET ?action=tables | data&table=..&page=..&q=..
export async function GET(req: NextRequest) {
  const p = req.nextUrl.searchParams;
  const action = p.get("action") ?? "tables";
  if (action !== "tables" && action !== "data") {
    return NextResponse.json({ error: "잘못된 action" }, { status: 400 });
  }
  return forward({
    action,
    table: p.get("table") ?? "",
    page: Number(p.get("page") ?? 1),
    q: p.get("q") ?? "",
  });
}

// POST {sql} : 읽기 전용 SQL 실행 (검증은 PHP에서 수행)
export async function POST(req: NextRequest) {
  const body = await req.json().catch(() => ({}));
  return forward({ action: "query", sql: String(body.sql ?? "") });
}
