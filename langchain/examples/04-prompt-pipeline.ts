import { HumanMessage, AIMessage } from "@langchain/core/messages";
import { ChatPromptTemplate, MessagesPlaceholder } from "@langchain/core/prompts";
import { StringOutputParser } from "@langchain/core/output_parsers";
import { createLessonModel, messageText } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async ({ live }) => {
  const prompt = ChatPromptTemplate.fromMessages([
    ["system", "用 {language} 面向有经验的 Node 开发者解释概念，区分模型与业务代码。"],
    new MessagesPlaceholder("history"),
    ["human", "{question}"],
  ]);
  const input = {
    language: "中文",
    history: [new HumanMessage("我熟悉 Express。"), new AIMessage("工具执行可以类比经过校验的 handler。")],
    question: "PromptTemplate 和普通字符串有什么不同？",
  };
  console.log("生成的消息:", (await prompt.invoke(input)).toChatMessages().map((message) => ({ role: message.type, content: messageText(message.content) })));
  const model = createLessonModel(live, ["固定回答：模板负责变量与消息结构，Runnable.pipe 负责数据流组合。"]);
  const pipeline = prompt.pipe(model).pipe(new StringOutputParser());
  console.log("pipeline:", await pipeline.invoke(input));
});
