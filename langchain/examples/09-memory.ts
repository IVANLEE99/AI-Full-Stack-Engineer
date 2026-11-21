import { MemorySaver, InMemoryStore } from "@langchain/langgraph";
import { createAgent } from "langchain";
import { createLessonModel } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const checkpointer = new MemorySaver();
  const store = new InMemoryStore();
  const agent = createAgent({
    model: createLessonModel(live, ["第一轮固定回答。", "第二轮固定回答。", "另一个线程的固定回答。"]),
    tools: [],
    systemPrompt: "你是匿名练习助手，不保存敏感资料。",
    checkpointer,
    store,
  });
  const threadA = { configurable: { thread_id: "demo-thread-a" } };
  const threadB = { configurable: { thread_id: "demo-thread-b" } };
  await agent.invoke({ messages: [{ role: "user", content: "本轮主题是配送。" }] }, threadA);
  const continued = await agent.invoke({ messages: [{ role: "user", content: "列出本线程已有的用户消息。" }] }, threadA);
  const isolated = await agent.invoke({ messages: [{ role: "user", content: "开启独立的练习会话。" }] }, threadB);
  console.log("线程 A 的用户消息:", continued.messages.filter((message) => message.type === "human").map((message) => message.content));
  console.log("线程 B 的用户消息:", isolated.messages.filter((message) => message.type === "human").map((message) => message.content));
  const namespaceA = ["demo-user-a", "preferences"];
  const namespaceB = ["demo-user-b", "preferences"];
  await store.put(namespaceA, "language", { language: "zh-CN" });
  console.log("用户 A 偏好:", (await store.get(namespaceA, "language"))?.value);
  console.log("用户 B 同名 key:", await store.get(namespaceB, "language"));
  console.log("这里只验证状态历史和 namespace 隔离，不验证模型记忆质量；进程退出后内存数据消失。thread_id 不是鉴权。" );
});
