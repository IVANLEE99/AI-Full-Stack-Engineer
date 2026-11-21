import { createLessonModel } from "../src/models.js";
import { TicketSchema } from "../src/structured.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const fixture = { category: "order", summary: "查询匿名练习订单 demo-001 的配送状态", requiresHuman: false };
  const model = createLessonModel(live, [JSON.stringify(fixture)]);
  // functionCalling 是本示例选择的策略；兼容平台仍需实际支持工具调用。
  const runnable = model.withStructuredOutput(TicketSchema, { method: "functionCalling" });
  const parsed = await runnable.invoke("将“查询 demo-001 的配送状态”整理为分类、摘要、是否转人工。");
  // FakeListChatModel 只解析 JSON，不强制 Schema：独立校验不能省略。
  console.log("合法工单:", TicketSchema.parse(parsed));
  console.log("非法数据被拒绝:", !TicketSchema.safeParse({ category: "order", summary: "", requiresHuman: "false" }).success);
  console.log("结构合法不代表事实正确；不要把模拟输出当成供应商原生结构约束验证。" );
});
