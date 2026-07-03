# 第二十周：Tool Calling / MCP / Agent 基础

## 本周学习目标

- 理解 Tool Calling（函数调用）的工作原理
- 掌握 MCP（Model Context Protocol）基础概念
- 学会实现 ReAct 循环
- 构建一个能调用工具的 Agent

---

## 一、学习内容

### 1. Tool Calling 基础（星期一-星期二）

**什么是 Tool Calling**
```
传统对话：
  用户："北京今天天气怎么样？"
  LLM："抱歉，我的知识截止到 2024 年..."

Tool Calling：
  用户："北京今天天气怎么样？"
  LLM：调用 get_weather(city="北京")
  工具返回：{"temp": 15, "weather": "晴"}
  LLM："北京今天 15°C，天气晴朗。"
```

**工作流程**
```
1. 定义工具（Tool Schema）
2. 用户提问
3. LLM 判断是否需要调用工具
4. 如果需要：
   - LLM 生成函数调用请求
   - 服务端执行函数
   - 将结果返回给 LLM
   - LLM 基于结果生成最终回答
5. 返回给用户
```

---

#### 1.1 定义工具（Tool Schema）

```go
package tools

import "github.com/openai/openai-go"

// 定义天气查询工具
var GetWeatherTool = openai.ChatCompletionToolParam{
    Type: openai.F("function"),
    Function: openai.F(openai.FunctionDefinitionParam{
        Name:        openai.F("get_weather"),
        Description: openai.F("获取指定城市的实时天气信息"),
        Parameters: openai.F(openai.FunctionParameters{
            "type": "object",
            "properties": map[string]interface{}{
                "city": map[string]interface{}{
                    "type":        "string",
                    "description": "城市名称，例如：北京、上海",
                },
                "unit": map[string]interface{}{
                    "type":        "string",
                    "enum":        []string{"celsius", "fahrenheit"},
                    "description": "温度单位",
                },
            },
            "required": []string{"city"},
        }),
    }),
}

// 计算器工具
var CalculatorTool = openai.ChatCompletionToolParam{
    Type: openai.F("function"),
    Function: openai.F(openai.FunctionDefinitionParam{
        Name:        openai.F("calculator"),
        Description: openai.F("执行数学计算"),
        Parameters: openai.F(openai.FunctionParameters{
            "type": "object",
            "properties": map[string]interface{}{
                "expression": map[string]interface{}{
                    "type":        "string",
                    "description": "数学表达式，例如：2 + 2，10 * 5",
                },
            },
            "required": []string{"expression"},
        }),
    }),
}

// 订单查询工具
var GetOrderTool = openai.ChatCompletionToolParam{
    Type: openai.F("function"),
    Function: openai.F(openai.FunctionDefinitionParam{
        Name:        openai.F("get_order"),
        Description: openai.F("查询订单详情"),
        Parameters: openai.F(openai.FunctionParameters{
            "type": "object",
            "properties": map[string]interface{}{
                "order_id": map[string]interface{}{
                    "type":        "string",
                    "description": "订单ID",
                },
            },
            "required": []string{"order_id"},
        }),
    }),
}
```

---

#### 1.2 实现工具函数

