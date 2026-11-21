import { createAgent, createMiddleware, modelCallLimitMiddleware, toolCallLimitMiddleware } from "langchain";
import { createToolModel } from "../src/models.js";
import { lookupOrderTool } from "../src/tools.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const events: string[] = [];
  const audit = createMiddleware({
    name: "lesson-audit",
    beforeModel: (state) => { events.push(`beforeModel: messages=${state.messages.length}`); },
    wrapModelCall: async (request, handler) => {
      events.push("wrapModelCall:before");
      const response = await handler(request);
      events.push("wrapModelCall:after");
      return response;
    },
    wrapToolCall: async (request, handler) => {
      events.push(`wrapToolCall:${request.toolCall.name}`);
      return handler(request);
    },
  });
  const agent = createAgent({
    model: createToolModel(live, { toolCalls: [[{ name: "lookup_order", args: { orderId: "demo-001" }, id: "middleware-order-1" }], []] }),
    tools: [lookupOrderTool],
    systemPrompt: "只查询匿名练习订单。",
    middleware: [
      audit,
      modelCallLimitMiddleware({ runLimit: 3, exitBehavior: "error" }),
      toolCallLimitMiddleware({ runLimit: 2, exitBehavior: "error" }),
    ],
  });
  await agent.invoke({ messages: [{ role: "user", content: "查询 demo-001。" }] }, { recursionLimit: 25, signal: AbortSignal.timeout(20_000) });
  console.log("只记录阶段和计数，不记录秘密/完整请求:", events);
  console.log("练习：把模型 runLimit 改成 1，观察下一次模型调用被拦截。" );
});
