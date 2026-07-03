# 第二十三周：企业级 AI Agent 项目开发

## 本周学习目标

- 掌握 Agent 编排和工具集成
- 学会接入静态分析工具和业务规则
- 完成一个可演示的企业级 AI Agent 项目
- 打通核心主链路

---

## 一、项目选择

### 选项 1：AI 代码审查 Agent

**功能描述**
自动审查代码提交，检测问题并提供修复建议

**核心能力**
1. 代码静态分析（golangci-lint、gosec）
2. 安全漏洞检测（SQL 注入、XSS、越权）
3. 性能问题识别（慢查询、N+1、内存泄露）
4. 代码规范检查（命名、注释、重复代码）
5. 生成审查报告和修复建议

**技术栈**
- Go + Gin（后端服务）
- OpenAI GPT-4o（代码分析）
- golangci-lint、gosec（静态分析）
- PostgreSQL（存储审查记录）

---

### 选项 2：AI 数据分析 Agent

**功能描述**
自然语言查询数据库，生成图表和分析报告

**核心能力**
1. 自然语言转 SQL
2. 执行查询并返回结果
3. 数据可视化（图表生成）
4. 趋势分析和异常检测
5. 生成数据分析报告

**技术栈**
- Go + Gin（后端服务）
- OpenAI GPT-4o（NL2SQL）
- PostgreSQL（数据源）
- Plotly/Chart.js（可视化）

---

## 二、实现：AI 代码审查 Agent（详细版）

### 1. 系统架构（星期一）

```
用户提交代码
    ↓
GitHub Webhook / API 接口
    ↓
Code Review Agent
    ├─→ 静态分析器
    │   ├─→ golangci-lint
    │   ├─→ gosec
    │   └─→ go vet
    ├─→ AI 分析器
    │   ├─→ 安全漏洞检测
    │   ├─→ 性能问题分析
    │   └─→ 可维护性评估
    └─→ 报告生成器
        ├─→ 问题汇总
        ├─→ 修复建议
        └─→ 代码示例
    ↓
审查报告（GitHub PR Comment / 邮件）
```

---

### 2. 静态分析集成（星期一-星期二）

#### 2.1 golangci-lint 集成

```go
package analyzer

import (
    "bytes"
    "encoding/json"
    "os/exec"
)

type StaticAnalyzer struct{}

// AnalyzeWithGolangCI 使用 golangci-lint 分析代码
func (a *StaticAnalyzer) AnalyzeWithGolangCI(projectPath string) ([]Issue, error) {
    cmd := exec.Command("golangci-lint", "run", 
        "--out-format=json",
        "--issues-exit-code=0",  // 有问题也不报错
        projectPath,
    )
    
    var out bytes.Buffer
    cmd.Stdout = &out
    
    if err := cmd.Run(); err != nil {
        return nil, err
    }
    
    var result GolangCIResult
    if err := json.Unmarshal(out.Bytes(), &result); err != nil {
        return nil, err
    }
    
    // 转换为统一格式
    var issues []Issue
    for _, issue := range result.Issues {
        issues = append(issues, Issue{
            File:        issue.Pos.Filename,
            Line:        issue.Pos.Line,
            Severity:    mapSeverity(issue.Severity),
            Category:    "lint",
            Description: issue.Text,
            Linter:      issue.FromLinter,
        })
    }
    
    return issues, nil
}

type GolangCIResult struct {
    Issues []struct {
        FromLinter string `json:"FromLinter"`
        Text       string `json:"Text"`
        Severity   string `json:"Severity"`
        Pos        struct {
            Filename string `json:"Filename"`
            Line     int    `json:"Line"`
            Column   int    `json:"Column"`
        } `json:"Pos"`
    } `json:"Issues"`
}

type Issue struct {
    File        string `json:"file"`
    Line        int    `json:"line"`
    Severity    string `json:"severity"` // high/medium/low
    Category    string `json:"category"` // lint/security/performance
    Description string `json:"description"`
    Linter      string `json:"linter"`
}

func mapSeverity(s string) string {
    switch s {
    case "error":
        return "high"
    case "warning":
        return "medium"
    default:
        return "low"
    }
}
```

#### 2.2 gosec 安全扫描

