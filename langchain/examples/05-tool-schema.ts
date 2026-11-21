import { type BaseMessage, HumanMessage, SystemMessage } from "@langchain/core/messages";
import { createToolModel, messageText } from "../src/models.js";
import { executeOrderCalls, lookupOrderTool, OrderInputSchema } from "../src/tools.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  console.log("本地直接调用:", await lookupOrderTool.invoke({ orderId: "demo-001" }));
  console.log("错误参数被拒绝:", !OrderInputSchema.safeParse({ orderId: 1 }).success);
  const model = createToolModel(live, { toolCalls: [[
    { name: "lookup_order", args: { orderId: "demo-001" }, id: "call-order-1" },
  ], []] });
  if (!model.bindTools) throw new Error("该模型集成未提供 bindTools，不能执行本工具调用练习。");
  const bound = model.bindTools([lookupOrderTool]);
  const messages: BaseMessage[] = [
    new SystemMessage("仅使用只读订单工具查询匿名练习数据；找不到时明确说明。"),
    new HumanMessage("查询 demo-001 的配送状态。"),
  ];
  for (let turn = 0; turn < 3; turn++) {
    const response = await bound.invoke(messages, { signal: AbortSignal.timeout(20_000) });
    messages.push(response);
    if (!response.tool_calls?.length) {
      console.log("最终消息:", messageText(response.content));
      return;
    }
    console.log("模型请求工具（离线模式为预设脚本）:", response.tool_calls);
    const results = await executeOrderCalls(response);
    for (const result of results) console.log("ToolMessage:", result.tool_call_id, messageText(result.content));
    messages.push(...results);
  }
  throw new Error("超过本练习的 3 次模型调用上限。");
});
