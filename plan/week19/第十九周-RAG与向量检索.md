# 第十九周：RAG 与向量检索

## 本周学习目标

- 理解 RAG（检索增强生成）的核心原理
- 掌握文本切片（Chunking）和 Embedding 技术
- 学会使用向量数据库（PGVector / Milvus）
- 实现一个企业知识库问答系统

---

## 一、学习内容

### 1. RAG 基础概念（星期一）

**什么是 RAG**
```
传统 LLM：
  用户提问 → LLM 生成回答（仅基于训练数据）
  
RAG 增强：
  用户提问 → 检索相关文档 → LLM 基于文档生成回答
  
优势：
  ✓ 减少幻觉（基于真实文档）
  ✓ 知识可更新（无需重新训练）
  ✓ 可追溯来源（引用文档）
  ✓ 成本更低（无需微调）
```

**RAG 流程**
```
离线流程（知识入库）：
  1. 文档加载（PDF/Word/Markdown）
  2. 文本切片（Chunking）
  3. 向量化（Embedding）
  4. 存入向量库

在线流程（问答）：
  1. 用户提问
  2. 问题向量化
  3. 向量检索（召回 Top K）
  4. 构建 Prompt（问题 + 召回文档）
  5. LLM 生成回答
  6. 返回答案 + 引用来源
```

**核心技术**
```markdown
1. 文本切片（Chunking）
   - 固定长度切片
   - 语义切片
   - 重叠切片

2. 向量化（Embedding）
   - OpenAI text-embedding-3-small
   - OpenAI text-embedding-3-large
   - 本地模型（bge-large-zh）

3. 向量检索
   - 余弦相似度
   - 欧氏距离
   - Top-K 检索

4. 重排序（Rerank）
   - 提高召回精度
   - 过滤无关内容
```

**实践任务**
- [ ] 理解 RAG 的核心价值
- [ ] 掌握 RAG 的完整流程
- [ ] 了解 Embedding 模型对比

---

### 2. 文本切片（Chunking）（星期一-星期二）

#### 2.1 固定长度切片

```go
package chunking

import "strings"

// FixedSizeChunker 固定长度切片
type FixedSizeChunker struct {
    ChunkSize    int // 每片字符数
    ChunkOverlap int // 重叠字符数
}

func (c *FixedSizeChunker) Split(text string) []string {
    var chunks []string
    runes := []rune(text)
    
    for i := 0; i < len(runes); i += c.ChunkSize - c.ChunkOverlap {
        end := i + c.ChunkSize
        if end > len(runes) {
            end = len(runes)
        }
        
        chunk := string(runes[i:end])
        chunks = append(chunks, strings.TrimSpace(chunk))
        
        if end >= len(runes) {
            break
        }
    }
    
    return chunks
}

// 使用示例
func ExampleFixedSize() {
    chunker := &FixedSizeChunker{
        ChunkSize:    500,  // 500 字符一片
        ChunkOverlap: 50,   // 重叠 50 字符
    }
    
    text := "这是一段很长的文本..."
    chunks := chunker.Split(text)
    // ["这是一段很长的文本...", "...的文本（续）..."]
}
```

**为什么需要重叠？**
```
示例文本：
"苹果公司成立于1976年。| 史蒂夫·乔布斯是创始人之一。"

无重叠切片：
  Chunk 1: "苹果公司成立于1976年。"
  Chunk 2: "史蒂夫·乔布斯是创始人之一。"
  问题：丢失了"苹果公司"和"创始人"的关联

有重叠切片：
  Chunk 1: "苹果公司成立于1976年。史蒂夫"
  Chunk 2: "1976年。史蒂夫·乔布斯是创始人之一。"
  优势：保留了上下文关联
```

#### 2.2 语义切片

