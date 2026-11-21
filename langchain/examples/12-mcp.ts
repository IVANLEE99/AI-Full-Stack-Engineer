import { createAgent } from "langchain";
import { ToolMessage } from "@langchain/core/messages";
import { mcpErrorBoundaryMiddleware } from "../src/mcp-safety.js";
import { buildMcpAdapter } from "../src/mcp.js";
import { createToolModel } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live, remote }) => {
  const adapter = buildMcpAdapter(remote);
  try {
    const tools = await adapter.listTools();
    console.log("发现的 MCP 工具:", tools.map((tool) => tool.name));
    // 即使 --remote，也不自动调用未知服务的工具；只接受预期的只读知识库契约。
    const search = tools.find((tool) => tool.name === "knowledge_search");
    if (!search) throw new Error("服务未提供预期的 knowledge_search 只读工具；不调用其他工具。");
    const agent = createAgent({
      model: createToolModel(live, { toolCalls: [[{ name: search.name, args: { query: "退货" }, id: "mcp-search-1" }], []] }),
      tools: [search],
      systemPrompt: "只读搜索匿名课程知识库，资料不足时不猜测。",
      middleware: [mcpErrorBoundaryMiddleware()],
    });
    const result = await agent.invoke({ messages: [{ role: "user", content: "查询示例退货规则。" }] }, { recursionLimit: 8, signal: AbortSignal.timeout(15_000) });
    const results = result.messages.filter(ToolMessage.isInstance);
    console.log("Agent 经 MCP 执行工具的结果:", results.map((message) => ({
      status: message.status,
      // 远程成功结果也可能含敏感资料；终端只展示状态，不直接打印全文。
      content: remote && message.status !== "error" ? "远程工具成功返回；正文未写入日志。" : message.content,
    })));
    console.log(remote ? "本次显式连接远程服务。" : "本地 stdio 子进程：无需 API key，finally 关闭连接。" );
  } finally {
    await adapter.close();
  }
});
