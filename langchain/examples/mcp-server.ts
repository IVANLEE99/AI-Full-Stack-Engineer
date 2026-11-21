import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { buildLocalKnowledgeBase, retrieveEvidence, sourceOf } from "../src/rag.js";

// stdio 的 stdout 专用于 MCP 协议，不能打印课程日志或启动提示。
for (const key of ["LANGSMITH_TRACING", "LANGSMITH_TRACING_V2", "LANGCHAIN_TRACING", "LANGCHAIN_TRACING_V2"]) {
  process.env[key] = "false";
}
const base = await buildLocalKnowledgeBase();
const server = new McpServer({ name: "anonymous-knowledge-fixture", version: "1.0.0" });
server.registerTool("search", {
  description: "只读搜索匿名课程知识库，不接受路径，不执行系统命令。",
  inputSchema: { query: z.string().min(1).max(200) },
  annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
}, async ({ query }) => {
  const documents = await retrieveEvidence(base, query);
  return { content: [{ type: "text", text: JSON.stringify(documents.map((doc) => ({ source: sourceOf(doc), text: doc.pageContent }))) }] };
});
await server.connect(new StdioServerTransport());
