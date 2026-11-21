import { z } from "zod";

export const TicketSchema = z.object({
  category: z.enum(["order", "refund", "other"]).describe("工单分类"),
  summary: z.string().min(1).max(200).describe("中文工单摘要，不包含个人信息"),
  requiresHuman: z.boolean().describe("是否需要转人工处理"),
}).strict();

export type Ticket = z.infer<typeof TicketSchema>;

export const AssistantAnswerSchema = z.object({
  answer: z.string().min(1).describe("仅基于已检索资料的中文回答"),
  citations: z.array(z.string()).describe("检索结果中的相对来源路径；没有证据时为空"),
  needsReview: z.boolean().describe("没有证据或超出资料范围时为 true"),
}).strict();

/** Schema 合法不等于事实正确：来源必须来自本次检索，而不是模型自行编造。 */
export function validateCitations(value: unknown, allowedSources: ReadonlySet<string>) {
  const answer = AssistantAnswerSchema.parse(value);
  if (answer.citations.some((source) => !allowedSources.has(source))) {
    throw new Error("回答包含不在本次检索结果中的来源。");
  }
  if (allowedSources.size === 0 && (!answer.needsReview || answer.citations.length > 0)) {
    throw new Error("无检索证据时必须标记 needsReview，且不能附带引用。");
  }
  return answer;
}
