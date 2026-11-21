import { ToolMessage } from "@langchain/core/messages";
import { isGraphBubbleUp } from "@langchain/langgraph";
import { createMiddleware } from "langchain";
import { TutorialConfigError } from "./runtime.js";

export const SAFE_MCP_ERROR = JSON.stringify({
  code: "MCP_TOOL_FAILED",
  info: "知识库工具暂时不可用，请稍后重试或转人工；未公开原始错误。",
});

/** 在错误进入下一轮模型输入前处理，而不只在 CLI catch 或日志层掩盖。 */
export function mcpErrorBoundaryMiddleware() {
  return createMiddleware({
    name: "mcp-safe-errors",
    wrapToolCall: async (request, handler) => {
      const callId = request.toolCall.id;
      if (!callId) throw new TutorialConfigError("MCP 工具调用缺少 ID，不能安全关联工具结果。");
      const safeResult = () => new ToolMessage({
        content: SAFE_MCP_ERROR,
        name: request.toolCall.name,
        tool_call_id: callId,
        status: "error",
        // 不保留可能包含原始服务响应的 artifact、metadata 或异常对象。
      });
      request.runtime.signal?.throwIfAborted();
      try {
        const result = await handler(request);
        request.runtime.signal?.throwIfAborted();
        // 适配器收到 MCP isError 时可以直接返回 ToolMessage，而不是抛异常。
        if (ToolMessage.isInstance(result) && result.status === "error") return safeResult();
        return result;
      } catch (error) {
        // 先保留取消原因（包括合法的 null reason），再检查可能为 null 的拒绝值。
        request.runtime.signal?.throwIfAborted();
        // 中断、审批和取消不是可解释为“工具暂时不可用”的普通错误。
        if ((error != null && isGraphBubbleUp(error)) || (error instanceof Error && error.name === "AbortError")) {
          throw error;
        }
        return safeResult();
      }
    },
  });
}
