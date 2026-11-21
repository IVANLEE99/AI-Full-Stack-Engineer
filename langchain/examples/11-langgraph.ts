import { buildApprovalGraph, resumeApproval } from "../src/graph.js";
import { runExample } from "../src/runtime.js";

await runExample(async () => {
  const graph = buildApprovalGraph();
  const regular = { configurable: { thread_id: "graph-general" }, streamMode: "updates" as const };
  for await (const update of await graph.stream({ question: "配送咨询" }, regular)) console.log("节点增量:", update);
  const review = { configurable: { thread_id: "graph-review" } };
  const paused = await graph.invoke({ question: "申请退款" }, review);
  console.log("暂停结果:", paused);
  const completed = await graph.invoke(resumeApproval(true), review);
  console.log("同线程恢复:", completed.answer);
  console.log("图只演示 classify → reply/review → END，未执行真实退款。interrupt 前的节点代码可能在恢复时重跑。" );
});
