import { describe, expect, it } from "vitest";
import { buildApprovalGraph, DecisionSchema, resumeApproval } from "../src/graph.js";
import { buildMcpAdapter } from "../src/mcp.js";

describe("LangGraph 可控分支和恢复", () => {
  it("普通咨询走 reply 分支，节点返回增量", async () => {
    const graph = buildApprovalGraph();
    const updates: unknown[] = [];
    for await (const update of await graph.stream({ question: "配送咨询" }, { configurable: { thread_id: "graph-normal" }, streamMode: "updates" })) updates.push(update);
    expect(updates).toHaveLength(2);
    expect(JSON.stringify(updates)).toContain("reply");
    expect(JSON.stringify(updates)).not.toContain("refund-review");
  });
  it("退款演示先 interrupt，批准后仅恢复同一线程", async () => {
    const graph = buildApprovalGraph();
    const config = { configurable: { thread_id: "graph-approved" } };
    const paused = await graph.invoke({ question: "申请退款" }, config);
    expect(paused.answer).toBe("");
    expect(JSON.stringify(paused)).toContain("refund-review");
    const completed = await graph.invoke(resumeApproval(true), config);
    expect(completed.answer).toContain("人工已批准");
    expect(completed.answer).toContain("未执行真实退款");
  });
  it("拒绝分支与错误恢复参数都可识别", async () => {
    const graph = buildApprovalGraph();
    const config = { configurable: { thread_id: "graph-rejected" } };
    await graph.invoke({ question: "申请退款" }, config);
    expect((await graph.invoke(resumeApproval(false), config)).answer).toContain("人工拒绝");
    expect(DecisionSchema.safeParse({ approved: "true" }).success).toBe(false);
  });
});

describe("本地 MCP 协议集成", () => {
  it("通过 stdio 发现并调用只读知识库工具，然后关闭子进程", async () => {
    const adapter = buildMcpAdapter(false);
    try {
      const tools = await adapter.listTools();
      const search = tools.find((tool) => tool.name === "knowledge_search");
      expect(search).toBeDefined();
      if (!search) throw new Error("missing fixture tool");
      const result = await search.invoke({ query: "退货" });
      const content = typeof result === "string" ? result : JSON.stringify(result);
      expect(content).toContain("fixtures/knowledge/returns.md");
      expect(content).toContain("7 天");
      await expect(search.invoke({ query: "" })).rejects.toThrow();
    } finally {
      await adapter.close();
    }
  });
});
