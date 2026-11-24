"use client";
import { useCallback, useEffect, useRef, useState } from "react";

type Col = { name: string; type: string; nullable: boolean; pk: boolean; fk: string | null };
type TableInfo = { name: string; rows: number | null; columns: Col[] };
type Grid = { columns: string[]; rows: (string | null)[][] };
type DataRes = Grid & { total: number; page: number; size: number };
type QueryRes = Grid & { truncated: boolean; max: number };

const card: React.CSSProperties = { background: "#fff", border: "1px solid #dde0e6", borderRadius: 10, padding: 20, marginBottom: 16 };
const cell: React.CSSProperties = { padding: "6px 12px", borderBottom: "1px solid #e8eaee", textAlign: "left", whiteSpace: "nowrap" };
const btn: React.CSSProperties = { padding: "5px 12px", cursor: "pointer", border: "1px solid #c5cad3", borderRadius: 6, background: "#f4f6fb" };

async function api<T>(url: string, init?: RequestInit): Promise<T> {
  const r = await fetch(url, init);
  const j = await r.json();
  if (j.error) throw new Error(j.error + (j.detail ? `\n${j.detail}` : ""));
  return j as T;
}

function GridTable({ g }: { g: Grid }) {
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
        <thead>
          <tr>{g.columns.map((c) => <th key={c} style={{ ...cell, fontSize: 12, color: "#667" }}>{c}</th>)}</tr>
        </thead>
        <tbody>
          {g.rows.map((row, i) => (
            <tr key={i}>{row.map((v, j) => <td key={j} style={cell}>{v ?? <i style={{ color: "#aaa" }}>NULL</i>}</td>)}</tr>
          ))}
          {g.rows.length === 0 && <tr><td style={cell} colSpan={g.columns.length || 1}>결과가 없습니다.</td></tr>}
        </tbody>
      </table>
    </div>
  );
}

