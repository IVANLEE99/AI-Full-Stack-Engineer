import { readFile, readdir, stat } from "node:fs/promises";
import { dirname, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../", import.meta.url));
const manifest = JSON.parse(await readFile(new URL("../package.json", import.meta.url), "utf8")) as { scripts: Record<string, string> };
const files = ["README.md", ...Array.from({ length: 7 }, (_, index) => `day0${index + 1}.md`), ...(await readdir(resolve(root, "lessons"))).filter((name) => name.endsWith(".md")).map((name) => `lessons/${name}`)];
const failures: string[] = [];
let links = 0;
for (const file of files) {
  let source: string;
  try { source = await readFile(resolve(root, file), "utf8"); }
  catch { failures.push(`${file}: 文件不存在`); continue; }
  const fences = source.match(/^\s*```/gm) ?? [];
  if (fences.length % 2 !== 0) failures.push(`${file}: 代码围栏未成对关闭`);
  if (/\/Users\/[^\s]+|\/home\/[^\s]+|[A-Z]:\\Users\\/.test(source)) failures.push(`${file}: 包含本机绝对路径`);
  for (const match of source.matchAll(/\[[^\]\n]*\]\(([^)\n]+)\)/g)) {
    const target = match[1]?.split(/[?#]/, 1)[0];
    if (!target || /^(?:https?:|mailto:|#)/.test(target)) continue;
    const path = resolve(dirname(resolve(root, file)), decodeURIComponent(target));
    try { await stat(path); links++; }
    catch { failures.push(`${file}: 不存在的本地链接 ${relative(root, path)}`); }
  }
  for (const match of source.matchAll(/npm run ([a-z][a-z\d:-]*)/g)) {
    const script = match[1];
    if (script && !manifest.scripts[script]) failures.push(`${file}: npm 脚本不存在 ${script}`);
  }
}
if (failures.length > 0) {
  console.error(failures.join("\n"));
  process.exitCode = 1;
} else {
  console.log(`文档检查通过：${files.length} 篇，${links} 个本地链接；脚本名称、代码围栏和路径规则一致。`);
  console.log("范围：不访问外部链接，也不把 Markdown 中的说明片段当成供应商联调证据。");
}