```go
// AnalyzeWithGosec 使用 gosec 进行安全扫描
func (a *StaticAnalyzer) AnalyzeWithGosec(projectPath string) ([]Issue, error) {
    cmd := exec.Command("gosec", 
        "-fmt=json",
        "-no-fail",  // 有问题也不报错
        "./...",
    )
    cmd.Dir = projectPath
    
    var out bytes.Buffer
    cmd.Stdout = &out
    
    if err := cmd.Run(); err != nil {
        // gosec 可能返回非零退出码，但仍有输出
        if out.Len() == 0 {
            return nil, err
        }
    }
    
    var result GosecResult
    if err := json.Unmarshal(out.Bytes(), &result); err != nil {
        return nil, err
    }
    
    var issues []Issue
    for _, issue := range result.Issues {
        issues = append(issues, Issue{
            File:        issue.File,
            Line:        issue.Line,
            Severity:    strings.ToLower(issue.Severity),
            Category:    "security",
            Description: issue.What,
            Linter:      issue.RuleID,
        })
    }
    
    return issues, nil
}

type GosecResult struct {
    Issues []struct {
        Severity string `json:"severity"`
        RuleID   string `json:"rule_id"`
        What     string `json:"details"`
        File     string `json:"file"`
        Line     string `json:"line"`
    } `json:"Issues"`
}
```

---

### 3. AI 深度分析（星期二-星期三）

```go
package analyzer

import (
    "context"
    "fmt"
    "github.com/openai/openai-go"
)

type AIAnalyzer struct {
    client *openai.Client
}

func NewAIAnalyzer(apiKey string) *AIAnalyzer {
    return &AIAnalyzer{
        client: openai.NewClient(apiKey),
    }
}

// AnalyzeSecurity 安全漏洞深度分析
func (a *AIAnalyzer) AnalyzeSecurity(ctx context.Context, code string, file string) ([]SecurityIssue, error) {
    prompt := fmt.Sprintf(`作为安全专家，审查以下 Go 代码的安全问题。

重点关注：
1. SQL 注入（直接拼接 SQL）
2. XSS（未转义的用户输入）
3. 越权访问（缺少权限检查）
4. 敏感信息泄露（密码、token 暴露）
5. 路径遍历（文件操作未验证）

文件：%s
代码：
```go
%s
```

输出 JSON 格式：
{
  "issues": [
    {
      "line": 行号,
      "type": "sql_injection|xss|unauthorized_access|info_leak|path_traversal",
      "severity": "high|medium|low",
      "description": "问题描述",
      "exploit_scenario": "攻击场景",
      "fix_suggestion": "修复建议",
      "fixed_code": "修复后的代码"
    }
  ]
}`, file, code)

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
    
    var result struct {
        Issues []SecurityIssue `json:"issues"`
    }
    json.Unmarshal([]byte(resp.Choices[0].Message.Content), &result)
    
    return result.Issues, nil
}

// AnalyzePerformance 性能问题分析
func (a *AIAnalyzer) AnalyzePerformance(ctx context.Context, code string, file string) ([]PerformanceIssue, error) {
    prompt := fmt.Sprintf(`作为性能专家，审查以下 Go 代码的性能问题。

重点关注：
1. N+1 查询问题
2. 缺少数据库索引
3. 内存泄露（goroutine 泄露、map 不清理）
4. 低效算法（O(n²) 可优化为 O(n)）
5. 不必要的内存分配

文件：%s
代码：
```go
%s
```

输出 JSON 格式：
{
  "issues": [
    {
      "line": 行号,
      "type": "n_plus_one|missing_index|memory_leak|inefficient_algorithm|excessive_allocation",
      "severity": "high|medium|low",
      "description": "问题描述",
      "impact": "性能影响（如：慢 10 倍）",
      "fix_suggestion": "优化建议",
      "optimized_code": "优化后的代码"
    }
  ]
}`, file, code)

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
    
    var result struct {
        Issues []PerformanceIssue `json:"issues"`
    }
    json.Unmarshal([]byte(resp.Choices[0].Message.Content), &result)
    
    return result.Issues, nil
}

type SecurityIssue struct {
    Line            int    `json:"line"`
    Type            string `json:"type"`
    Severity        string `json:"severity"`
    Description     string `json:"description"`
    ExploitScenario string `json:"exploit_scenario"`
    FixSuggestion   string `json:"fix_suggestion"`
    FixedCode       string `json:"fixed_code"`
}

type PerformanceIssue struct {
    Line           int    `json:"line"`
    Type           string `json:"type"`
    Severity       string `json:"severity"`
    Description    string `json:"description"`
    Impact         string `json:"impact"`
    FixSuggestion  string `json:"fix_suggestion"`
    OptimizedCode  string `json:"optimized_code"`
}
```

