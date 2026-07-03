# 第二十二周：Skill 化与知识沉淀

## 本周学习目标

- 掌握 Prompt 模板设计方法
- 学会整理可复用的规则文件
- 建立团队知识库体系
- 形成标准化的 AI 协作规范

---

## 一、学习内容

### 1. Prompt 模板设计（星期一-星期二）

#### 1.1 好 Prompt 的特征

**CLEAR 原则**
```
C - Clear（清晰）：目标明确、无歧义
L - Logical（逻辑）：结构化、步骤清晰
E - Examples（示例）：提供正反例
A - Attributes（属性）：定义角色、风格、约束
R - Result（结果）：明确输出格式
```

**示例对比**
```markdown
❌ 差的 Prompt：
"帮我写个登录接口"

✅ 好的 Prompt：
你是一个 Go 后端工程师，遵循 RESTful 规范。

任务：实现用户登录接口

要求：
1. 路径：POST /api/v1/auth/login
2. 请求参数：username, password
3. 返回 JWT token
4. 密码需要 bcrypt 加密验证
5. 登录失败 3 次锁定账号 15 分钟
6. 记录登录日志

输出：
- 完整的 Go 代码（包含错误处理）
- 单元测试代码
- API 文档注释
```

---

#### 1.2 Prompt 模板库

**代码生成模板**
```markdown
# Go API 接口生成模板

你是一个资深 Go 后端工程师，遵循以下规范：
- RESTful API 设计
- 分层架构（Controller/Service/Repository）
- 统一错误码和响应格式
- 完善的错误处理
- 日志记录关键操作

## 任务
实现 {功能描述}

## 规格说明
- 路径：{HTTP_METHOD} {API_PATH}
- 请求参数：{REQUEST_PARAMS}
- 响应格式：{RESPONSE_FORMAT}
- 业务规则：{BUSINESS_RULES}

## 输出要求
1. Controller 层代码
2. Service 层代码
3. Repository 层代码（如需数据库操作）
4. 请求/响应结构体定义
5. 单元测试（覆盖核心逻辑）
6. API 文档注释

## 质量标准
- 代码符合 Go 最佳实践
- 错误处理完整
- 参数校验严格
- 有必要的日志记录
- 测试覆盖关键路径
```

**代码审查模板**
```markdown
# Go 代码审查模板

你是一个严格的代码审查专家，从以下维度审查代码：

## 审查维度
1. 正确性
   - 逻辑是否正确
   - 边界条件处理
   - 并发安全

2. 可维护性
   - 代码可读性
   - 命名规范
   - 注释充分性

3. 性能
   - 是否有性能瓶颈
   - 资源使用是否合理
   - 是否有内存泄露

4. 安全性
   - SQL 注入
   - XSS/CSRF
   - 敏感信息泄露

5. 最佳实践
   - 是否符合 Go 惯用法
   - 错误处理是否正确
   - 是否有代码重复

## 输出格式
对于每个问题：
- 问题描述：[简要说明问题]
- 严重程度：[高/中/低]
- 位置：[文件名:行号]
- 修复建议：[具体的修复方案]
- 示例代码：[修复后的代码]

## 审查代码
{CODE_TO_REVIEW}
```

**需求拆解模板**
```markdown
# 需求拆解模板

你是一个资深产品经理，擅长需求分析和拆解。

## 输入
需求描述：{REQUIREMENT}

## 拆解流程
1. 理解需求背景和目标
2. 识别核心功能模块
3. 编写用户故事（User Story）
4. 定义验收标准（Acceptance Criteria）
5. 估算优先级（P0/P1/P2）

## 输出格式（JSON）
```json
{
  "title": "需求标题",
  "background": "需求背景",
  "objectives": ["目标1", "目标2"],
  "modules": [
    {
      "name": "模块名",
      "user_stories": [
        {
          "as": "作为[角色]",
          "want": "我想要[功能]",
          "so_that": "以便[价值]",
          "acceptance_criteria": [
            "验收标准1",
            "验收标准2"
          ],
          "priority": "P0"
        }
      ]
    }
  ]
}
```

## 注意事项
- 用户故事要具体、可测试
- 验收标准要明确、可量化
- 优先级要合理（P0: 核心功能，P1: 重要功能，P2: 增强功能）
```

**实践任务**
- [ ] 整理项目中常用的 3-5 个 Prompt 模板
- [ ] 将模板参数化（用占位符）
- [ ] 编写模板使用说明

---

### 2. 规则文件整理（星期二-星期三）

#### 2.1 代码规范文件