```go
package tools

import (
    "context"
    "encoding/json"
    "fmt"
    "math/big"
)

// ToolExecutor 工具执行器
type ToolExecutor struct {
    orderRepo OrderRepository
}

// Execute 执行工具调用
func (e *ToolExecutor) Execute(ctx context.Context, toolName string, arguments string) (string, error) {
    switch toolName {
    case "get_weather":
        return e.getWeather(arguments)
    case "calculator":
        return e.calculator(arguments)
    case "get_order":
        return e.getOrder(ctx, arguments)
    default:
        return "", fmt.Errorf("unknown tool: %s", toolName)
    }
}

// 天气查询（模拟）
func (e *ToolExecutor) getWeather(arguments string) (string, error) {
    var args struct {
        City string `json:"city"`
        Unit string `json:"unit"`
    }
    
    if err := json.Unmarshal([]byte(arguments), &args); err != nil {
        return "", err
    }
    
    // 实际项目中调用天气 API
    // 这里模拟返回
    weather := map[string]interface{}{
        "city":        args.City,
        "temperature": 15,
        "weather":     "晴",
        "humidity":    45,
    }
    
    result, _ := json.Marshal(weather)
    return string(result), nil
}

// 计算器
func (e *ToolExecutor) calculator(arguments string) (string, error) {
    var args struct {
        Expression string `json:"expression"`
    }
    
    if err := json.Unmarshal([]byte(arguments), &args); err != nil {
        return "", err
    }
    
    // 使用安全的表达式求值库
    result, err := evalExpression(args.Expression)
    if err != nil {
        return "", err
    }
    
    return fmt.Sprintf(`{"result": %v}`, result), nil
}

// 订单查询
func (e *ToolExecutor) getOrder(ctx context.Context, arguments string) (string, error) {
    var args struct {
        OrderID string `json:"order_id"`
    }
    
    if err := json.Unmarshal([]byte(arguments), &args); err != nil {
        return "", err
    }
    
    // 从数据库查询订单
    order, err := e.orderRepo.GetByID(ctx, args.OrderID)
    if err != nil {
        return "", err
    }
    
    result, _ := json.Marshal(order)
    return string(result), nil
}

// 简单的表达式求值（生产环境建议使用专业库）
func evalExpression(expr string) (float64, error) {
    // 这里使用 github.com/Knetic/govaluate 或类似库
    // 示例简化
    return 42.0, nil
}
```

---

#### 1.3 Agent 主循环

```go
package agent

import (
    "context"
    "encoding/json"
    "fmt"
    "github.com/openai/openai-go"
)

type Agent struct {
    client   *openai.Client
    executor *ToolExecutor
    tools    []openai.ChatCompletionToolParam
}

func NewAgent(apiKey string, executor *ToolExecutor) *Agent {
    return &Agent{
        client:   openai.NewClient(apiKey),
        executor: executor,
        tools: []openai.ChatCompletionToolParam{
            GetWeatherTool,
            CalculatorTool,
            GetOrderTool,
        },
    }
}

// Run 运行 Agent
func (a *Agent) Run(ctx context.Context, userMessage string) (string, error) {
    messages := []openai.ChatCompletionMessageParamUnion{
        openai.UserMessage(userMessage),
    }
    
    // 最多循环 5 次（防止无限循环）
    for i := 0; i < 5; i++ {
        // 调用 LLM
        resp, err := a.client.Chat.Completions.Create(ctx, openai.ChatCompletionCreateParams{
            Model:    openai.F("gpt-4o-mini"),
            Messages: openai.F(messages),
            Tools:    openai.F(a.tools),
        })
        
        if err != nil {
            return "", err
        }
        
        message := resp.Choices[0].Message
        
        // 如果没有工具调用，返回结果
        if len(message.ToolCalls) == 0 {
            return message.Content, nil
        }
        
        // 执行工具调用
        messages = append(messages, openai.AssistantMessage(message.Content))
        
        for _, toolCall := range message.ToolCalls {
            functionName := toolCall.Function.Name
            arguments := toolCall.Function.Arguments
            
            fmt.Printf("调用工具: %s(%s)\n", functionName, arguments)
            
            // 执行工具
            result, err := a.executor.Execute(ctx, functionName, arguments)
            if err != nil {
                result = fmt.Sprintf(`{"error": "%s"}`, err.Error())
            }
            
            fmt.Printf("工具返回: %s\n", result)
            
            // 将工具结果添加到消息历史
            messages = append(messages, openai.ToolMessage(toolCall.ID, result))
        }
    }
    
    return "", fmt.Errorf("达到最大循环次数")
}
```