---

### 4. Agent 主流程（星期三-星期四）

```go
package agent

import (
    "context"
    "fmt"
)

type CodeReviewAgent struct {
    staticAnalyzer *StaticAnalyzer
    aiAnalyzer     *AIAnalyzer
    reportGenerator *ReportGenerator
}

func NewCodeReviewAgent(apiKey string) *CodeReviewAgent {
    return &CodeReviewAgent{
        staticAnalyzer:  &StaticAnalyzer{},
        aiAnalyzer:      NewAIAnalyzer(apiKey),
        reportGenerator: &ReportGenerator{},
    }
}

// Review 审查代码
func (a *CodeReviewAgent) Review(ctx context.Context, req ReviewRequest) (*ReviewReport, error) {
    report := &ReviewReport{
        ProjectName: req.ProjectName,
        CommitHash:  req.CommitHash,
        Status:      "in_progress",
    }
    
    // 1. 静态分析
    fmt.Println("=== 阶段 1：静态分析 ===")
    
    lintIssues, err := a.staticAnalyzer.AnalyzeWithGolangCI(req.ProjectPath)
    if err != nil {
        return nil, fmt.Errorf("golangci-lint 失败: %w", err)
    }
    report.LintIssues = lintIssues
    fmt.Printf("发现 %d 个 lint 问题\n", len(lintIssues))
    
    secIssues, err := a.staticAnalyzer.AnalyzeWithGosec(req.ProjectPath)
    if err != nil {
        return nil, fmt.Errorf("gosec 失败: %w", err)
    }
    report.SecurityIssues = append(report.SecurityIssues, secIssues...)
    fmt.Printf("发现 %d 个安全问题\n", len(secIssues))
    
    // 2. AI 深度分析
    fmt.Println("\n=== 阶段 2：AI 深度分析 ===")
    
    // 获取变更的文件
    changedFiles := getChangedFiles(req.ProjectPath, req.CommitHash)
    
    for _, file := range changedFiles {
        code, _ := readFile(file.Path)
        
        // 安全分析
        secIssues, err := a.aiAnalyzer.AnalyzeSecurity(ctx, code, file.Path)
        if err != nil {
            fmt.Printf("安全分析失败 %s: %v\n", file.Path, err)
            continue
        }
        report.AISecurityIssues = append(report.AISecurityIssues, secIssues...)
        
        // 性能分析
        perfIssues, err := a.aiAnalyzer.AnalyzePerformance(ctx, code, file.Path)
        if err != nil {
            fmt.Printf("性能分析失败 %s: %v\n", file.Path, err)
            continue
        }
        report.PerformanceIssues = append(report.PerformanceIssues, perfIssues...)
    }
    
    fmt.Printf("AI 发现 %d 个安全问题\n", len(report.AISecurityIssues))
    fmt.Printf("AI 发现 %d 个性能问题\n", len(report.PerformanceIssues))
    
    // 3. 生成报告
    fmt.Println("\n=== 阶段 3：生成报告 ===")
    
    report.Status = "completed"
    report.Summary = a.generateSummary(report)
    
    // 保存报告
    if err := a.saveReport(report); err != nil {
        return nil, err
    }
    
    return report, nil
}

type ReviewRequest struct {
    ProjectName string
    ProjectPath string
    CommitHash  string
}

type ReviewReport struct {
    ProjectName       string
    CommitHash        string
    Status            string
    LintIssues        []Issue
    SecurityIssues    []Issue
    AISecurityIssues  []SecurityIssue
    PerformanceIssues []PerformanceIssue
    Summary           string
    CreatedAt         time.Time
}

func (a *CodeReviewAgent) generateSummary(report *ReviewReport) string {
    totalIssues := len(report.LintIssues) + 
                   len(report.SecurityIssues) + 
                   len(report.AISecurityIssues) + 
                   len(report.PerformanceIssues)
    
    highSeverity := countHighSeverity(report)
    
    return fmt.Sprintf(`代码审查完成

