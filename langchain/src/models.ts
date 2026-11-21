import { FakeListChatModel } from "@langchain/core/utils/testing";
import { BaseChatModel } from "@langchain/core/language_models/chat_models";
import { ChatOpenAI } from "@langchain/openai";
import { FakeToolCallingModel } from "langchain";
import { requiredEnv, serviceUrl } from "./runtime.js";

export function createLiveModel(): ChatOpenAI {
  const baseURL = serviceUrl("OPENAI_BASE_URL");
  return new ChatOpenAI({
    apiKey: requiredEnv("OPENAI_API_KEY"),
    model: requiredEnv("OPENAI_MODEL"),
    ...(baseURL ? { configuration: { baseURL } } : {}),
    // 与 provider SDK 只保留这一层重试，避免多个重试层相乘。
    maxRetries: 1,
    timeout: 20_000,
  });
}

export function createLessonModel(live: boolean, responses: string[]): BaseChatModel {
  return live ? createLiveModel() : new FakeListChatModel({ responses });
}

export type ScriptedToolCalls = ConstructorParameters<typeof FakeToolCallingModel>[0];

export function createToolModel(live: boolean, fields: ScriptedToolCalls): BaseChatModel {
  return live ? createLiveModel() : new FakeToolCallingModel(fields);
}

/** AIMessage 的 content 可能包含文本、图片、推理等多个块，不直接强转 string。 */
export function messageText(content: unknown): string {
  if (typeof content === "string") return content;
  if (!Array.isArray(content)) return "";
  return content.map((block: unknown) => {
    if (typeof block === "string") return block;
    if (block && typeof block === "object" && "type" in block && block.type === "text" && "text" in block && typeof block.text === "string") {
      return block.text;
    }
    return "";
  }).join("");
}
