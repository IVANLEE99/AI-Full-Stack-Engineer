# 第十八周详细学习内容:Prompt 与结构化输出

> 主题:Prompt 与结构化输出
> 目标:让你能设计稳定的 Prompt,并让 LLM 输出可被 Go 直接反序列化的 JSON。
> 原则:后端要的不是"聊天",是"稳定可解析的数据"。**把 LLM 当成一个不太靠谱但很强的函数来对待。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 会设计角色 | 掌握 `system` / `user` / `assistant` 三种角色的分工 |
| 拿到稳定 JSON | 用 JSON Mode / `response_format` 让输出必为合法 JSON |
| 反序列化到 struct | 把模型输出直接 `Unmarshal` 进 Go 结构体 |
| 会用 Function Calling | 理解 Tool/Function 定义,为第 20 周 Agent 打基础 |
| 项目产出 | 做一个"自然语言转结构化查询条件"的接口 |

---

## 二、本周关键认知

上周你让 LLM 说人话,这周你要让它"说机器话"。前端调后端接口时,期待的是固定结构的 JSON。同理,当后端把 LLM 当作一个处理步骤时,也需要它返回**结构固定、字段确定、能被 `json.Unmarshal` 吃下**的数据。

- **Prompt 是接口契约**。`system` 定角色和规则,`user` 给具体输入,`assistant` 是模型或你注入的历史回答。把规则写进 `system`,把数据放进 `user`,别混。
- **自由文本不可靠**。让模型"顺便输出个 JSON",它可能加上 ```json 代码块、加解释、加寒暄。要用 **JSON Mode**(`response_format: {"type":"json_object"}`)强制它只输出合法 JSON。
- **Function Calling(Tool Calling)** 是更强的结构化:你用 JSON Schema 描述一个"函数",模型不再返回文本,而是返回"我要调用哪个函数、参数是什么"。这既是结构化输出的高级形态,也是第 20 周 Agent 的核心。
- 拿到 JSON 只是第一步,**必须校验**。模型偶尔会漏字段、给错类型。反序列化后要判断关键字段,失败要能重试或降级。

前端对照:

| 前端习惯 | 本周对应 |
|---|---|
| 定义 TS interface 约束响应 | 用 Go struct + JSON Schema 约束模型输出 |
| 相信后端返回结构稳定 | 不能盲信模型,必须校验和兜底 |
| `JSON.parse` | Go 的 `json.Unmarshal` |
| 表单校验 | 对模型输出做字段校验 |

---

## 三、每天学习安排(7天)

### Day 1:三种角色与 Prompt 结构

一次对话的 `messages` 是一个数组,每条有 `role` 和 `content`:

```go
messages := []Message{
    {Role: "system", Content: "你是一个严谨的数据抽取助手,只输出结果,不解释。"},
    {Role: "user", Content: "帮我把这句话里的人名提出来:张三和李四去北京了。"},
}
```

- `system`:定义模型的身份、语气、规则、约束。**权重最高,写死规则放这里。**
- `user`:用户的实际输入 / 你要处理的数据。
- `assistant`:模型的历史回答;多轮对话时把它加回数组,模型才有"记忆"。

一个基础调用封装(本周反复用):

```go
package main

import (
    "bytes"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "os"
)

type Message struct {
    Role    string `json:"role"`
    Content string `json:"content"`
}

type ChatRequest struct {
    Model          string          `json:"model"`
    Messages       []Message       `json:"messages"`
    ResponseFormat *ResponseFormat `json:"response_format,omitempty"`
}

type ResponseFormat struct {
    Type string `json:"type"` // "json_object"
}

type ChatResponse struct {
    Choices []struct {
        Message Message `json:"message"`
    } `json:"choices"`
}

// callLLM 发一次非流式请求,返回模型文本
func callLLM(req ChatRequest) (string, error) {
    req.Model = "deepseek-chat"
    body, _ := json.Marshal(req)

    url := os.Getenv("LLM_BASE_URL") + "/chat/completions"
    httpReq, _ := http.NewRequest("POST", url, bytes.NewReader(body))
    httpReq.Header.Set("Content-Type", "application/json")
    httpReq.Header.Set("Authorization", "Bearer "+os.Getenv("LLM_API_KEY"))

    resp, err := http.DefaultClient.Do(httpReq)
    if err != nil {
        return "", err
    }
    defer resp.Body.Close()

    raw, _ := io.ReadAll(resp.Body)
    var cr ChatResponse
    if err := json.Unmarshal(raw, &cr); err != nil {
        return "", err
    }
    if len(cr.Choices) == 0 {
        return "", fmt.Errorf("模型无返回")
    }
    return cr.Choices[0].Message.Content, nil
}