```go
// SentenceChunker 按句子切片
type SentenceChunker struct {
    MaxChunkSize int
}

func (c *SentenceChunker) Split(text string) []string {
    // 按句号、问号、感叹号分句
    sentences := splitSentences(text)
    
    var chunks []string
    var currentChunk []string
    currentSize := 0
    
    for _, sentence := range sentences {
        sentenceLen := len([]rune(sentence))
        
        if currentSize+sentenceLen > c.MaxChunkSize && len(currentChunk) > 0 {
            // 当前 chunk 满了，保存并开始新的
            chunks = append(chunks, strings.Join(currentChunk, ""))
            currentChunk = []string{sentence}
            currentSize = sentenceLen
        } else {
            currentChunk = append(currentChunk, sentence)
            currentSize += sentenceLen
        }
    }
    
    // 保存最后一个 chunk
    if len(currentChunk) > 0 {
        chunks = append(chunks, strings.Join(currentChunk, ""))
    }
    
    return chunks
}

func splitSentences(text string) []string {
    // 简单实现：按标点分句
    separators := []string{"。", "！", "？", "\n"}
    sentences := []string{text}
    
    for _, sep := range separators {
        var newSentences []string
        for _, sent := range sentences {
            parts := strings.Split(sent, sep)
            for _, part := range parts {
                if part = strings.TrimSpace(part); part != "" {
                    newSentences = append(newSentences, part+sep)
                }
            }
        }
        sentences = newSentences
    }
    
    return sentences
}
```

#### 2.3 Markdown 结构化切片

```go
// MarkdownChunker 按 Markdown 结构切片
type MarkdownChunker struct {
    MaxChunkSize int
}

func (c *MarkdownChunker) Split(text string) []Chunk {
    lines := strings.Split(text, "\n")
    var chunks []Chunk
    var currentChunk Chunk
    var currentHeading string
    
    for _, line := range lines {
        // 检测标题
        if strings.HasPrefix(line, "#") {
            // 保存当前 chunk
            if currentChunk.Content != "" {
                chunks = append(chunks, currentChunk)
            }
            
            // 开始新 chunk
            currentHeading = strings.TrimPrefix(line, "# ")
            currentHeading = strings.TrimPrefix(currentHeading, "## ")
            currentHeading = strings.TrimPrefix(currentHeading, "### ")
            currentChunk = Chunk{
                Heading: currentHeading,
                Content: line + "\n",
            }
        } else {
            currentChunk.Content += line + "\n"
            
            // 如果当前 chunk 过大，分割
            if len([]rune(currentChunk.Content)) > c.MaxChunkSize {
                chunks = append(chunks, currentChunk)
                currentChunk = Chunk{
                    Heading: currentHeading,
                    Content: "",
                }
            }
        }
    }
    
    if currentChunk.Content != "" {
        chunks = append(chunks, currentChunk)
    }
    
    return chunks
}

type Chunk struct {
    Heading string
    Content string
}
```

**实践任务**
- [ ] 实现固定长度切片（支持重叠）
- [ ] 实现按句子切片
- [ ] 对比不同切片方法的效果

---

### 3. 文本向量化（Embedding）（星期二-星期三）

#### 3.1 调用 OpenAI Embedding API

```go
package embedding

import (
    "context"
    "github.com/openai/openai-go"
)

type EmbeddingService struct {
    client *openai.Client
}

func NewEmbeddingService(apiKey string) *EmbeddingService {
    client := openai.NewClient(apiKey)
    return &EmbeddingService{client: client}
}

// Embed 生成单个文本的向量
func (s *EmbeddingService) Embed(ctx context.Context, text string) ([]float32, error) {
    resp, err := s.client.Embeddings.Create(ctx, openai.EmbeddingCreateParams{
        Input: openai.F(text),
        Model: openai.F("text-embedding-3-small"),  // 或 text-embedding-3-large
    })
    if err != nil {
        return nil, err
    }
    
    return resp.Data[0].Embedding, nil
}

// EmbedBatch 批量生成向量（提高效率）
func (s *EmbeddingService) EmbedBatch(ctx context.Context, texts []string) ([][]float32, error) {
    resp, err := s.client.Embeddings.Create(ctx, openai.EmbeddingCreateParams{
        Input: openai.F(texts),
        Model: openai.F("text-embedding-3-small"),
    })
    if err != nil {
        return nil, err
    }
    
    embeddings := make([][]float32, len(resp.Data))
    for i, data := range resp.Data {
        embeddings[i] = data.Embedding
    }
    
    return embeddings, nil
}
```

#### 3.2 向量相似度计算

