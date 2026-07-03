# 第二十一周：多 Agent 与需求拆解

## 本周学习目标

- 理解多 Agent 协作模式
- 掌握需求拆解方法论
- 学会从一句话需求生成结构化 PRD
- 实现一个项目管理 Agent 系统

---

## 一、学习内容

### 1. 多 Agent 协作模式（星期一-星期二）

#### 1.1 为什么需要多 Agent

**单 Agent 的局限**
```
问题：
- 上下文长度限制（无法处理超大任务）
- 专业性不足（一个模型难以精通所有领域）
- 可维护性差（所有逻辑耦合在一起）
- 并行能力弱（串行处理效率低）

解决方案：多 Agent
- 主管 Agent：任务分解、协调、结果汇总
- 专家 Agent：各司其职（研发、测试、设计、文档）
```

#### 1.2 常见多 Agent 模式

**模式 1：主管-子 Agent（Manager-Worker）**
```
┌─────────────┐
│ Manager Agent│  (任务分解、协调、汇总)
└──────┬───────┘
       │
   ┌───┴────┬────────┬────────┐
   │        │        │        │
┌──▼──┐  ┌─▼──┐  ┌──▼──┐  ┌──▼──┐
│Dev  │  │QA  │  │Design│ │Doc  │
│Agent│  │Agent│ │Agent │ │Agent│
└─────┘  └────┘  └──────┘ └─────┘

应用场景：项目开发、复杂任务拆解
```

**模式 2：流水线（Pipeline）**
```
输入 → Agent 1 → Agent 2 → Agent 3 → 输出
      (需求分析) (架构设计) (代码生成)

应用场景：有明确流程的任务
```

**模式 3：专家小组（Panel）**
```
       问题
         │
    ┌────┼────┐
    │    │    │
  ┌─▼─┐┌─▼─┐┌─▼─┐
  │专家││专家││专家│
  │ 1 ││ 2 ││ 3 │
  └─┬─┘└─┬─┘└─┬─┘
    │    │    │
    └────┼────┘
         │
      投票/综合

应用场景：需要多角度评估的任务（代码审查、决策）
```

**模式 4：ReAct 多 Agent**
```
主 Agent：思考 → 决定调用哪个子 Agent → 观察结果 → 继续思考

应用场景：动态决策、需要灵活协作
```

---

#### 1.3 实现主管-子 Agent

```go
package agent

import (
    "context"
    "fmt"
)

// ManagerAgent 主管 Agent
type ManagerAgent struct {
    client    *openai.Client
    workers   map[string]*WorkerAgent
}

func NewManagerAgent(apiKey string) *ManagerAgent {
    return &ManagerAgent{
        client: openai.NewClient(apiKey),
        workers: map[string]*WorkerAgent{
            "product_manager": NewProductManager(apiKey),
            "architect":       NewArchitect(apiKey),
            "developer":       NewDeveloper(apiKey),
            "qa":              NewQA(apiKey),
        },
    }
}

// Process 处理需求
func (m *ManagerAgent) Process(ctx context.Context, requirement string) (*ProjectPlan, error) {
    // 1. 分析需求，制定计划
    plan, err := m.analyzeTasks(ctx, requirement)
    if err != nil {
        return nil, err
    }
    
    // 2. 分配任务给子 Agent
    results := make(map[string]string)
    
    for _, task := range plan.Tasks {
        worker := m.workers[task.Agent]
        if worker == nil {
            return nil, fmt.Errorf("unknown agent: %s", task.Agent)
        }
        
        fmt.Printf("分配任务给 %s: %s\n", task.Agent, task.Description)
        
        result, err := worker.Execute(ctx, task)
        if err != nil {
            return nil, fmt.Errorf("task %s failed: %w", task.ID, err)
        }
        
        results[task.ID] = result
    }
    
    // 3. 汇总结果
    summary, err := m.summarize(ctx, results)
    if err != nil {
        return nil, err
    }
    
    return &ProjectPlan{
        Requirement: requirement,
        Tasks:       plan.Tasks,
        Results:     results,
        Summary:     summary,
    }, nil
}

// analyzeTasks 分析需求并分解任务
func (m *ManagerAgent) analyzeTasks(ctx context.Context, requirement string) (*TaskPlan, error) {
    prompt := fmt.Sprintf(`作为项目经理，将以下需求分解为具体任务：

需求：%s

请输出 JSON 格式的任务列表：
{
  "tasks": [
    {
      "id": "task-1",
      "agent": "product_manager",
      "description": "编写 PRD 文档",
      "dependencies": []
    },
    {
      "id": "task-2",
      "agent": "architect",
      "description": "设计系统架构",
      "dependencies": ["task-1"]
    }
  ]
}