func main() {
    out, err := callLLM(ChatRequest{
        Messages: []Message{
            {Role: "system", Content: "你是数据抽取助手,只输出结果。"},
            {Role: "user", Content: "提取人名:张三和李四去北京了。"},
        },
    })
    fmt.Println(out, err)
}
```

**Day 1 理解要点**
- 规则放 `system`,数据放 `user`,别把规则混进 `user`
- `assistant` 消息用于多轮上下文,本周先用单轮
- 这个 `callLLM` 是本周基础件,后面都基于它

---

### Day 2:写出稳定的抽取 Prompt

同样一个任务,Prompt 写法决定输出稳不稳定。目标:让模型输出一个固定结构的 JSON。

先看一版容易出问题的 Prompt,再看改进版:

```go
// 差:模型可能加解释、加代码块、字段名飘忽
badPrompt := "从这句话提取信息:我叫王五,今年28岁,是工程师。"

// 好:明确身份 + 明确输出格式 + 给示例 + 约束
goodSystem := `你是信息抽取助手。请从用户输入中抽取信息,严格按以下 JSON 格式输出,不要有任何多余文字:
{"name": "姓名", "age": 年龄数字, "job": "职业"}
如果某个字段找不到,填空字符串或 0。`
```

调用:

```go
out, _ := callLLM(ChatRequest{
    Messages: []Message{
        {Role: "system", Content: goodSystem},
        {Role: "user", Content: "我叫王五,今年28岁,是工程师。"},
    },
})
fmt.Println(out)
// 期望:{"name":"王五","age":28,"job":"工程师"}
```

写好 Prompt 的几个套路:
- **给出确切的 JSON 模板**,字段名、类型都写清楚
- **说明缺失字段怎么处理**(填空还是填默认值)
- **明确禁止多余内容**:"不要解释,不要 markdown 代码块"
- **给一两个示例**(few-shot),效果立竿见影

**Day 2 理解要点**
- 模型倾向于"多说话",要显式压制
- 把输出格式写成模板,比口头描述稳定得多
- few-shot 示例是提升稳定性最有效的手段之一

---

### Day 3:JSON Mode 强制合法 JSON

光靠 Prompt 请求"输出 JSON",模型偶尔还是会加代码块或解释。**JSON Mode** 从接口层面保证输出是合法 JSON。

```go
out, err := callLLM(ChatRequest{
    ResponseFormat: &ResponseFormat{Type: "json_object"}, // 关键
    Messages: []Message{
        {Role: "system", Content: goodSystem + "\n请以 JSON 格式输出。"},
        {Role: "user", Content: "我叫王五,今年28岁,是工程师。"},
    },
})
if err != nil {
    fmt.Println("调用失败:", err)
    return
}
fmt.Println(out) // 保证是合法 JSON,不会有 ```json 包裹
```

注意:
- 开 JSON Mode 时,多数厂商要求 Prompt 里出现 "JSON" 字样,否则报错
- 它只保证"是合法 JSON",**不保证字段符合你的 schema**,字段校验仍要自己做
- DeepSeek、GLM、OpenAI 都支持这个字段,写法一致

**Day 3 理解要点**
- JSON Mode 解决"格式合法"问题,不解决"内容正确"问题
- 开了 JSON Mode 也建议在 Prompt 里再描述一遍结构
- 拿到的还是字符串,下一步才反序列化

---

### Day 4:反序列化到 struct + 校验

拿到 JSON 字符串,`Unmarshal` 进 struct,并做校验和清洗。

```go
package main

import (
    "encoding/json"
    "fmt"
    "strings"
)

type PersonInfo struct {
    Name string `json:"name"`
    Age  int    `json:"age"`
    Job  string `json:"job"`
}

// parseAndValidate 反序列化并校验
func parseAndValidate(raw string) (*PersonInfo, error) {
    // 兜底:万一模型还是包了代码块,剥掉
    raw = strings.TrimSpace(raw)
    raw = strings.TrimPrefix(raw, "```json")
    raw = strings.TrimPrefix(raw, "```")
    raw = strings.TrimSuffix(raw, "```")
    raw = strings.TrimSpace(raw)

    var p PersonInfo
    if err := json.Unmarshal([]byte(raw), &p); err != nil {
        return nil, fmt.Errorf("JSON 解析失败: %w,原文: %s", err, raw)
    }

    // 字段校验:模型可能漏字段或给脏数据
    if p.Name == "" {
        return nil, fmt.Errorf("缺少必要字段 name")
    }
    if p.Age < 0 || p.Age > 150 {
        return nil, fmt.Errorf("age 不合理: %d", p.Age)
    }
    return &p, nil
}