```go
package similarity

import "math"

// CosineSimilarity 余弦相似度（最常用）
func CosineSimilarity(a, b []float32) float32 {
    if len(a) != len(b) {
        panic("vectors must have the same length")
    }
    
    var dotProduct, normA, normB float32
    
    for i := 0; i < len(a); i++ {
        dotProduct += a[i] * b[i]
        normA += a[i] * a[i]
        normB += b[i] * b[i]
    }
    
    return dotProduct / (float32(math.Sqrt(float64(normA))) * float32(math.Sqrt(float64(normB))))
}

// EuclideanDistance 欧氏距离
func EuclideanDistance(a, b []float32) float32 {
    var sum float32
    for i := 0; i < len(a); i++ {
        diff := a[i] - b[i]
        sum += diff * diff
    }
    return float32(math.Sqrt(float64(sum)))
}

// 使用示例
func Example() {
    vec1 := []float32{0.1, 0.2, 0.3}
    vec2 := []float32{0.15, 0.25, 0.35}
    
    similarity := CosineSimilarity(vec1, vec2)
    // similarity ≈ 0.999（非常相似）
}
```

**实践任务**
- [ ] 申请 OpenAI API Key
- [ ] 实现文本向量化接口
- [ ] 理解余弦相似度的计算

---

### 4. 向量数据库（星期三-星期四）

#### 4.1 PostgreSQL + PGVector

**安装 PGVector 扩展**
```sql
-- 在 PostgreSQL 中创建扩展
CREATE EXTENSION vector;
```

**创建表**
```sql
CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    content TEXT NOT NULL,
    embedding vector(1536),  -- OpenAI text-embedding-3-small 维度
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

-- 创建向量索引（加速检索）
CREATE INDEX ON documents USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

**Go 集成**
```go
package vectordb

import (
    "context"
    "github.com/jackc/pgx/v5/pgxpool"
    "github.com/pgvector/pgvector-go"
)

type PGVectorDB struct {
    pool *pgxpool.Pool
}

func NewPGVectorDB(connString string) (*PGVectorDB, error) {
    pool, err := pgxpool.New(context.Background(), connString)
    if err != nil {
        return nil, err
    }
    return &PGVectorDB{pool: pool}, nil
}

// Insert 插入文档和向量
func (db *PGVectorDB) Insert(ctx context.Context, doc Document) error {
    query := `
        INSERT INTO documents (content, embedding, metadata)
        VALUES ($1, $2, $3)
    `
    
    _, err := db.pool.Exec(ctx, query,
        doc.Content,
        pgvector.NewVector(doc.Embedding),
        doc.Metadata,
    )
    return err
}

// Search 向量检索（Top K）
func (db *PGVectorDB) Search(ctx context.Context, queryEmbedding []float32, topK int) ([]Document, error) {
    query := `
        SELECT id, content, metadata, 
               embedding <=> $1 AS distance
        FROM documents
        ORDER BY embedding <=> $1
        LIMIT $2
    `
    
    rows, err := db.pool.Query(ctx, query,
        pgvector.NewVector(queryEmbedding),
        topK,
    )
    if err != nil {
        return nil, err
    }
    defer rows.Close()
    
    var docs []Document
    for rows.Next() {
        var doc Document
        var distance float32
        err := rows.Scan(&doc.ID, &doc.Content, &doc.Metadata, &distance)
        if err != nil {
            return nil, err
        }
        doc.Score = 1 - distance  // 转换为相似度分数
        docs = append(docs, doc)
    }
    
    return docs, nil
}

type Document struct {
    ID        int
    Content   string
    Embedding []float32
    Metadata  map[string]interface{}
    Score     float32
}
```

#### 4.2 Milvus（可选，高性能方案）

```go
package vectordb

import (
    "context"
    "github.com/milvus-io/milvus-sdk-go/v2/client"
)

type MilvusDB struct {
    client client.Client
}

func NewMilvusDB(addr string) (*MilvusDB, error) {
    c, err := client.NewClient(context.Background(), client.Config{
        Address: addr,
    })
    if err != nil {
        return nil, err
    }
    return &MilvusDB{client: c}, nil
}