可用 Agent：
- product_manager: 需求分析、PRD 编写
- architect: 系统架构设计
- developer: 代码实现
- qa: 测试用例编写`, requirement)

    resp, err := m.client.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
        Model: openai.F("gpt-4o"),
        Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
            openai.UserMessage(prompt),
        }),
        ResponseFormat: openai.F(openai.ResponseFormatJSONObject{
            Type: openai.F(openai.ResponseFormatJSONObjectTypeJSONObject),
        }),
    })
    
    if err != nil {
        return nil, err
    }
    
    var plan TaskPlan
    json.Unmarshal([]byte(resp.Choices[0].Message.Content), &plan)
    
    return &plan, nil
}

// WorkerAgent 子 Agent 基类
type WorkerAgent struct {
    name     string
    role     string
    client   *openai.Client
}

func (w *WorkerAgent) Execute(ctx context.Context, task Task) (string, error) {
    prompt := fmt.Sprintf(`你是一个 %s。

任务：%s

请完成这个任务并返回结果。`, w.role, task.Description)

    resp, err := w.client.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
        Model: openai.F("gpt-4o-mini"),
        Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
            openai.UserMessage(prompt),
        }),
    })
    
    if err != nil {
        return "", err
    }
    
    return resp.Choices[0].Message.Content, nil
}

// 具体子 Agent
func NewProductManager(apiKey string) *WorkerAgent {
    return &WorkerAgent{
        name:   "product_manager",
        role:   "产品经理，负责需求分析和 PRD 编写",
        client: openai.NewClient(apiKey),
    }
}

func NewArchitect(apiKey string) *WorkerAgent {
    return &WorkerAgent{
        name:   "architect",
        role:   "架构师，负责系统设计和技术选型",
        client: openai.NewClient(apiKey),
    }
}

func NewDeveloper(apiKey string) *WorkerAgent {
    return &WorkerAgent{
        name:   "developer",
        role:   "开发工程师，负责代码实现",
        client: openai.NewClient(apiKey),
    }
}

func NewQA(apiKey string) *WorkerAgent {
    return &WorkerAgent{
        name:   "qa",
        role:   "测试工程师，负责测试用例设计",
        client: openai.NewClient(apiKey),
    }
}

// 数据结构
type TaskPlan struct {
    Tasks []Task `json:"tasks"`
}

type Task struct {
    ID           string   `json:"id"`
    Agent        string   `json:"agent"`
    Description  string   `json:"description"`
    Dependencies []string `json:"dependencies"`
}

type ProjectPlan struct {
    Requirement string
    Tasks       []Task
    Results     map[string]string
    Summary     string
}
```

---

### 2. 需求拆解方法论（星期二-星期三）

#### 2.1 从一句话到 PRD

**拆解层次**
```
Level 0: 一句话需求
"做一个在线商城"

Level 1: 功能模块
- 用户模块
- 商品模块
- 订单模块
- 支付模块

Level 2: 用户故事
用户模块：
  - 作为用户，我希望能注册账号
  - 作为用户，我希望能登录系统
  - 作为用户，我希望能修改个人信息

Level 3: 验收标准
注册功能：
  - 支持手机号注册
  - 发送验证码
  - 密码强度校验
  - 注册成功后自动登录

Level 4: 技术任务
- 设计用户表结构
- 实现注册 API
- 实现验证码发送
- 前端注册页面
```

#### 2.2 实现需求拆解 Agent

```go
package agent

type RequirementAnalyzer struct {
    client *openai.Client
}

// Analyze 分析需求
func (a *RequirementAnalyzer) Analyze(ctx context.Context, requirement string) (*PRD, error) {
    prompt := fmt.Sprintf(`作为资深产品经理，将以下需求拆解为结构化 PRD：

需求：%s