func main() {
    raw := `{"name":"王五","age":28,"job":"工程师"}`
    p, err := parseAndValidate(raw)
    if err != nil {
        fmt.Println("处理失败:", err)
        return
    }
    fmt.Printf("解析成功: %+v\n", *p)
}
```

配合重试:解析失败时,把错误信息当作新的 `user` 消息发回去,让模型修正。

```go
func extractWithRetry(input string, maxRetry int) (*PersonInfo, error) {
    var lastErr error
    for i := 0; i < maxRetry; i++ {
        out, err := callLLM(ChatRequest{
            ResponseFormat: &ResponseFormat{Type: "json_object"},
            Messages: []Message{
                {Role: "system", Content: goodSystem + " 请以 JSON 输出。"},
                {Role: "user", Content: input},
            },
        })
        if err != nil {
            lastErr = err
            continue
        }
        p, err := parseAndValidate(out)
        if err == nil {
            return p, nil
        }
        lastErr = err // 解析失败,再试
    }
    return nil, fmt.Errorf("重试 %d 次仍失败: %w", maxRetry, lastErr)
}
```

**Day 4 理解要点**
- `Unmarshal` 前先做兜底清洗(剥代码块),防御性编程
- 关键字段一定要校验,不能盲信模型输出
- 失败重试是让 LLM 管线稳定的标配

---

### Day 5:Function Calling 入门

结构化的进阶形态。你用 JSON Schema 描述"工具",模型不返回文本,而是返回"要调哪个工具、参数是什么"。本质还是让模型产出结构化数据,只是格式更规范。

```go
// 工具定义
type Tool struct {
    Type     string   `json:"type"` // "function"
    Function Function `json:"function"`
}

type Function struct {
    Name        string      `json:"name"`
    Description string      `json:"description"`
    Parameters  interface{} `json:"parameters"` // JSON Schema
}

// 定义一个"查询天气"工具
weatherTool := Tool{
    Type: "function",
    Function: Function{
        Name:        "get_weather",
        Description: "查询指定城市的天气",
        Parameters: map[string]interface{}{
            "type": "object",
            "properties": map[string]interface{}{
                "city": map[string]interface{}{
                    "type":        "string",
                    "description": "城市名,如 北京",
                },
            },
            "required": []string{"city"},
        },
    },
}
```

请求时带上 `tools`,响应里会出现 `tool_calls`:

```go
type ChatRequestWithTools struct {
    Model    string    `json:"model"`
    Messages []Message `json:"messages"`
    Tools    []Tool    `json:"tools,omitempty"`
}

// 响应里 message 会带 tool_calls
type ToolCall struct {
    ID       string `json:"id"`
    Function struct {
        Name      string `json:"name"`
        Arguments string `json:"arguments"` // 注意:是 JSON 字符串
    } `json:"function"`
}
```

模型对"北京天气怎么样"会返回类似:

```json
{
  "tool_calls": [{
    "id": "call_abc",
    "function": {"name": "get_weather", "arguments": "{\"city\":\"北京\"}"}
  }]
}
```

**Day 5 理解要点**
- Function Calling 是"受 schema 约束的结构化输出",比自由 JSON 更规范
- `arguments` 是**字符串形式的 JSON**,要再 `Unmarshal` 一次
- 本周先理解定义和返回结构,第 20 周做完整的执行回填

---

### Day 6-7 实战:自然语言转结构化查询条件

做一个实用接口:前端传一句中文,后端返回结构化的查询条件 JSON,可直接拼数据库查询。比如"帮我找上海的、月薪两万以上的后端工程师"转成:

```json
{"city":"上海","min_salary":20000,"keyword":"后端工程师"}
```

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
    "strings"

    "github.com/gin-gonic/gin"
)

// 查询条件结构
type JobQuery struct {
    City      string `json:"city"`
    MinSalary int    `json:"min_salary"`
    MaxSalary int    `json:"max_salary"`
    Keyword   string `json:"keyword"`
}

const querySystem = `你是查询条件解析器。把用户的招聘搜索需求转成 JSON,格式:
{"city":"城市或空","min_salary":最低月薪数字或0,"max_salary":最高月薪数字或0,"keyword":"岗位关键词或空"}
薪资单位统一转成元。找不到的字段填空字符串或 0。只输出 JSON。`

func parseQuery(raw string) (*JobQuery, error) {
    raw = strings.TrimSpace(raw)
    raw = strings.TrimPrefix(raw, "```json")
    raw = strings.TrimPrefix(raw, "```")
    raw = strings.TrimSuffix(raw, "```")
    var q JobQuery
    if err := json.Unmarshal([]byte(strings.TrimSpace(raw)), &q); err != nil {
        return nil, err
    }
    return &q, nil
}

