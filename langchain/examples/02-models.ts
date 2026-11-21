import { createLessonModel, messageText } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const model = createLessonModel(live, [
    "固定回答：invoke 返回 AIMessage，而不是 HTTP 原始 JSON。",
    "固定批处理回答 A。", "固定批处理回答 B。",
    "固定流式回答：逐块消费 AsyncIterable。",
  ]);
  const response = await model.invoke("一句话解释 invoke。", { signal: AbortSignal.timeout(20_000) });
  console.log("invoke:", messageText(response.content));
  console.log("usage:", response.usage_metadata ?? "模拟模型没有真实 token 用量");
  const batch = await model.batch(["一句话解释 batch。", "一句话解释 stream。"], { maxConcurrency: 2 });
  console.log("batch:", batch.map((message) => messageText(message.content)));
  process.stdout.write("stream: ");
  for await (const chunk of await model.stream("一句话解释 Node AsyncIterable。")) {
    process.stdout.write(messageText(chunk.content));
  }
  process.stdout.write("\n");
});