**使用示例**
```go
func main() {
    executor := &ToolExecutor{orderRepo: orderRepo}
    agent := NewAgent("your-api-key", executor)
    
    // 示例 1：查天气
    answer, _ := agent.Run(context.Background(), "北京今天天气怎么样？")
    // LLM 调用 get_weather(city="北京")
    // 返回："北京今天 15°C，天气晴朗。"
    
    // 示例 2：计算
    answer, _ = agent.Run(context.Background(), "123 * 456 等于多少？")
    // LLM 调用 calculator(expression="123 * 456")
    // 返回："123 * 456 = 56088"
    
    // 示例 3：查订单
    answer, _ = agent.Run(context.Background(), "帮我查一下订单 ORD001 的状态")
    // LLM 调用 get_order(order_id="ORD001")
    // 返回："订单 ORD001 已发货，预计明天送达。"
}
```

---

### 2. MCP（Model Context Protocol）基础（星期二-星期三）

**MCP 是什么**
```
MCP（模型上下文协议）是一个标准化协议，让 AI 应用能够：
1. 连接到外部数据源（数据库、API、文件系统）
2. 调用外部工具（代码执行、搜索、计算）
3. 提供上下文信息给 LLM

优势：
- 标准化接口（类似 USB 协议）
- 可插拔工具（不同 MCP 服务器提供不同能力）
- 安全隔离（工具在独立进程中运行）
```

**MCP 架构**
```
┌─────────────┐
│   AI App    │ (Claude Code, Custom App)
└──────┬──────┘
       │ MCP Client
       │
   ┌───┴────┬────────┬────────┐
   │        │        │        │
┌──▼──┐  ┌─▼──┐  ┌──▼──┐  ┌──▼──┐
│ MCP │  │MCP │  │ MCP │  │ MCP │
│File │  │DB  │  │Web  │  │Custom│
│Sys  │  │    │  │Search│  │Tool  │
└─────┘  └────┘  └─────┘  └─────┘
```

**MCP 核心概念**
```go
// MCP 资源（Resources）
type Resource struct {
    URI         string      `json:"uri"`
    Name        string      `json:"name"`
    Description string      `json:"description"`
    MimeType    string      `json:"mimeType"`
}

// MCP 工具（Tools）
type Tool struct {
    Name        string      `json:"name"`
    Description string      `json:"description"`
    InputSchema JSONSchema  `json:"inputSchema"`
}

// MCP 提示（Prompts）
type Prompt struct {
    Name        string      `json:"name"`
    Description string      `json:"description"`
    Arguments   []Argument  `json:"arguments"`
}
```

**MCP Server 示例（简化）**
```go
package mcp

import (
    "context"
    "encoding/json"
)

// MCPServer 实现了 MCP 协议的服务器
type MCPServer struct {
    tools     map[string]Tool
    resources map[string]Resource
}

// ListTools 列出可用工具
func (s *MCPServer) ListTools(ctx context.Context) ([]Tool, error) {
    var tools []Tool
    for _, tool := range s.tools {
        tools = append(tools, tool)
    }
    return tools, nil
}

// CallTool 调用工具
func (s *MCPServer) CallTool(ctx context.Context, name string, arguments json.RawMessage) (json.RawMessage, error) {
    tool, ok := s.tools[name]
    if !ok {
        return nil, fmt.Errorf("tool not found: %s", name)
    }
    
    // 执行工具逻辑
    result := tool.Execute(ctx, arguments)
    return json.Marshal(result)
}

// ListResources 列出可用资源
func (s *MCPServer) ListResources(ctx context.Context) ([]Resource, error) {
    var resources []Resource
    for _, res := range s.resources {
        resources = append(resources, res)
    }
    return resources, nil
}

// ReadResource 读取资源
func (s *MCPServer) ReadResource(ctx context.Context, uri string) ([]byte, error) {
    resource, ok := s.resources[uri]
    if !ok {
        return nil, fmt.Errorf("resource not found: %s", uri)
    }
    
    return resource.Read(ctx)
}
```

**实践任务**
- [ ] 理解 MCP 的价值和应用场景
- [ ] 了解 MCP 的三个核心概念：Resources、Tools、Prompts
- [ ] （可选）尝试使用现有的 MCP 服务器

---

### 3. ReAct 模式（星期三-星期四）