func handleParse(c *gin.Context) {
    var body struct {
        Text string `json:"text"`
    }
    if err := c.ShouldBindJSON(&body); err != nil || body.Text == "" {
        c.JSON(http.StatusBadRequest, gin.H{"error": "缺少 text"})
        return
    }

    out, err := callLLM(ChatRequest{
        ResponseFormat: &ResponseFormat{Type: "json_object"},
        Messages: []Message{
            {Role: "system", Content: querySystem},
            {Role: "user", Content: body.Text},
        },
    })
    if err != nil {
        c.JSON(http.StatusBadGateway, gin.H{"error": "模型调用失败"})
        return
    }

    q, err := parseQuery(out)
    if err != nil {
        c.JSON(http.StatusUnprocessableEntity, gin.H{"error": "解析失败", "raw": out})
        return
    }

    // 直接返回结构化条件,前端/下游服务可直接消费
    c.JSON(http.StatusOK, q)
}

func main() {
    r := gin.Default()
    r.POST("/parse-query", handleParse)
    r.Run(":8080")
}
```

测试:

```bash
curl -X POST localhost:8080/parse-query \
  -H 'Content-Type: application/json' \
  -d '{"text":"找上海的月薪两万以上的后端工程师"}'
# 期望: {"city":"上海","min_salary":20000,"max_salary":0,"keyword":"后端工程师"}
```

进阶(有余力):把返回的 `JobQuery` 真的拼成一条 SQL 或 GORM 查询,打通"自然语言 → 数据库查询"。

本周产出:一个稳定的 `/parse-query` 接口,输入中文,输出结构化 JSON,可被前端和下游服务直接消费。

---

## 四、本周验收清单

- [ ] 能说清 `system` / `user` / `assistant` 三种角色的分工
- [ ] 能写出让模型稳定输出固定结构 JSON 的 Prompt
- [ ] 会用 few-shot 示例提升输出稳定性
- [ ] 能用 `response_format: json_object` 开启 JSON Mode
- [ ] 能把模型输出 `Unmarshal` 进 Go struct
- [ ] 能对反序列化结果做字段校验和兜底清洗
- [ ] 能实现解析失败时的重试逻辑
- [ ] 能读懂 Function Calling 的 `tools` 定义和 `tool_calls` 返回
- [ ] `/parse-query` 接口跑通,中文转结构化 JSON 稳定
- [ ] 能说清 JSON Mode 保证"格式合法"但不保证"内容正确"

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 规则写进 user | 规则应放 system,放 user 里容易被后续输入干扰 |
| 盲信模型输出 | 必须校验字段,模型会漏字段、给错类型 |
| 忘了 JSON Mode 要求提到 JSON | 多数厂商开 JSON Mode 时 Prompt 需含 "JSON" 字样,否则报错 |
| 输出被 ```json 包裹 | 没开 JSON Mode 时常见,要做剥壳兜底 |
| `arguments` 当对象解析 | Function Calling 的 arguments 是 JSON 字符串,要二次 Unmarshal |
| 没有重试 | LLM 有随机性,单次失败不重试会导致接口不稳定 |
| temperature 太高 | 结构化任务建议调低 temperature,输出更确定 |

---

## 六、推荐资料

- **OpenAI - Structured Outputs / JSON Mode** 文档:结构化输出的权威说明
- **OpenAI - Function Calling** 文档:tools 定义与 tool_calls 流程
- **DeepSeek / GLM 开放平台 - JSON Output**:确认你用的模型支持情况
- Go 标准库 `encoding/json` 文档:tag、`omitempty`、嵌套解析

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | 三种角色 | 封装 `callLLM`,理解 role 分工 |
| Day 2 | 稳定 Prompt | 写出让模型输出固定 JSON 的 Prompt |
| Day 3 | JSON Mode | 开 `response_format` 保证合法 JSON |
| Day 4 | 反序列化 + 校验 | Unmarshal 到 struct,做校验和重试 |
| Day 5 | Function Calling | 理解 tools 定义与 tool_calls 返回 |
| Day 6 | 实战接口 | 做 `/parse-query` 中文转查询条件 |
| Day 7 | 打磨 + 提交 | 加校验兜底,测多种输入,Git 提交 |
