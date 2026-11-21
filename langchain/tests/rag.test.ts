import { beforeAll, describe, expect, it } from "vitest";
import { Document } from "@langchain/core/documents";
import { RecursiveCharacterTextSplitter } from "@langchain/textsplitters";
import { buildContext, buildLocalKnowledgeBase, KeywordEmbeddings, loadKnowledge, retrieveEvidence, sourceOf, type KnowledgeBase } from "../src/rag.js";

let base: KnowledgeBase;
beforeAll(async () => { base = await buildLocalKnowledgeBase(); });

describe("匿名本地知识库", () => {
  it("只读取课程 fixture，并保留相对来源", async () => {
    const docs = await loadKnowledge();
    expect(docs).toHaveLength(3);
    expect(docs.map(sourceOf)).toEqual(["fixtures/knowledge/returns.md", "fixtures/knowledge/shipping.md", "fixtures/knowledge/warranty.md"]);
  });
  it("切片保留来源和切片 ID，字符长度符合本配置", () => {
    expect(base.chunks.length).toBeGreaterThanOrEqual(3);
    for (const chunk of base.chunks) {
      expect(sourceOf(chunk)).toMatch(/^fixtures\/knowledge\//);
      expect(chunk.metadata.chunkId).toMatch(/^chunk-\d+$/);
      expect(chunk.pageContent.length).toBeLessThanOrEqual(280);
    }
  });
  it("长文档实际拆成多个片段，而且每片保留来源", async () => {
    const splitter = new RecursiveCharacterTextSplitter({ chunkSize: 80, chunkOverlap: 20, separators: ["。", ""] });
    const source = "fixtures/knowledge/returns.md";
    const chunks = await splitter.splitDocuments([new Document({ pageContent: "退货资料仅用于切片测试。".repeat(30), metadata: { source } })]);
    expect(chunks.length).toBeGreaterThan(1);
    for (const chunk of chunks) {
      expect(chunk.pageContent.length).toBeLessThanOrEqual(80);
      expect(sourceOf(chunk)).toBe(source);
    }
  });
  it("词项向量是确定性的，并把未知文本单独放到第四维", async () => {
    const embeddings = new KeywordEmbeddings();
    expect(await embeddings.embedQuery("退货退款")).toEqual([2, 0, 0, 0]);
    expect(await embeddings.embedQuery("天气")).toEqual([0, 0, 0, 1]);
    expect(await embeddings.embedDocuments(["退货", "保修维修"])).toEqual([[1, 0, 0, 0], [0, 0, 2, 0]]);
  });
  it("退货词项命中退货资料，不假装这是语义质量评估", async () => {
    const docs = await retrieveEvidence(base, "退货条件");
    expect(docs.length).toBeGreaterThan(0);
    expect(docs.every((doc) => sourceOf(doc) === "fixtures/knowledge/returns.md")).toBe(true);
    expect(docs.some((doc) => doc.pageContent.includes("7 天"))).toBe(true);
  });
  it("未知主题和空问题不会伪造命中证据", async () => {
    expect(await retrieveEvidence(base, "天气预报")).toEqual([]);
    expect(await retrieveEvidence(base, "  ")).toEqual([]);
  });
  it("可通过正式 retriever 接口调用同一向量库", async () => {
    const docs = await base.retriever.invoke("保修维修");
    expect(docs).toHaveLength(2);
    expect(sourceOf(docs[0]!)).toBe("fixtures/knowledge/warranty.md");
  });
  it("上下文包含来源且将文档标为不可信数据", async () => {
    const context = buildContext(await retrieveEvidence(base, "配送"));
    expect(context).toContain("fixtures/knowledge/shipping.md");
    expect(context).toContain("<untrusted_document>");
    expect(buildContext([])).toContain("没有检索到证据");
  });
  it("没有来源的 Document 不能被当作已证实引用", () => {
    expect(() => sourceOf(new Document({ pageContent: "fixture" }))).toThrow("来源");
  });
});
