import { traceable } from "langsmith/traceable";
import { createLessonModel, messageText } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live, trace }) => {
  const model = createLessonModel(live, ["固定回答：追踪记录执行过程，不替你提供模型能力。"]);
  const task = async (question: string) => messageText((await model.invoke(question)).content);
  const answer = trace
    ? await traceable(task, { name: "anonymous-learning-task", tags: ["tutorial"], metadata: { lesson: "03" } })("追踪和模型服务有什么区别？")
    : await task("追踪和模型服务有什么区别？");
  console.log(answer);
  console.log(trace ? "CLI 退出前会等待追踪回调完成。" : "没有上传 trace。读者显式执行 --live --trace 才启用云端追踪。" );
});