**Go 代码规范（CODING_RULES.md）**
```markdown
# Go 代码规范

## 命名规范
- 包名：小写，单词，简短（如 user, order）
- 文件名：小写，下划线分隔（如 user_service.go）
- 变量名：驼峰命名（如 userName, orderID）
- 常量：大驼峰或全大写（如 MaxRetries, API_VERSION）
- 接口：名词 + er（如 Reader, Writer, UserService）

## 项目结构
```
project/
├── cmd/                 # 入口文件
│   └── server/
│       └── main.go
├── internal/            # 私有代码
│   ├── controller/      # 控制器层
│   ├── service/         # 业务逻辑层
│   ├── repository/      # 数据访问层
│   └── model/           # 数据模型
├── pkg/                 # 可导出的公共库
├── configs/             # 配置文件
├── docs/                # 文档
└── tests/               # 测试
```

## 错误处理
```go
// ✅ 推荐
func GetUser(id int) (*User, error) {
    user, err := repo.GetByID(id)
    if err != nil {
        return nil, fmt.Errorf("get user failed: %w", err)
    }
    return user, nil
}

// ❌ 不推荐
func GetUser(id int) *User {
    user, _ := repo.GetByID(id)  // 忽略错误
    return user
}
```

## 并发安全
- 共享数据必须加锁或使用 channel
- 使用 `go run -race` 检测数据竞争
- Context 作为第一个参数传递

## 单元测试
- 测试文件命名：`xxx_test.go`
- 测试函数命名：`TestXxx`
- 表驱动测试：用于多种输入场景
- Mock 外部依赖：使用 gomock 或 testify
```

**API 设计规范（API_RULES.md）**
```markdown
# API 设计规范

## RESTful 规范
- GET：查询资源
- POST：创建资源
- PUT：完整更新资源
- PATCH：部分更新资源
- DELETE：删除资源

## URL 设计
- 使用名词复数：`/api/v1/users`
- 资源嵌套不超过 2 层：`/api/v1/users/{id}/orders`
- 使用查询参数过滤：`/api/v1/users?status=active&page=1`

## 统一响应格式
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "id": 1,
    "username": "alice"
  },
  "trace_id": "abc123"
}
```

## 统一错误码
- 1000-1999：通用错误
- 2000-2999：用户相关
- 3000-3999：订单相关
- 4000-4999：支付相关

## 分页规范
请求参数：
- page: 页码（从 1 开始）
- page_size: 每页数量（默认 20，最大 100）

响应格式：
```json
{
  "items": [...],
  "total": 100,
  "page": 1,
  "page_size": 20
}
```

## 安全规范
- 敏感接口需要认证（JWT）
- 写操作需要 CSRF 保护
- 密码等敏感字段不返回
- 接口限流（按 IP 或用户）
```

---

#### 2.2 AI 协作规范

**AI Prompt 规范（AI_PROMPT_GUIDE.md）**
```markdown
# AI 协作规范

## 基本原则
1. 明确告知角色和任务
2. 提供足够的上下文
3. 定义清晰的输出格式
4. 给出正反例
5. 约束边界条件

## 代码生成场景
```markdown
作为 Go 后端工程师，实现 {功能}。

要求：
- 遵循项目代码规范（见 CODING_RULES.md）
- 使用分层架构
- 完善错误处理
- 包含单元测试

上下文：
{相关代码或接口定义}

输出：
- 完整可运行的代码
- 必要的注释
- 单元测试
```

## 代码审查场景
```markdown
审查以下代码，重点关注：
1. 安全性（SQL 注入、XSS、越权）
2. 性能（慢查询、N+1、内存泄露）
3. 正确性（逻辑错误、边界条件）
4. 可维护性（命名、注释、重复代码）

对每个问题输出：
- 问题描述
- 严重程度（高/中/低）
- 修复建议
- 示例代码

代码：
{CODE}
```

## 需求分析场景
```markdown
作为产品经理，将以下需求转化为 PRD：

需求：{REQUIREMENT}

输出 JSON 格式（见 PRD_TEMPLATE.json）
```

