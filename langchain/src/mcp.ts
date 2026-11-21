import { fileURLToPath } from "node:url";
import { MCPAdapter } from "@langchain/mcp-adapters";
import { requiredEnv, serviceUrl } from "./runtime.js";

export function buildMcpAdapter(remote = false): MCPAdapter {
  if (remote) {
    requiredEnv("MCP_SERVER_URL");
    const url = serviceUrl("MCP_SERVER_URL");
    if (!url) throw new Error("缺少 MCP_SERVER_URL。");
    return new MCPAdapter({ servers: { knowledge: {
      transport: "http", url, defaultToolTimeout: 5_000,
    } } });
  }
  // node 可执行文件沿用当前进程，而不是退回系统默认的 Node 14。
  // fixture server 使用 SDK 1.x 协议，显式选择适配器的 legacy 协议接口。
  return new MCPAdapter({ servers: { knowledge: {
    transport: "stdio",
    mode: "legacy",
    command: process.execPath,
    args: ["--import", "tsx", fileURLToPath(new URL("../examples/mcp-server.ts", import.meta.url))],
    stderr: "pipe",
    defaultToolTimeout: 5_000,
    restart: { enabled: false },
    env: { LANGSMITH_TRACING: "false", LANGSMITH_TRACING_V2: "false", LANGCHAIN_TRACING: "false", LANGCHAIN_TRACING_V2: "false" },
  } } });
}
