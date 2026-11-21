import { AIMessage, HumanMessage, SystemMessage } from "@langchain/core/messages";
import { messageText } from "../src/models.js";
import { runExample } from "../src/runtime.js";

await runExample(async () => {
  console.log(`Node.js ${process.version}；本课程使用 ESM + TypeScript。`);
  const messages = [
    new SystemMessage("你是匿名学习助手。"),
    new HumanMessage("解释模型、工具和 Agent 分别做什么。"),
    new AIMessage("这是手工构造的消息，不是模型生成：模型提议，应用执行工具，Agent 编排循环。"),
  ];
  for (const message of messages) console.log(message.type, messageText(message.content));
  console.log("下一步：npm run example:model；切勿把聊天消息对象误认为服务端业务执行。" );
});