总计发现 %d 个问题，其中高危 %d 个。

问题分布：
- Lint 问题：%d
- 安全问题：%d (静态) + %d (AI)
- 性能问题：%d

建议：
%s`, totalIssues, highSeverity,
        len(report.LintIssues),
        len(report.SecurityIssues),
        len(report.AISecurityIssues),
        len(report.PerformanceIssues),
        generateRecommendations(report))
}
```

---

### 5. API 接口（星期四-星期五）

```go
package controller

import (
    "github.com/gin-gonic/gin"
)

type ReviewController struct {
    agent *CodeReviewAgent
}

// SubmitReview 提交代码审查
func (c *ReviewController) SubmitReview(ctx *gin.Context) {
    var req struct {
        ProjectName string `json:"project_name" binding:"required"`
        ProjectPath string `json:"project_path" binding:"required"`
        CommitHash  string `json:"commit_hash"`
    }
    
    if err := ctx.ShouldBindJSON(&req); err != nil {
        ctx.JSON(400, gin.H{"error": err.Error()})
        return
    }
    
    // 异步执行审查
    reviewID := generateReviewID()
    
    go func() {
        report, err := c.agent.Review(context.Background(), ReviewRequest{
            ProjectName: req.ProjectName,
            ProjectPath: req.ProjectPath,
            CommitHash:  req.CommitHash,
        })
        
        if err != nil {
            log.Printf("审查失败: %v", err)
            updateReviewStatus(reviewID, "failed", err.Error())
            return
        }
        
        saveReviewReport(reviewID, report)
    }()
    
    ctx.JSON(200, gin.H{
        "review_id": reviewID,
        "status":    "pending",
        "message":   "审查任务已提交",
    })
}

// GetReviewReport 获取审查报告
func (c *ReviewController) GetReviewReport(ctx *gin.Context) {
    reviewID := ctx.Param("id")
    
    report, err := getReviewReport(reviewID)
    if err != nil {
        ctx.JSON(404, gin.H{"error": "Report not found"})
        return
    }
    
    ctx.JSON(200, report)
}
```

---

## 三、本周实战任务

### 任务：完成 AI 代码审查 Agent（星期五-星期日）

**最小可用版本（MVP）**
- [ ] 集成 golangci-lint
- [ ] 集成 gosec
- [ ] 实现 AI 安全分析
- [ ] 实现 AI 性能分析
- [ ] 生成审查报告
- [ ] 提供 API 接口

**验收标准**
- [ ] 能成功审查一个 Go 项目
- [ ] 发现至少 3 类问题
- [ ] 报告包含修复建议
- [ ] API 接口可用
- [ ] 有完整的使用文档

---

## 四、推荐资源

### 工具
- [golangci-lint](https://golangci-lint.run/)
- [gosec](https://github.com/securego/gosec)
- [staticcheck](https://staticcheck.io/)

### 参考项目
- [CodeRabbit](https://coderabbit.ai/) - AI 代码审查
- [Sourcegraph Cody](https://sourcegraph.com/cody) - AI 编程助手

---

## 五、本周复盘模板

```markdown
### 第 23 周复盘

**这周学了什么**
- Agent 编排和工具集成
- 静态分析工具使用
- AI 代码分析实现

**这周做了什么**
- 完成 AI 代码审查 Agent
- 集成了 3 个静态分析工具
- 实现了深度安全和性能分析

**真正掌握了什么**
- 能将外部工具集成到 Agent
- 会设计 Agent 的工作流程
- 理解了企业级 Agent 的要求

**下周怎么调整**
- 最后一周，加固项目
- 准备演示和总结
```

---

## 六、下周预告

**第二十四周：上线前加固与毕业项目**
- RBAC 权限控制
- 审计日志与成本记录
- 限流、重试、降级
- 部署到测试环境
- 项目演示与总结
