import { describe, expect, it } from "vitest";
import { AIMessage, HumanMessage } from "@langchain/core/messages";
import { ChatPromptTemplate, MessagesPlaceholder } from "@langchain/core/prompts";
import { StringOutputParser } from "@langchain/core/output_parsers";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { executeOrderCalls, lookupOrderTool, OrderInputSchema } from "../src/tools.js";
import { AssistantAnswerSchema, TicketSchema, validateCitations } from "../src/structured.js";

describe("消息与 Runnable", () => {
  const prompt = ChatPromptTemplate.fromMessages([
    ["system", "使用 {language}"], new MessagesPlaceholder("history"), ["human", "{question}"],
  ]);
  it("模板按顺序保留角色与历史", async () => {
    const value = await prompt.invoke({ language: "中文", history: [new HumanMessage("旧问题"), new AIMessage("旧回答")], question: "新问题" });
    expect(value.toChatMessages().map((message) => message.type)).toEqual(["system", "human", "ai", "human"]);
    expect(value.toChatMessages().at(-1)?.content).toBe("新问题");
  });
  it("pipe 把 PromptValue 传给模型，再解析为字符串", async () => {
    const chain = prompt.pipe(new FakeListChatModel({ responses: ["固定输出"] })).pipe(new StringOutputParser());
    expect(await chain.invoke({ language: "中文", history: [], question: "练习" })).toBe("固定输出");
  });
  it("缺少模板变量会报错，而不是拼出 undefined", async () => {
    await expect(prompt.invoke({ history: [], question: "练习" })).rejects.toThrow();
  });
});

describe("工具输入与 ToolMessage", () => {
  it("拒绝类型错误、非法订单格式和额外参数", async () => {
    expect(OrderInputSchema.safeParse({ orderId: 1 }).success).toBe(false);
    expect(OrderInputSchema.safeParse({ orderId: "real-order" }).success).toBe(false);
    expect(OrderInputSchema.safeParse({ orderId: "demo-001", admin: true }).success).toBe(false);
    await expect(lookupOrderTool.invoke({ orderId: "invalid" })).rejects.toThrow();
  });
  it("找到匿名订单且不存在时返回 found:false", async () => {
    expect(JSON.parse(await lookupOrderTool.invoke({ orderId: "demo-001" }))).toMatchObject({ found: true, status: "已发货" });
    expect(JSON.parse(await lookupOrderTool.invoke({ orderId: "demo-999" }))).toMatchObject({ found: false });
  });
  it("手动闭环保留每个工具请求对应的 tool_call_id", async () => {
    const messages = await executeOrderCalls(new AIMessage({ content: "", tool_calls: [
      { name: "lookup_order", args: { orderId: "demo-001" }, id: "call-1", type: "tool_call" },
      { name: "lookup_order", args: { orderId: "demo-002" }, id: "call-2", type: "tool_call" },
    ] }));
    expect(messages.map((message) => message.tool_call_id)).toEqual(["call-1", "call-2"]);
    expect(JSON.parse(String(messages[1]?.content))).toMatchObject({ status: "待发货" });
  });
  it("未知工具与错误参数都回填可识别的工具结果", async () => {
    const results = await executeOrderCalls(new AIMessage({ content: "", tool_calls: [
      { name: "delete_everything", args: {}, id: "bad-name", type: "tool_call" },
      { name: "lookup_order", args: { orderId: 1 }, id: "bad-args", type: "tool_call" },
    ] }));
    expect(results.map((message) => JSON.parse(String(message.content)).code)).toEqual(["UNKNOWN_TOOL", "BAD_ARGS"]);
  });
  it("没有调用 ID 时拒绝伪造关联", async () => {
    await expect(executeOrderCalls(new AIMessage({ content: "", tool_calls: [{ name: "lookup_order", args: { orderId: "demo-001" }, type: "tool_call" }] }))).rejects.toThrow("id");
  });
});

describe("结构与证据是两层校验", () => {
  it("合法工单通过，空摘要/字符串布尔值被拒绝", () => {
    expect(TicketSchema.parse({ category: "refund", summary: "退货咨询", requiresHuman: true }).category).toBe("refund");
    expect(TicketSchema.safeParse({ category: "order", summary: "", requiresHuman: false }).success).toBe(false);
    expect(TicketSchema.safeParse({ category: "order", summary: "配送咨询", requiresHuman: "false" }).success).toBe(false);
  });
  it("FakeListChatModel 不代替客户端 Zod 校验", async () => {
    const fake = new FakeListChatModel({ responses: [JSON.stringify({ category: "invalid", summary: "", requiresHuman: "yes" })] });
    const result = await fake.withStructuredOutput(TicketSchema).invoke("练习");
    expect(TicketSchema.safeParse(result).success).toBe(false);
  });
  it("Schema 合法仍不能引用未检索到的资料", () => {
    const fixture = { answer: "回答", citations: ["fixtures/knowledge/returns.md"], needsReview: false };
    expect(AssistantAnswerSchema.safeParse(fixture).success).toBe(true);
    expect(() => validateCitations(fixture, new Set())).toThrow("来源");
    expect(validateCitations(fixture, new Set(fixture.citations))).toEqual(fixture);
  });
  it("无证据时必须转复核且不带引用", () => {
    expect(() => validateCitations({ answer: "猜测", citations: [], needsReview: false }, new Set())).toThrow("无检索证据");
    expect(validateCitations({ answer: "资料不足", citations: [], needsReview: true }, new Set()).needsReview).toBe(true);
  });
});
