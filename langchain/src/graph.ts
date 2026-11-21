import { Command, END, MemorySaver, START, StateGraph, StateSchema, interrupt } from "@langchain/langgraph";
import { z } from "zod";

export const ReviewState = new StateSchema({
  question: z.string(),
  topic: z.enum(["general", "refund"]).default("general"),
  answer: z.string().default(""),
});

export const DecisionSchema = z.object({ approved: z.boolean() }).strict();

/** 全部节点为确定性本地逻辑；审批演示不会真的退款或修改订单。 */
export function buildApprovalGraph() {
  return new StateGraph(ReviewState)
    .addNode("classify", (state) => ({ topic: state.question.includes("退款") ? "refund" as const : "general" as const }))
    .addNode("reply", () => ({ answer: "普通咨询已处理：本节点是固定规则演示。" }))
    .addNode("review", (state) => {
      const decision = DecisionSchema.parse(interrupt({ kind: "refund-review", question: state.question }));
      return { answer: decision.approved ? "人工已批准演示请求；未执行真实退款。" : "人工拒绝演示请求；未执行真实退款。" };
    })
    .addEdge(START, "classify")
    .addConditionalEdges("classify", (state) => state.topic === "refund" ? "review" : "reply", ["review", "reply"])
    .addEdge("reply", END)
    .addEdge("review", END)
    .compile({ checkpointer: new MemorySaver() });
}

export function resumeApproval(approved: boolean) {
  return new Command<{ approved: boolean }, typeof ReviewState.Update, "__start__" | "classify" | "reply" | "review">({ resume: { approved } });
}