## 注意事项
- 不要一次性给太多任务（分步进行）
- 提供项目相关的规范文件
- 明确不要做什么（负面约束）
- 审查 AI 输出（不盲目采纳）
```

**实践任务**
- [ ] 编写项目代码规范
- [ ] 编写 API 设计规范
- [ ] 编写 AI 协作规范

---

### 3. 团队知识库建设（星期三-星期五）

#### 3.1 知识库结构

```
knowledge-base/
├── 01-规范/
│   ├── 代码规范.md
│   ├── API 规范.md
│   ├── 数据库规范.md
│   └── Git 规范.md
├── 02-模板/
│   ├── Prompt 模板/
│   │   ├── 代码生成.md
│   │   ├── 代码审查.md
│   │   └── 需求拆解.md
│   ├── 代码模板/
│   │   ├── CRUD 接口.go
│   │   └── 单元测试.go
│   └── 文档模板/
│       ├── PRD.md
│       └── 技术方案.md
├── 03-最佳实践/
│   ├── Go 并发最佳实践.md
│   ├── 缓存使用指南.md
│   ├── 性能优化指南.md
│   └── 安全开发指南.md
├── 04-常见问题/
│   ├── 开发环境问题.md
│   ├── 部署问题.md
│   └── 性能问题排查.md
└── 05-工具使用/
    ├── Git 使用指南.md
    ├── Docker 使用指南.md
    └── AI 工具使用指南.md
```

---

#### 3.2 知识文档编写

**模板：技术方案文档**
```markdown
# {功能名称} 技术方案

## 1. 背景与目标
### 1.1 背景
{为什么要做这个功能}

### 1.2 目标
- 目标 1
- 目标 2

## 2. 技术方案
### 2.1 整体架构
```
[架构图]
```

### 2.2 核心流程
1. 步骤 1
2. 步骤 2

### 2.3 技术选型
| 模块 | 技术方案 | 理由 |
|------|----------|------|
| 后端 | Go + Gin | 高性能、并发友好 |
| 数据库 | PostgreSQL | 支持 JSON、全文搜索 |

## 3. 数据库设计
### 3.1 表结构
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

## 4. API 设计
### 4.1 创建用户
- 路径：POST /api/v1/users
- 请求：{"username": "alice"}
- 响应：{"id": 1, "username": "alice"}

## 5. 风险与应对
| 风险 | 影响 | 应对措施 |
|------|------|----------|
| 并发超卖 | 高 | 使用乐观锁 |

## 6. 排期
| 阶段 | 时间 | 产出 |
|------|------|------|
| 设计 | 1d | 技术方案 |
| 开发 | 3d | 功能完成 |
| 测试 | 1d | 测试通过 |
```

**模板：排查问题文档**
```markdown
# {问题描述}

## 问题现象
- 现象描述
- 发生时间
- 影响范围

## 排查过程
1. 检查日志
   ```
   [日志内容]
   ```

2. 查看监控指标
   - CPU: 90%
   - 内存: 8GB

3. 定位根因
   - 慢 SQL 导致连接池耗尽

## 解决方案
### 临时方案
重启服务

### 长期方案
1. 优化慢 SQL
2. 增加索引
3. 调整连接池配置

## 预防措施
- 添加 SQL 慢查询告警
- 定期审查数据库性能

## 参考资料
- [相关文档链接]
```

---

## 二、本周实战任务

### 任务：建立项目知识库（星期五-星期日）

**目标**
为你的 Go 项目建立完整的知识库

**内容清单**
- [ ] 代码规范文档
- [ ] API 设计规范
- [ ] 数据库设计规范
- [ ] Git 工作流规范
- [ ] 3-5 个 Prompt 模板
- [ ] 2-3 个代码模板
- [ ] 3-5 篇最佳实践文档
- [ ] 常见问题 FAQ

**验收标准**
- [ ] 文档结构清晰
- [ ] 内容完整可用
- [ ] 有实际示例
- [ ] 可直接用于新项目
- [ ] 团队成员能快速上手

---

## 三、推荐资源

### 文档工具
- [VitePress](https://vitepress.dev/) - 静态站点生成
- [Docusaurus](https://docusaurus.io/) - 文档网站
- [Notion](https://notion.so) - 协作文档

### 模板参考
- [Google 工程实践](https://google.github.io/eng-practices/)
- [Microsoft REST API Guidelines](https://github.com/microsoft/api-guidelines)

---

## 四、本周复盘模板

```markdown
### 第 22 周复盘

**这周学了什么**
- Prompt 模板设计方法
- 规则文件整理
- 知识库建设

**这周做了什么**
- 整理了 5 个 Prompt 模板
- 编写了 3 份规范文档
- 建立了项目知识库

**真正掌握了什么**
- 会设计高质量 Prompt
- 能编写清晰的规范文档
- 理解了知识沉淀的价值

**下周怎么调整**
- 开始企业级 AI Agent 项目开发
```

---

## 五、下周预告

**第二十三周：企业级 AI Agent 项目开发**
- Agent 编排与工具集成
- 静态分析与业务规则接入
- AI 代码审查 Agent 或数据分析 Agent
- 主链路打通
