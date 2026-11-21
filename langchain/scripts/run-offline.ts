import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";

if (process.argv.length > 2) throw new Error("批量离线验证不接受 --live、--trace、--remote 或其他参数。");
const root = fileURLToPath(new URL("../", import.meta.url));
const guard = new URL("./offline-guard.mjs", import.meta.url).href;
const examples = [
  "01-overview", "02-models", "03-langsmith", "04-prompt-pipeline", "05-tool-schema",
  "06-structured-output", "07-agent", "08-middleware", "09-memory", "10-rag-local",
  "11-langgraph", "12-mcp", "13-knowledge-assistant",
];
for (const name of examples) {
  console.log(`\n--- ${name} ---`);
  const result = spawnSync(process.execPath, ["--import", guard, "--import", "tsx", `examples/${name}.ts`], {
    cwd: root,
    stdio: "inherit",
    timeout: 30_000,
    env: {
      ...process.env,
      LANGSMITH_TRACING: "false", LANGSMITH_TRACING_V2: "false",
      LANGCHAIN_TRACING: "false", LANGCHAIN_TRACING_V2: "false",
    },
  });
  if (result.error || result.status !== 0) {
    console.error(`${name} 未通过：${result.error?.name ?? result.signal ?? result.status}`);
    process.exitCode = 1;
    break;
  }
}
if (!process.exitCode) console.log(`\n${examples.length} 个示例全部离线执行通过；不代表真实模型、语义检索、远程 MCP 或云端追踪已验证。`);