// CreateCollection 创建集合
func (m *MilvusDB) CreateCollection(ctx context.Context) error {
    schema := &entity.Schema{
        CollectionName: "documents",
        Fields: []*entity.Field{
            {
                Name:       "id",
                DataType:   entity.FieldTypeInt64,
                PrimaryKey: true,
                AutoID:     true,
            },
            {
                Name:     "content",
                DataType: entity.FieldTypeVarChar,
                TypeParams: map[string]string{
                    "max_length": "65535",
                },
            },
            {
                Name:     "embedding",
                DataType: entity.FieldTypeFloatVector,
                TypeParams: map[string]string{
                    "dim": "1536",
                },
            },
        },
    }
    
    return m.client.CreateCollection(ctx, schema, 2)
}

// Insert 插入数据
func (m *MilvusDB) Insert(ctx context.Context, docs []Document) error {
    var contents []string
    var embeddings [][]float32
    
    for _, doc := range docs {
        contents = append(contents, doc.Content)
        embeddings = append(embeddings, doc.Embedding)
    }
    
    _, err := m.client.Insert(ctx, "documents", "",
        entity.NewColumnVarChar("content", contents),
        entity.NewColumnFloatVector("embedding", 1536, embeddings),
    )
    
    return err
}

// Search 向量检索
func (m *MilvusDB) Search(ctx context.Context, queryEmbedding []float32, topK int) ([]Document, error) {
    vectors := []entity.Vector{
        entity.FloatVector(queryEmbedding),
    }
    
    sp, _ := entity.NewIndexFlatSearchParam()
    
    results, err := m.client.Search(ctx, "documents", nil,
        "", []string{"content"},
        vectors,
        "embedding",
        entity.L2,
        topK,
        sp,
    )
    
    if err != nil {
        return nil, err
    }
    
    var docs []Document
    for _, result := range results {
        for i := 0; i < result.ResultCount; i++ {
            content, _ := result.Fields.GetColumn("content").Get(i)
            docs = append(docs, Document{
                Content: content.(string),
                Score:   result.Scores[i],
            })
        }
    }
    
    return docs, nil
}
```

**实践任务**
- [ ] 配置 PostgreSQL + PGVector
- [ ] 实现文档入库
- [ ] 实现向量检索

---

### 5. RAG 系统实现（星期四-星期五）

#### 5.1 完整流程

```go
package rag

import (
    "context"
    "fmt"
)

type RAGService struct {
    embedder  *EmbeddingService
    vectorDB  *PGVectorDB
    llmClient *openai.Client
}

// Index 知识入库
func (s *RAGService) Index(ctx context.Context, filePath string) error {
    // 1. 加载文档
    content, err := loadDocument(filePath)
    if err != nil {
        return err
    }
    
    // 2. 文本切片
    chunker := &FixedSizeChunker{ChunkSize: 500, ChunkOverlap: 50}
    chunks := chunker.Split(content)
    
    // 3. 批量向量化
    embeddings, err := s.embedder.EmbedBatch(ctx, chunks)
    if err != nil {
        return err
    }
    
    // 4. 存入向量库
    for i, chunk := range chunks {
        doc := Document{
            Content:   chunk,
            Embedding: embeddings[i],
            Metadata: map[string]interface{}{
                "source": filePath,
                "chunk_index": i,
            },
        }
        
        if err := s.vectorDB.Insert(ctx, doc); err != nil {
            return err
        }
    }
    
    return nil
}

// Query RAG 问答
func (s *RAGService) Query(ctx context.Context, question string) (string, error) {
    // 1. 问题向量化
    queryEmbedding, err := s.embedder.Embed(ctx, question)
    if err != nil {
        return "", err
    }
    
    // 2. 向量检索（召回 Top 3）
    docs, err := s.vectorDB.Search(ctx, queryEmbedding, 3)
    if err != nil {
        return "", err
    }
    
    // 3. 构建 Prompt
    prompt := buildRAGPrompt(question, docs)
    
    // 4. 调用 LLM 生成回答
    resp, err := s.llmClient.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
        Model: openai.F("gpt-4o-mini"),
        Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
            openai.UserMessage(prompt),
        }),
    })
    
    if err != nil {
        return "", err
    }
    
    answer := resp.Choices[0].Message.Content
    
    // 5. 返回答案 + 来源
    return formatAnswer(answer, docs), nil
}

