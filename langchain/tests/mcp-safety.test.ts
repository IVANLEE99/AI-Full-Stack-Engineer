import { afterEach, describe, expect, it, vi } from "vitest";
import { ToolMessage } from "@langchain/core/messages";
import { Command, GraphInterrupt } from "@langchain/langgraph";
import { createAgent, createMiddleware, FakeToolCallingModel, tool, type ToolCallRequest } from "langchain";
import { z } from "zod";
import { mcpErrorBoundaryMiddleware, SAFE_MCP_ERROR } from "../src/mcp-safety.js";

const privateFixture = "fixture-confidential-request-body";
const input = { messages: [{ role: "user" as const, content: "查询匿名退货规则。" }] };
const callId = "mcp-error-test-1";

function guardedAgent() {
  const search = tool(async () => "正常匿名结果", {
    name: "knowledge_search", description: "只读错误边界测试", schema: z.object({ query: z.string() }),
  });
  const modelInputs: string[] = [];
  const observer = createMiddleware({ name: "inspect-model-input", wrapModelCall: (request, handler) => {
    modelInputs.push(JSON.stringify(request.messages));
    return handler(request);
  } });
  const agent = createAgent({
    model: new FakeToolCallingModel({ toolCalls: [[{ name: search.name, args: { query: "退货" }, id: callId }], []] }),
    tools: [search], systemPrompt: "只读匿名测试助手",
    middleware: [mcpErrorBoundaryMiddleware(), observer],
  });
  return { search, modelInputs, agent };
}

function wrapperRequest(signal?: AbortSignal): ToolCallRequest {
  return {
    toolCall: { name: "knowledge_search", args: { query: "退货" }, id: callId },
    tool: undefined,
    state: { messages: [] },
    runtime: signal ? { signal } : {},
  };
}

const boundary = () => {
  const wrapper = mcpErrorBoundaryMiddleware().wrapToolCall;
  if (!wrapper) throw new Error("missing fixture wrapper");
  return wrapper;
};

afterEach(() => vi.restoreAllMocks());

describe("MCP 错误必须在下一轮模型调用之前脱敏", () => {
  it("抛出的工具异常不会进入返回状态或下一轮模型输入", async () => {
    const { search, modelInputs, agent } = guardedAgent();
    vi.spyOn(search, "invoke").mockRejectedValue(new Error(privateFixture));
    const result = await agent.invoke(input);
    const message = result.messages.find(ToolMessage.isInstance);
    expect(message?.status).toBe("error");
    expect(message?.tool_call_id).toBe(callId);
    expect(message?.content).toBe(SAFE_MCP_ERROR);
    expect(JSON.stringify(result)).not.toContain(privateFixture);
    expect(modelInputs).toHaveLength(2);
    expect(modelInputs.join("\n")).not.toContain(privateFixture);
    expect(modelInputs[1]).toContain("MCP_TOOL_FAILED");
  });

  it("适配器直接返回的 error ToolMessage 也去掉原文与 artifact", async () => {
    const { search, modelInputs, agent } = guardedAgent();
    vi.spyOn(search, "invoke").mockImplementation(async () => new ToolMessage({
      content: privateFixture, artifact: { originalError: privateFixture }, status: "error", tool_call_id: callId,
    }));
    const result = await agent.invoke(input);
    const message = result.messages.find(ToolMessage.isInstance);
    expect(message?.content).toBe(SAFE_MCP_ERROR);
    expect(message?.status).toBe("error");
    expect(message?.artifact).toBeUndefined();
    expect(JSON.stringify(result)).not.toContain(privateFixture);
    expect(modelInputs.join("\n")).not.toContain(privateFixture);
  });

  it.each([null, undefined])("空拒绝值 %s 被转换为安全工具结果", async (reason) => {
    const result = await boundary()(wrapperRequest(), () => Promise.reject(reason));
    expect(ToolMessage.isInstance(result)).toBe(true);
    if (!ToolMessage.isInstance(result)) throw new Error("expected fixture ToolMessage");
    expect(result.content).toBe(SAFE_MCP_ERROR);
    expect(result.status).toBe("error");
    expect(result.tool_call_id).toBe(callId);
  });

  it("Agent 工具以 null 拒绝时仍可安全完成下一轮", async () => {
    const { search, modelInputs, agent } = guardedAgent();
    vi.spyOn(search, "invoke").mockRejectedValue(null);
    const result = await agent.invoke(input);
    expect(result.messages.find(ToolMessage.isInstance)?.content).toBe(SAFE_MCP_ERROR);
    expect(modelInputs).toHaveLength(2);
    expect(modelInputs[1]).toContain("MCP_TOOL_FAILED");
  });

  it("正常成功结果不被误改为错误", async () => {
    const { agent, modelInputs } = guardedAgent();
    const result = await agent.invoke(input);
    const message = result.messages.find(ToolMessage.isInstance);
    expect(message?.status).toBe("success");
    expect(message?.content).toBe("正常匿名结果");
    expect(modelInputs[1]).toContain("正常匿名结果");
  });
});

describe("中断与取消不被吞成普通工具失败", () => {
  it("保持 LangGraph 中断信号", async () => {
    const interrupted = new GraphInterrupt([]);
    await expect(boundary()(wrapperRequest(), async () => { throw interrupted; })).rejects.toBe(interrupted);
  });
  it("保持返回的 Command 控制流", async () => {
    const command = new Command({ update: { fixture: true } });
    expect(await boundary()(wrapperRequest(), async () => command)).toBe(command);
  });
  it("预先取消时不执行工具", async () => {
    const controller = new AbortController();
    const canceled = new DOMException("fixture cancellation", "AbortError");
    controller.abort(canceled);
    const handler = vi.fn(async () => new ToolMessage({ content: "不应执行", tool_call_id: callId }));
    await expect(boundary()(wrapperRequest(controller.signal), handler)).rejects.toBe(canceled);
    expect(handler).not.toHaveBeenCalled();
  });
  it("工具执行中取消时不把返回错误当作可继续生成的结果", async () => {
    const controller = new AbortController();
    const canceled = new DOMException("fixture cancellation", "AbortError");
    await expect(boundary()(wrapperRequest(controller.signal), async () => {
      controller.abort(canceled);
      return new ToolMessage({ content: privateFixture, status: "error", tool_call_id: callId });
    })).rejects.toBe(canceled);
  });
  it("执行中以 null 为取消原因时原样传播，不变成 TypeError", async () => {
    const controller = new AbortController();
    await expect(boundary()(wrapperRequest(controller.signal), async () => {
      controller.abort(null);
      return new ToolMessage({ content: "不应作为正常结果继续生成", tool_call_id: callId });
    })).rejects.toBe(null);
  });
  it("没有调用 ID 时拒绝制造无关联的工具结果", async () => {
    const request = wrapperRequest();
    delete request.toolCall.id;
    await expect(boundary()(request, async () => { throw new Error(privateFixture); })).rejects.toThrow("缺少 ID");
  });
});