**什么是 ReAct**
```
ReAct = Reasoning（推理） + Acting（行动）

传统 LLM：
  思考 → 回答

ReAct Agent：
  思考 → 行动 → 观察 → 思考 → 行动 → ... → 最终回答
```

**ReAct 循环示例**
```
用户："帮我订一张明天去上海的机票"

Thought 1: 我需要先查询明天去上海的航班
Action 1: search_flights(from="北京", to="上海", date="2024-07-04")
Observation 1: 找到 5 个航班，最早 08:00，最晚 20:00

Thought 2: 用户没说具体时间，我应该询问
Action 2: ask_user("您希望几点的航班？")
Observation 2: 用户回复 "上午"

Thought 3: 那就选择 08:00 的航班
Action 3: book_flight(flight_id="CA1234")
Observation 3: 订票成功，订单号 ORD789

Thought 4: 我已经完成任务，可以告诉用户了
Final Answer: 已为您预订明天 08:00 北京飞上海的 CA1234 航班，订单号 ORD789。
```

**实现 ReAct Agent**
```go
package agent

import (
    "context"
    "fmt"
    "strings"
)

type ReactAgent struct {
    client   *openai.Client
    executor *ToolExecutor
    tools    []Tool
}

// Run 运行 ReAct 循环
func (a *ReactAgent) Run(ctx context.Context, task string) (string, error) {
    systemPrompt := `你是一个 ReAct Agent。对于每个任务，你需要：
1. Thought: 思考下一步该做什么
2. Action: 决定调用哪个工具
3. Observation: 观察工具返回的结果
4. 重复上述过程，直到得出最终答案

可用工具：
%s

格式：
Thought: [你的思考]
Action: tool_name(arg1="value1", arg2="value2")
Observation: [工具返回结果]
... (重复)
Final Answer: [最终答案]`

    toolsDesc := a.getToolsDescription()
    systemPrompt = fmt.Sprintf(systemPrompt, toolsDesc)
    
    messages := []Message{
        {Role: "system", Content: systemPrompt},
        {Role: "user", Content: task},
    }
    
    scratchpad := ""
    
    for i := 0; i < 10; i++ {  // 最多 10 步
        // 调用 LLM
        response, err := a.callLLM(ctx, messages, scratchpad)
        if err != nil {
            return "", err
        }
        
        scratchpad += response + "\n"
        
        // 解析响应
        if strings.Contains(response, "Final Answer:") {
            // 提取最终答案
            parts := strings.Split(response, "Final Answer:")
            return strings.TrimSpace(parts[1]), nil
        }
        
        // 提取 Action
        action := extractAction(response)
        if action == "" {
            continue
        }
        
        // 执行 Action
        observation, err := a.executeAction(ctx, action)
        if err != nil {
            observation = fmt.Sprintf("Error: %v", err)
        }
        
        scratchpad += fmt.Sprintf("Observation: %s\n", observation)
    }
    
    return "", fmt.Errorf("达到最大步数限制")
}

func (a *ReactAgent) getToolsDescription() string {
    var desc strings.Builder
    for _, tool := range a.tools {
        desc.WriteString(fmt.Sprintf("- %s: %s\n", tool.Name, tool.Description))
    }
    return desc.String()
}

func extractAction(response string) string {
    // 解析 "Action: tool_name(args)" 格式
    lines := strings.Split(response, "\n")
    for _, line := range lines {
        if strings.HasPrefix(line, "Action:") {
            return strings.TrimSpace(strings.TrimPrefix(line, "Action:"))
        }
    }
    return ""
}

func (a *ReactAgent) executeAction(ctx context.Context, action string) (string, error) {
    // 解析 action 字符串并执行
    // 例如: "get_weather(city=\"北京\")"
    // 提取工具名和参数，然后调用 executor
    toolName, args := parseAction(action)
    return a.executor.Execute(ctx, toolName, args)
}
```

---

### 4. 完整 Agent 实战（星期四-星期五）