输出 JSON 格式的 PRD：
{
  "title": "项目标题",
  "background": "项目背景",
  "objectives": ["目标1", "目标2"],
  "features": [
    {
      "name": "功能模块名",
      "description": "功能描述",
      "user_stories": [
        {
          "as": "用户角色",
          "want": "期望功能",
          "so_that": "价值/目的",
          "acceptance_criteria": ["验收标准1", "验收标准2"]
        }
      ],
      "priority": "P0/P1/P2"
    }
  ],
  "technical_requirements": {
    "frontend": ["技术要求"],
    "backend": ["技术要求"],
    "database": ["数据库设计要点"]
  },
  "milestones": [
    {
      "name": "里程碑名称",
      "deadline": "预计时间",
      "deliverables": ["交付物"]
    }
  ]
}`, requirement)

    resp, err := a.client.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
        Model: openai.F("gpt-4o"),
        Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
            openai.UserMessage(prompt),
        }),
        ResponseFormat: openai.F(openai.ResponseFormatJSONObject{
            Type: openai.F(openai.ResponseFormatJSONObjectTypeJSONObject),
        }),
    })
    
    if err != nil {
        return nil, err
    }
    
    var prd PRD
    json.Unmarshal([]byte(resp.Choices[0].Message.Content), &prd)
    
    return &prd, nil
}

type PRD struct {
    Title                 string              `json:"title"`
    Background            string              `json:"background"`
    Objectives            []string            `json:"objectives"`
    Features              []Feature           `json:"features"`
    TechnicalRequirements TechnicalRequirements `json:"technical_requirements"`
    Milestones            []Milestone         `json:"milestones"`
}

type Feature struct {
    Name        string       `json:"name"`
    Description string       `json:"description"`
    UserStories []UserStory  `json:"user_stories"`
    Priority    string       `json:"priority"`
}

type UserStory struct {
    As                 string   `json:"as"`
    Want               string   `json:"want"`
    SoThat             string   `json:"so_that"`
    AcceptanceCriteria []string `json:"acceptance_criteria"`
}

type TechnicalRequirements struct {
    Frontend []string `json:"frontend"`
    Backend  []string `json:"backend"`
    Database []string `json:"database"`
}

type Milestone struct {
    Name         string   `json:"name"`
    Deadline     string   `json:"deadline"`
    Deliverables []string `json:"deliverables"`
}
```

---

#### 2.3 从 PRD 到技术任务

```go
type TechnicalPlanner struct {
    client *openai.Client
}

// GenerateTasks 从 PRD 生成技术任务
func (p *TechnicalPlanner) GenerateTasks(ctx context.Context, prd *PRD) (*TechnicalPlan, error) {
    prdJSON, _ := json.MarshalIndent(prd, "", "  ")
    
    prompt := fmt.Sprintf(`作为技术负责人，根据以下 PRD 生成技术实现计划：

PRD：
%s

输出 JSON 格式的技术计划：
{
  "architecture": {
    "frontend": "技术栈描述",
    "backend": "技术栈描述",
    "database": "数据库方案",
    "infrastructure": "基础设施"
  },
  "database_schema": [
    {
      "table": "表名",
      "fields": [
        {"name": "字段名", "type": "类型", "description": "说明"}
      ]
    }
  ],
  "api_contracts": [
    {
      "path": "/api/users",
      "method": "POST",
      "description": "创建用户",
      "request": {"username": "string", "password": "string"},
      "response": {"id": "int", "username": "string"}
    }
  ],
  "tasks": [
    {
      "id": "TASK-001",
      "title": "任务标题",
      "description": "任务描述",
      "type": "backend/frontend/database/devops",
      "effort": "2h/1d/3d",
      "dependencies": ["TASK-000"]
    }
  ]
}`, string(prdJSON))

    resp, err := p.client.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
        Model: openai.F("gpt-4o"),
        Messages: openai.F([]openai.ChatCompletionMessageParamUnion{
            openai.UserMessage(prompt),
        }),
        ResponseFormat: openai.F(openai.ResponseFormatJSONObject{
            Type: openai.F(openai.ResponseFormatJSONObjectTypeJSONObject),
        }),
    })
    
    if err != nil {
        return nil, err
    }
    
    var plan TechnicalPlan
    json.Unmarshal([]byte(resp.Choices[0].Message.Content), &plan)
    
    return &plan, nil
}

type TechnicalPlan struct {
    Architecture   Architecture   `json:"architecture"`
    DatabaseSchema []TableSchema  `json:"database_schema"`
    APIContracts   []APIContract  `json:"api_contracts"`
    Tasks          []DevTask      `json:"tasks"`
}

type Architecture struct {
    Frontend       string `json:"frontend"`
    Backend        string `json:"backend"`
    Database       string `json:"database"`
    Infrastructure string `json:"infrastructure"`
}

type TableSchema struct {
    Table  string  `json:"table"`
    Fields []Field `json:"fields"`
}

type Field struct {
    Name        string `json:"name"`
    Type        string `json:"type"`
    Description string `json:"description"`
}

type APIContract struct {
    Path        string                 `json:"path"`
    Method      string                 `json:"method"`
    Description string                 `json:"description"`
    Request     map[string]interface{} `json:"request"`
    Response    map[string]interface{} `json:"response"`
}

type DevTask struct {
    ID           string   `json:"id"`
    Title        string   `json:"title"`
    Description  string   `json:"description"`
    Type         string   `json:"type"`
    Effort       string   `json:"effort"`
    Dependencies []string `json:"dependencies"`
}
```