export default function Page() {
  const [user, setUser] = useState("");
  const [tab, setTab] = useState<"tables" | "sql">("tables");
  const [tables, setTables] = useState<TableInfo[]>([]);
  const [open, setOpen] = useState<string | null>(null); // 구조/데이터를 보고 있는 테이블
  const [data, setData] = useState<DataRes | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [sql, setSql] = useState("SELECT * FROM STUDENT");
  const [result, setResult] = useState<QueryRes | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [auto, setAuto] = useState(false);
  const [updated, setUpdated] = useState("");

  const loadTables = useCallback(async () => {
    const r = await api<{ tables: TableInfo[]; user: string }>("/api/tables?action=tables");
    setTables(r.tables);
    setUser(r.user);
  }, []);

  const loadData = useCallback(async (t: string, p: number, q: string) => {
    const r = await api<DataRes>(`/api/tables?action=data&table=${encodeURIComponent(t)}&page=${p}&q=${encodeURIComponent(q)}`);
    setData(r);
  }, []);

  const runSql = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setResult(await api<QueryRes>("/api/tables", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ sql }) }));
    } catch (e) {
      setResult(null);
      setError((e as Error).message);
    }
    setLoading(false);
  }, [sql]);

  // 현재 화면 새로고침 (silent: 로딩 표시 없이)
  const refresh = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      setError("");
      if (tab === "tables") {
        await loadTables();
        if (open) await loadData(open, page, search);
      }
      setUpdated(new Date().toLocaleTimeString("ko-KR"));
    } catch (e) {
      setError((e as Error).message);
    }
    setLoading(false);
  }, [tab, open, page, search, loadTables, loadData]);

  // 최신 refresh를 interval에서 쓰기 위한 ref
  const refreshRef = useRef(refresh);
  refreshRef.current = refresh;

  useEffect(() => { refresh(); }, [tab, open, page]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!auto || tab !== "tables") return;
    const id = setInterval(() => refreshRef.current(true), 5000);
    return () => clearInterval(id);
  }, [auto, tab]);

  const selected = tables.find((t) => t.name === open);
  const lastPage = data ? Math.max(1, Math.ceil(data.total / data.size)) : 1;

  return (
    <main style={{ maxWidth: 1000, margin: "0 auto", padding: 24 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 8 }}>
        <h1 style={{ margin: 0 }}>DB2026 모니터링</h1>
        <div style={{ fontSize: 14 }}>
          {user && <>계정 <code>{user}</code> · </>}
          {updated && <>갱신 {updated} · </>}
          <label style={{ cursor: "pointer" }}>
            <input type="checkbox" checked={auto} onChange={(e) => setAuto(e.target.checked)} /> 자동 새로고침(5초)
          </label>{" "}
          <button style={btn} onClick={() => refresh()}>새로고침</button>
        </div>
      </div>

      <div style={{ margin: "16px 0" }}>
        {(["tables", "sql"] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} style={{ ...btn, marginRight: 8, background: tab === t ? "#dbe5ff" : "#f4f6fb" }}>
            {t === "tables" ? "테이블" : "SQL 실행(읽기 전용)"}
          </button>
        ))}
      </div>

      {error && (
        <div style={{ ...card, borderColor: "#e5484d", color: "#b42318", whiteSpace: "pre-wrap" }}>
          <b>오류:</b> {error}
        </div>
      )}
      {loading && !tables.length && <p>불러오는 중…</p>}

      {tab === "tables" && (
        <>
          <div style={card}>
            <h3 style={{ marginTop: 0 }}>테이블 ({tables.length}개)</h3>
            <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
              <thead>
                <tr><th style={cell}>이름</th><th style={cell}>행 수</th><th style={cell}>컬럼 수</th><th style={cell}></th></tr>
              </thead>
              <tbody>
                {tables.map((t) => (
                  <tr key={t.name} style={{ background: open === t.name ? "#eef3ff" : undefined }}>
                    <td style={cell}><b>{t.name}</b></td>
                    <td style={cell}>{t.rows ?? "-"}</td>
                    <td style={cell}>{t.columns.length}</td>
                    <td style={cell}>
                      <button style={btn} onClick={() => { setOpen(t.name); setPage(1); setSearch(""); setData(null); }}>보기</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {selected && (
            <>
              <div style={card}>
                <h3 style={{ marginTop: 0 }}>{selected.name} 구조</h3>
                <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
                  <thead>
                    <tr>{["컬럼", "타입", "NULL", "키"].map((h) => <th key={h} style={{ ...cell, fontSize: 12, color: "#667" }}>{h}</th>)}</tr>
                  </thead>
                  <tbody>
                    {selected.columns.map((c) => (
                      <tr key={c.name}>
                        <td style={cell}>{c.name}</td>
                        <td style={cell}>{c.type}</td>
                        <td style={cell}>{c.nullable ? "Y" : "N"}</td>
                        <td style={cell}>{[c.pk && "PK", c.fk && `FK → ${c.fk}`].filter(Boolean).join(", ")}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div style={card}>
                <div style={{ display: "flex", gap: 8, marginBottom: 12, flexWrap: "wrap" }}>
                  <h3 style={{ margin: 0, flex: "0 0 auto" }}>{selected.name} 데이터</h3>
                  <form
                    style={{ display: "flex", gap: 6, marginLeft: "auto" }}
                    onSubmit={(e) => { e.preventDefault(); setPage(1); refresh(); }}
                  >
                    <input
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      placeholder="전체 컬럼 검색"
                      style={{ padding: "5px 10px", border: "1px solid #c5cad3", borderRadius: 6 }}
                    />
                    <button style={btn} type="submit">검색</button>
                  </form>
                </div>
                {data ? (
                  <>
                    <GridTable g={data} />
                    <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center", fontSize: 14 }}>
                      <button style={btn} disabled={page <= 1} onClick={() => setPage(page - 1)}>이전</button>
                      <span>{page} / {lastPage} 페이지 · 총 {data.total}행</span>
                      <button style={btn} disabled={page >= lastPage} onClick={() => setPage(page + 1)}>다음</button>
                    </div>
                  </>
                ) : <p>불러오는 중…</p>}
              </div>
            </>
          )}
        </>
      )}

      {tab === "sql" && (
        <div style={card}>
          <textarea
            value={sql}
            onChange={(e) => setSql(e.target.value)}
            onKeyDown={(e) => { if ((e.metaKey || e.ctrlKey) && e.key === "Enter") runSql(); }}
            rows={6}
            spellCheck={false}
            style={{ width: "100%", boxSizing: "border-box", fontFamily: "ui-monospace, monospace", fontSize: 14, padding: 10, border: "1px solid #c5cad3", borderRadius: 6 }}
          />
          <div style={{ margin: "8px 0 16px", display: "flex", gap: 12, alignItems: "center" }}>
            <button style={{ ...btn, background: "#2f5bd8", color: "#fff", borderColor: "#2f5bd8" }} onClick={runSql}>실행 (⌘/Ctrl+Enter)</button>
            <span style={{ fontSize: 13, color: "#667" }}>SELECT/WITH만 가능 · 최대 200행</span>
          </div>
          {result && (
            <>
              <GridTable g={result} />
              <p style={{ fontSize: 13, color: "#667" }}>
                {result.rows.length}행{result.truncated ? ` (최대 ${result.max}행까지만 표시)` : ""}
              </p>
            </>
          )}
        </div>
      )}
    </main>
  );
}
