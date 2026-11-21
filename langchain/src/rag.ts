import { readFile, readdir } from "node:fs/promises";
import { Document } from "@langchain/core/documents";
import { Embeddings, type EmbeddingsInterface } from "@langchain/core/embeddings";
import { MemoryVectorStore } from "@langchain/classic/vectorstores/memory";
import { RecursiveCharacterTextSplitter } from "@langchain/textsplitters";

const vocabulary = [["退货", "退款"], ["配送", "发货"], ["保修", "维修"]] as const;

/** 固定词项计数向量，不是训练出的语义 Embedding，仅用于离线验证数据流。 */
export class KeywordEmbeddings extends Embeddings {
  constructor() { super({}); }
  async embedQuery(text: string): Promise<number[]> {
    const vector = vocabulary.map((words) => words.reduce((sum, word) => sum + text.split(word).length - 1, 0));
    return [...vector, vector.every((value) => value === 0) ? 1 : 0];
  }
  async embedDocuments(texts: string[]): Promise<number[][]> {
    return Promise.all(texts.map((text) => this.embedQuery(text)));
  }
}

export async function loadKnowledge(): Promise<Document[]> {
  const directory = new URL("../fixtures/knowledge/", import.meta.url);
  const names = (await readdir(directory)).filter((name) => name.endsWith(".md")).sort();
  return Promise.all(names.map(async (name) => new Document({
    pageContent: await readFile(new URL(name, directory), "utf8"),
    metadata: { source: `fixtures/knowledge/${name}` },
  })));
}

export async function buildLocalKnowledgeBase(embeddings: EmbeddingsInterface = new KeywordEmbeddings()) {
  const documents = await loadKnowledge();
  const splitter = new RecursiveCharacterTextSplitter({
    chunkSize: 280,
    chunkOverlap: 40,
    separators: ["\n\n", "\n", "。", " ", ""],
  });
  const chunks = (await splitter.splitDocuments(documents)).map((doc, index) => new Document({
    pageContent: doc.pageContent,
    metadata: { ...doc.metadata, chunkId: `chunk-${index}` },
  }));
  const store = await MemoryVectorStore.fromDocuments(chunks, embeddings);
  return { documents, chunks, store, retriever: store.asRetriever({ k: 2 }) };
}

export type KnowledgeBase = Awaited<ReturnType<typeof buildLocalKnowledgeBase>>;

/** 0.1 是词项模拟向量的测试阈值；真实 Embedding 必须在评估集上重新定阈值。 */
export async function retrieveEvidence(base: KnowledgeBase, question: string, minScore = 0.1): Promise<Document[]> {
  if (!question.trim()) return [];
  const scored = await base.store.similaritySearchWithScore(question, 3);
  return scored.filter(([, score]) => Number.isFinite(score) && score >= minScore).map(([doc]) => doc);
}

export function sourceOf(document: Document): string {
  const source: unknown = document.metadata.source;
  if (typeof source !== "string" || !source.startsWith("fixtures/knowledge/")) {
    throw new Error("知识库 Document 缺少合法的相对来源路径。");
  }
  return source;
}

export function buildContext(documents: Document[]): string {
  if (documents.length === 0) return "没有检索到证据。请说明资料不足，不编造政策。";
  return documents.map((doc, index) => `[${index + 1}] 来源：${sourceOf(doc)}\n<untrusted_document>\n${doc.pageContent}\n</untrusted_document>`).join("\n\n");
}