**多工具 Agent 示例**
```go
package main

import (
    "context"
    "fmt"
)

func main() {
    // 初始化工具执行器
    executor := &ToolExecutor{
        orderRepo:   orderRepo,
        weatherAPI:  weatherAPI,
        calculator:  calculator,
    }
    
    // 创建 Agent
    agent := NewAgent("your-api-key", executor)
    
    // 示例 1：单工具调用
    answer, _ := agent.Run(context.Background(), 
        "北京今天温度多少？")
    fmt.Println(answer)
    // 输出：北京今天气温 15°C。
    
    // 示例 2：多工具链式调用
    answer, _ = agent.Run(context.Background(), 
        "如果北京今天温度高于 20 度，帮我计算 25 * 4")
    fmt.Println(answer)
    // LLM 会先调用 get_weather，然后根据结果决定是否调用 calculator
    
    // 示例 3：复杂任务
    answer, _ = agent.Run(context.Background(), 
        "查询订单 ORD001，如果已发货，告诉我预计送达时间")
    fmt.Println(answer)
    // LLM 调用 get_order，然后基于订单状态生成答案
}
```

**带记忆的 Agent**
```go
type ConversationalAgent struct {
    agent   *Agent
    history []Message
}

func (a *ConversationalAgent) Chat(ctx context.Context, userMessage string) (string, error) {
    // 添加用户消息到历史
    a.history = append(a.history, Message{
        Role:    "user",
        Content: userMessage,
    })
    
    // 调用 Agent
    answer, err := a.agent.RunWithHistory(ctx, a.history)
    if err != nil {
        return "", err
    }
    
    // 添加 AI 回复到历史
    a.history = append(a.history, Message{
        Role:    "assistant",
        Content: answer,
    })
    
    return answer, nil
}

// 使用示例
func main() {
    agent := NewConversationalAgent()
    
    // 对话 1
    agent.Chat(ctx, "北京今天天气怎么样？")
    // 回答："北京今天 15°C，晴天。"
    
    // 对话 2（有上下文）
    agent.Chat(ctx, "那明天呢？")
    // Agent 知道"那"指的是北京天气
    // 回答："北京明天 18°C，多云。"
}
```

---

## 二、本周实战任务

### 任务：构建多功能 Agent（星期五-星期日）

**功能需求**
1. 天气查询
2. 计算器
3. 订单查询
4. 支持多轮对话

**实现要求**
```go
// 定义至少 3 个工具
tools := []Tool{
    GetWeatherTool,
    CalculatorTool,
    GetOrderTool,
}

// Agent 能够：
// 1. 根据用户问题自动选择工具
// 2. 链式调用多个工具
// 3. 处理工具调用失败
// 4. 保持对话上下文
```

**验收标准**
- [ ] 至少实现 3 个工具
- [ ] Agent 能正确判断何时调用工具
- [ ] 支持多轮对话
- [ ] 能处理复杂任务（需要多个工具配合）
- [ ] 有错误处理和兜底逻辑

---

## 三、推荐资源

### 必读文档
- [OpenAI Function Calling](https://platform.openai.com/docs/guides/function-calling)
- [MCP 官方文档](https://modelcontextprotocol.io/)
- [ReAct 论文](https://arxiv.org/abs/2210.03629)

### 推荐阅读
- [LangChain Agents](https://python.langchain.com/docs/modules/agents/)
- [Building Effective Agents](https://www.anthropic.com/research/building-effective-agents)

---

## 四、本周复盘模板

```markdown
### 第 20 周复盘

**这周学了什么**
- Tool Calling 工作原理
- MCP 协议基础
- ReAct 循环实现

**这周做了什么**
- 实现了天气、计算器、订单查询 3 个工具
- 构建了能链式调用工具的 Agent
- 支持了多轮对话

**真正掌握了什么**
- 理解了 Agent 如何自主决策调用工具
- 会定义 Tool Schema
- 能实现完整的 Agent 循环

**下周怎么调整**
- 学习多 Agent 协作
- 掌握需求拆解能力
```

---

## 五、下周预告

**第二十一周：多 Agent 与需求拆解**
- 主管 Agent / 子 Agent 分工
- 任务分解方法
- 从需求到 PRD、架构、开发任务
- 实现一个项目管理 Agent
