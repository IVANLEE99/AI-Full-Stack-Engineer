import { createAgent, modelCallLimitMiddleware } from "langchain";
import { createToolModel, messageText } from "../src/models.js";
import { lookupOrderTool } from "../src/tools.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const model = createToolModel(live, { toolCalls: [[
    { name: "lookup_order", args: { orderId: "demo-001" }, id: "agent-order-1" },
  ], []] });
  const agent = createAgent({
    model,
    tools: [lookupOrderTool],
    systemPrompt: "你是匿名订单助手。仅使用只读工具；不存在的订单不猜测，不修改业务数据。",
    middleware: [modelCallLimitMiddleware({ runLimit: 3, exitBehavior: "error" })],
  });
  const result = await agent.invoke({ messages: [{ role: "user", content: "查询 demo-001 的配送状态。" }] }, {
    recursionLimit: 10,
    signal: AbortSignal.timeout(20_000),
  });
  for (const message of result.messages) {
    console.log(message.type, messageText(message.content));
  }
  console.log("离线脚本只验证 Agent 自动编排工具循环，不验证模型能否正确决定调用工具。" );
});