---

### 3. 完整流程实现（星期三-星期五）

```go
package main

import (
    "context"
    "fmt"
)

// ProjectGenerator 项目生成器
type ProjectGenerator struct {
    analyzer *RequirementAnalyzer
    planner  *TechnicalPlanner
    manager  *ManagerAgent
}

func NewProjectGenerator(apiKey string) *ProjectGenerator {
    return &ProjectGenerator{
        analyzer: &RequirementAnalyzer{client: openai.NewClient(apiKey)},
        planner:  &TechnicalPlanner{client: openai.NewClient(apiKey)},
        manager:  NewManagerAgent(apiKey),
    }
}

// Generate 从需求到交付
func (g *ProjectGenerator) Generate(ctx context.Context, requirement string) error {
    fmt.Println("=== 阶段 1：需求分析 ===")
    
    // 1. 生成 PRD
    prd, err := g.analyzer.Analyze(ctx, requirement)
    if err != nil {
        return fmt.Errorf("需求分析失败: %w", err)
    }
    
    fmt.Printf("生成 PRD：%s\n", prd.Title)
    fmt.Printf("功能模块数：%d\n", len(prd.Features))
    
    // 保存 PRD
    if err := savePRD(prd, "docs/PRD.md"); err != nil {
        return err
    }
    
    fmt.Println("\n=== 阶段 2：技术设计 ===")
    
    // 2. 生成技术方案
    plan, err := g.planner.GenerateTasks(ctx, prd)
    if err != nil {
        return fmt.Errorf("技术设计失败: %w", err)
    }
    
    fmt.Printf("技术栈：%s + %s\n", plan.Architecture.Frontend, plan.Architecture.Backend)
    fmt.Printf("数据表数量：%d\n", len(plan.DatabaseSchema))
    fmt.Printf("API 数量：%d\n", len(plan.APIContracts))
    fmt.Printf("开发任务数：%d\n", len(plan.Tasks))
    
    // 保存技术方案
    if err := saveTechnicalPlan(plan, "docs/TECH_DESIGN.md"); err != nil {
        return err
    }
    
    fmt.Println("\n=== 阶段 3：任务分配与执行 ===")
    
    // 3. 多 Agent 执行
    result, err := g.manager.Process(ctx, requirement)
    if err != nil {
        return fmt.Errorf("任务执行失败: %w", err)
    }
    
    fmt.Println("任务执行完成")
    fmt.Println(result.Summary)
    
    return nil
}

func main() {
    generator := NewProjectGenerator("your-api-key")
    
    requirement := "开发一个在线考试系统，支持创建试卷、学生答题、自动批改、成绩统计等功能"
    
    err := generator.Generate(context.Background(), requirement)
    if err != nil {
        log.Fatal(err)
    }
}
```

---

## 二、本周实战任务

### 任务：项目需求拆解器（星期五-星期日）

**功能需求**
1. 输入一句话需求
2. 输出结构化 PRD
3. 生成数据库设计
4. 生成 API 契约
5. 生成开发任务列表

**验收标准**
- [ ] 能将一句话需求拆解为完整 PRD
- [ ] PRD 包含用户故事和验收标准
- [ ] 技术方案包含数据库设计
- [ ] API 契约定义清晰
- [ ] 任务列表可直接用于开发

---

## 三、推荐资源

### 必读文档
- [用户故事地图](https://www.jpattonassociates.com/user-story-mapping/)
- [INVEST 原则](https://xp123.com/articles/invest-in-good-stories-and-smart-tasks/)

### 推荐工具
- [AutoGPT](https://github.com/Significant-Gravitas/AutoGPT) - 自主 Agent
- [MetaGPT](https://github.com/geekan/MetaGPT) - 多 Agent 框架

---

## 四、本周复盘模板

```markdown
### 第 21 周复盘

**这周学了什么**
- 多 Agent 协作模式
- 需求拆解方法论
- 从需求到技术任务的完整流程

**这周做了什么**
- 实现了需求拆解 Agent
- 生成了 3 个项目的 PRD
- 完成了技术方案设计

**真正掌握了什么**
- 理解了多 Agent 如何协作
- 会将需求拆解为用户故事
- 能生成可执行的技术方案

**下周怎么调整**
- 整理知识沉淀
- 形成可复用的 Skill
```

---

## 五、下周预告

**第二十二周：Skill 化与知识沉淀**
- Prompt 模板设计
- 规则文件整理
- 团队知识库建设
- Agent 使用规范
