import { AIMessage, ToolMessage } from "@langchain/core/messages";
import { tool } from "langchain";
import { z } from "zod";

export const OrderInputSchema = z.object({
  orderId: z.string().regex(/^demo-\d{3}$/, "只允许匿名练习订单，例如 demo-001"),
}).strict();

const orders: Record<string, { status: string; dispatchDays: number }> = {
  "demo-001": { status: "已发货", dispatchDays: 2 },
  "demo-002": { status: "待发货", dispatchDays: 0 },
};

/** 只读 fixture；生产订单必须由鉴权后的既有服务封装读取。 */
export const lookupOrderTool = tool(async ({ orderId }) => {
  const order = orders[orderId];
  return JSON.stringify(order
    ? { found: true, orderId, ...order }
    : { found: false, orderId, info: "匿名练习数据中没有该订单" });
}, {
  name: "lookup_order",
  description: "查询匿名练习订单的配送状态；只读，不查询个人信息，不修改订单。",
  schema: OrderInputSchema,
});

/** 手动执行绑定工具后的请求；bindTools 本身不会帮你运行函数。 */
export async function executeOrderCalls(message: AIMessage): Promise<ToolMessage[]> {
  const results: ToolMessage[] = [];
  for (const call of message.tool_calls ?? []) {
    if (!call.id) throw new Error("工具调用缺少 id，不能构造对应的 ToolMessage。");
    let content: string;
    if (call.name !== lookupOrderTool.name) {
      content = JSON.stringify({ code: "UNKNOWN_TOOL", info: "工具不在只读白名单内" });
    } else {
      const args = OrderInputSchema.safeParse(call.args);
      content = args.success
        ? await lookupOrderTool.invoke(args.data)
        : JSON.stringify({ code: "BAD_ARGS", info: "工具参数未通过 Schema 校验" });
    }
    results.push(new ToolMessage({ content, name: call.name, tool_call_id: call.id }));
  }
  return results;
}
