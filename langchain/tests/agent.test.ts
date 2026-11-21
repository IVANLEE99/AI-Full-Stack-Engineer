import { afterEach, describe, expect, it, vi } from "vitest";
import { ToolMessage } from "@langchain/core/messages";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { InMemoryStore, MemorySaver } from "@langchain/langgraph";
import { createAgent, createMiddleware, FakeToolCallingModel, modelCallLimitMiddleware, tool, toolCallLimitMiddleware, toolStrategy } from "langchain";
import { z } from "zod";
import { lookupOrderTool } from "../src/tools.js";
import { TicketSchema } from "../src/structured.js";

const request = { messages: [{ role: "user" as const, content: "查询 demo-001。" }] };
const script = () => new FakeToolCallingModel({ toolCalls: [[{ name: "lookup_order", args: { orderId: "demo-001" }, id: "agent-call-1" }], []] });
afterEach(() => vi.restoreAllMocks());

describe("Agent 工具循环和治理", () => {
  it("自动执行工具，保留对应 ID，并在无后续请求时终止", async () => {
    const invoke = vi.spyOn(lookupOrderTool, "invoke");
    const agent = createAgent({ model: script(), tools: [lookupOrderTool], systemPrompt: "匿名只读助手" });
    const result = await agent.invoke(request, { recursionLimit: 8 });
    expect(invoke).toHaveBeenCalledTimes(1);
    const toolMessages = result.messages.filter(ToolMessage.isInstance);
    expect(toolMessages).toHaveLength(1);
    expect(toolMessages[0]?.tool_call_id).toBe("agent-call-1");
    expect(JSON.parse(String(toolMessages[0]?.content))).toMatchObject({ found: true, orderId: "demo-001" });
    expect(result.messages.at(-1)?.type).toBe("ai");
  });
  it("中间件看到实际的模型与工具阶段，而不是只挂在配置中", async () => {
    const events: string[] = [];
    const observer = createMiddleware({ name: "observer", beforeModel: () => { events.push("before"); }, wrapModelCall: async (req, next) => {
      events.push("model:start"); const result = await next(req); events.push("model:end"); return result;
    }, wrapToolCall: async (req, next) => { events.push(`tool:${req.toolCall.name}`); return next(req); } });
    const agent = createAgent({ model: script(), tools: [lookupOrderTool], systemPrompt: "匿名只读助手", middleware: [observer] });
    await agent.invoke(request);
    expect(events).toEqual(["before", "model:start", "model:end", "tool:lookup_order", "before", "model:start", "model:end"]);
  });
  it("模型调用上限阻止第二次模型请求", async () => {
    let calls = 0;
    const observer = createMiddleware({ name: "count", wrapModelCall: (req, next) => { calls++; return next(req); } });
    const agent = createAgent({ model: script(), tools: [lookupOrderTool], systemPrompt: "匿名只读助手", middleware: [observer, modelCallLimitMiddleware({ runLimit: 1, exitBehavior: "error" })] });
    await expect(agent.invoke(request)).rejects.toThrow();
    expect(calls).toBe(1);
  });
  it("工具调用上限可以在业务函数执行前拒绝请求", async () => {
    const invoke = vi.spyOn(lookupOrderTool, "invoke");
    const agent = createAgent({ model: script(), tools: [lookupOrderTool], systemPrompt: "匿名只读助手", middleware: [toolCallLimitMiddleware({ runLimit: 0, exitBehavior: "error" })] });
    await expect(agent.invoke(request)).rejects.toThrow();
    expect(invoke).not.toHaveBeenCalled();
  });
  it("工具异常经 wrapper 转为对应的错误 ToolMessage", async () => {
    const failure = tool(async () => { throw new Error("fixture-only-error"); }, { name: "failing_tool", description: "离线失败测试", schema: z.object({}) });
    const guard = createMiddleware({ name: "safe-error", wrapToolCall: async (req, next) => {
      try { return await next(req); } catch {
        return new ToolMessage({ content: JSON.stringify({ code: "TOOL_FAILED" }), tool_call_id: req.toolCall.id ?? "", status: "error" });
      }
    } });
    const agent = createAgent({ model: new FakeToolCallingModel({ toolCalls: [[{ name: "failing_tool", args: {}, id: "failed-1" }], []] }), tools: [failure], systemPrompt: "匿名测试助手", middleware: [guard] });
    const result = await agent.invoke(request);
    const error = result.messages.find(ToolMessage.isInstance);
    expect(error?.tool_call_id).toBe("failed-1");
    expect(error?.status).toBe("error");
    expect(error?.content).toContain("TOOL_FAILED");
  });
  it("Agent 的结构化结果位于 structuredResponse，格式工具名不硬编码", async () => {
    const format = toolStrategy(TicketSchema, { handleError: false });
    const name = format[0]?.name;
    if (!name) throw new Error("missing fixture format");
    const ticket = { category: "order", summary: "配送咨询", requiresHuman: false };
    const agent = createAgent({ model: new FakeToolCallingModel({ toolCalls: [[{ name, args: ticket, id: "format-1" }]] }), tools: [], systemPrompt: "匿名测试助手", responseFormat: format });
    expect(TicketSchema.parse((await agent.invoke(request)).structuredResponse)).toEqual(ticket);
  });
  it("实验性 v3 投影流可以消费消息并等待最终输出", async () => {
    const agent = createAgent({ model: script(), tools: [lookupOrderTool], systemPrompt: "匿名只读助手" });
    const run = await agent.streamEvents(request, { version: "v3", recursionLimit: 8 });
    let messages = 0;
    for await (const message of run.messages) { messages++; for await (const _text of message.text) { /* 消费投影 */ } }
    const result = await run.output;
    expect(messages).toBeGreaterThan(0);
    expect(result.messages.some(ToolMessage.isInstance)).toBe(true);
  });
});

describe("状态记忆和命名空间", () => {
  it("同一线程保留消息，新线程不继承用户历史", async () => {
    const agent = createAgent({ model: new FakeListChatModel({ responses: ["a", "b", "c"] }), tools: [], checkpointer: new MemorySaver() });
    const a = { configurable: { thread_id: "test-thread-a" } };
    const b = { configurable: { thread_id: "test-thread-b" } };
    await agent.invoke({ messages: [{ role: "user", content: "线程 A 第一问" }] }, a);
    const continued = await agent.invoke({ messages: [{ role: "user", content: "线程 A 第二问" }] }, a);
    const isolated = await agent.invoke({ messages: [{ role: "user", content: "线程 B 第一问" }] }, b);
    expect(continued.messages.filter((m) => m.type === "human").map((m) => m.content)).toEqual(["线程 A 第一问", "线程 A 第二问"]);
    expect(isolated.messages.filter((m) => m.type === "human").map((m) => m.content)).toEqual(["线程 B 第一问"]);
  });
  it("同名 key 在不同用户 namespace 下互不影响", async () => {
    const store = new InMemoryStore();
    await store.put(["user-a", "preferences"], "language", { language: "zh-CN" });
    expect((await store.get(["user-a", "preferences"], "language"))?.value).toEqual({ language: "zh-CN" });
    expect(await store.get(["user-b", "preferences"], "language")).toBeNull();
  });
});