func buildRAGPrompt(question string, docs []Document) string {
    context := ""
    for i, doc := range docs {
        context += fmt.Sprintf("\n[文档%d]\n%s\n", i+1, doc.Content)
    }
    
    return fmt.Sprintf(`请基于以下参考文档回答问题。如果文档中没有相关信息，请明确说明。

参考文档：
%s

问题：%s

回答：`, context, question)
}

func formatAnswer(answer string, docs []Document) string {
    result := answer + "\n\n参考来源：\n"
    for i, doc := range docs {
        source := doc.Metadata["source"]
        result += fmt.Sprintf("%d. %s (相关度: %.2f)\n", i+1, source, doc.Score)
    }
    return result
}
```

#### 5.2 高级优化

**混合检索（Hybrid Search）**
```go
// 结合关键词检索和向量检索
func (s *RAGService) HybridSearch(ctx context.Context, query string, topK int) ([]Document, error) {
    // 1. 向量检索
    queryEmbedding, _ := s.embedder.Embed(ctx, query)
    vectorResults, _ := s.vectorDB.Search(ctx, queryEmbedding, topK*2)
    
    // 2. 全文检索（PostgreSQL）
    keywordResults, _ := s.vectorDB.KeywordSearch(ctx, query, topK*2)
    
    // 3. 结果融合（RRF - Reciprocal Rank Fusion）
    merged := mergeResults(vectorResults, keywordResults)
    
    // 4. 返回 Top K
    if len(merged) > topK {
        merged = merged[:topK]
    }
    
    return merged, nil
}
```

**重排序（Rerank）**
```go
// 使用更强的模型对初步召回结果重新打分
func (s *RAGService) Rerank(ctx context.Context, query string, docs []Document) ([]Document, error) {
    var reranked []Document
    
    for _, doc := range docs {
        // 使用 LLM 评估相关性
        prompt := fmt.Sprintf(`问题：%s\n文档：%s\n\n这个文档与问题的相关性（0-10分）：`, query, doc.Content)
        
        resp, _ := s.llmClient.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
            Model: openai.F("gpt-4o-mini"),
            Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
                openai.UserMessage(prompt),
            }),
        })
        
        score := parseScore(resp.Choices[0].Message.Content)
        doc.Score = float32(score)
        reranked = append(reranked, doc)
    }
    
    // 按分数排序
    sort.Slice(reranked, func(i, j int) bool {
        return reranked[i].Score > reranked[j].Score
    })
    
    return reranked, nil
}
```

---

## 二、本周实战任务

### 任务：企业知识库 FAQ Bot（星期五-星期日）

**功能需求**
1. 支持上传 Markdown 文档
2. 自动切片并向量化入库
3. 用户提问，返回相关答案
4. 展示引用来源

**技术栈**
- 后端：Gin + PostgreSQL + PGVector
- Embedding：OpenAI text-embedding-3-small
- LLM：OpenAI GPT-4o-mini

**验收标准**
- [ ] 能上传并索引至少 10 篇文档
- [ ] 问答准确率 >80%
- [ ] 响应时间 <3s
- [ ] 能展示答案来源

---

## 三、推荐资源

### 必读文档
- [LangChain RAG 教程](https://python.langchain.com/docs/tutorials/rag/)
- [OpenAI Embeddings 文档](https://platform.openai.com/docs/guides/embeddings)
- [PGVector 文档](https://github.com/pgvector/pgvector)

### 推荐阅读
- [RAG 最佳实践](https://www.anthropic.com/news/contextual-retrieval)
- [如何评估 RAG 系统](https://docs.ragas.io/)

---

## 四、本周复盘模板

```markdown
### 第 19 周复盘

**这周学了什么**
- RAG 核心原理和流程
- 文本切片和向量化
- 向量数据库使用

**这周做了什么**
- 实现了企业知识库 FAQ Bot
- 索引了 20 篇技术文档
- 问答准确率达到 85%

**真正掌握了什么**
- 理解了 RAG 如何减少幻觉
- 会用 PGVector 做向量检索
- 能独立搭建知识库系统

**下周怎么调整**
- 学习 Tool Calling 和 MCP
- 实现 Agent 能力
```

---

## 五、下周预告

**第二十周：Tool Calling / MCP / Agent 基础**
- Tool Calling 原理和实现
- MCP 协议基础
- Agent 的 ReAct 循环
- 实现查天气/查订单 Agent
