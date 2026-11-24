import fs from "node:fs";
import path from "node:path";

export interface Config {
  section: string;
  team: string;
  password: string;
}

export function loadConfig(): Config {
  const file = path.join(process.cwd(), "config.json");
  const raw = JSON.parse(fs.readFileSync(file, "utf8"));
  const section = String(raw.section ?? "").trim();
  const team = String(raw.team ?? "").trim();
  const password = String(raw.password ?? "");
  if (!/^\d+$/.test(section) || !/^\d+$/.test(team)) {
    throw new Error("config.json의 section, team은 숫자여야 합니다.");
  }
  if (!password) throw new Error("config.json에 password를 입력하세요.");
  return { section, team, password };
}

export function derive(c: Config) {
  const user = `db2026_${c.section}_${c.team}`;
  return {
    user,
    // 실습 웹서버(Apache+PHP). 팀 홈 폴더가 곧 웹 폴더.
    apiUrl: `http://203.249.87.58/db2026_${c.section}/${user}/api.php`,
    sshHost: `${user}@203.249.87.58`,
  };
}
