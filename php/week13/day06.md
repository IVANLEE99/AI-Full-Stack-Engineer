# Week 13 Day 06：LLM Gateway 交付

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完善 Gateway 并写文档。

今天你要真正掌握这一句话：

> 「交付」不是「代码能跑」，而是「别人（包括三个月后的你自己）能读懂、能跑起来、能安全用起来」——所以今天的重点是补齐结构、配置、文档、依赖清单和一条 30 秒能跑通的 quickstart，把一堆散代码收拢成一个可交付的项目。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 明确「交付标准」到底包含什么
2. 整理最终项目结构
3. 抽出配置层（settings），别再散落硬编码
4. 补齐依赖清单（requirements.txt）
5. 写一份完整的 README（含 quickstart）
6. 把关键 curl 示例固化进文档
7. 自查一遍「交付清单」
8. 用 AI 做交付前 review
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么叫「可交付」

「能在我电脑上跑」不等于交付。交付意味着满足这几条：

| 维度 | 要求 |
|---|---|
| 结构 | 目录清晰，职责分明 |
| 配置 | 通过环境变量注入，不硬编码密钥 |
| 依赖 | 有 `requirements.txt`，一条命令装全 |
| 文档 | README 含 quickstart + 接口说明 + 错误格式 |
| 可验证 | 有 curl 示例，别人照着能跑通 |
| 安全 | `.env` 不进仓库，密钥不出现在代码里 |

小白重点：判断交付质量的黄金标准——「把项目发给一个没见过它的人，他能不能在 5 分钟内跑起来」。这也是 PHP 项目交付的同一标准（`composer install` + `.env.example` + README）。

---

### 1.2 最终项目结构

收拢成这样：

```text
llm-gateway/
├── app/
│   ├── __init__.py
│   ├── main.py            # FastAPI 实例 + 路由 + 异常处理器
│   ├── settings.py        # 配置层：读环境变量
│   ├── schemas.py         # Pydantic 请求/响应模型
│   ├── auth.py            # 鉴权依赖
│   ├── errors.py          # 统一错误响应
│   └── providers/
│       ├── __init__.py
│       ├── base.py        # provider 抽象与路由
│       ├── openai_provider.py
│       └── claude_provider.py
├── requirements.txt
├── .env.example           # 环境变量样板（不含真值）
├── .gitignore
└── README.md
```

小白重点：`app/` 是代码，根目录放配置和文档。这跟 PHP 项目「`src/` 放代码，根目录放 `composer.json` / `.env.example` / README」是一个思路。

---

### 1.3 抽出配置层 settings.py

把散落的密钥、Key 名收拢到一处：

```python
# app/settings.py
import os


class Settings:
    def __init__(self) -> None:
        # 网关自身的访问密钥
        self.gateway_api_key: str = os.getenv("GATEWAY_API_KEY", "")
        # 上游供应商密钥
        self.openai_api_key: str = os.getenv("OPENAI_API_KEY", "")
        self.claude_api_key: str = os.getenv("CLAUDE_API_KEY", "")

    def require(self, name: str, value: str) -> str:
        if not value:
            raise RuntimeError(f"缺少必需的环境变量：{name}")
        return value


settings = Settings()
```

其他文件统一 `from app.settings import settings` 使用，不再各自 `os.getenv`。

小白重点：配置层 ≈ PHP 的 `config/` + `.env` 组合。好处是「所有配置一眼看全、只改一处、方便测试替换」。

---

### 1.4 补齐 requirements.txt

```text
# requirements.txt
fastapi
uvicorn[standard]
pydantic
httpx
```

安装：

```bash
pip install -r requirements.txt
```

小白重点：`requirements.txt` ≈ PHP 的 `composer.json` 的 `require` 段。别人拿到项目，一条 `pip install -r requirements.txt` 就能装齐依赖。生产环境建议把版本钉死（如 `fastapi==0.115.0`），这里入门先不锁。

---

### 1.5 .env.example 与 .gitignore

`.env.example`（提交进仓库，只给字段名，不给真值）：

```text
GATEWAY_API_KEY=your-gateway-key-here
OPENAI_API_KEY=sk-...
CLAUDE_API_KEY=sk-ant-...
```

`.gitignore`（确保真 `.env` 不进仓库）：

```text
.env
__pycache__/
*.pyc
.venv/
```

小白重点：`.env.example` 告诉别人「你需要配哪些变量」，真 `.env` 永远不提交。这跟 PHP 项目的 `.env.example` + `.gitignore` 完全一致。密钥进仓库是最常见也最严重的安全事故。

---

### 1.6 写完整 README

README 骨架：

```markdown
# llm-gateway

统一的多模型 LLM 网关，对外暴露一个 /chat 接口，内部路由到 OpenAI / Claude。

## 特性
- 多模型统一入口（gpt-4o-mini / claude-3-5-sonnet）
- Pydantic 请求校验
- Bearer Token 鉴权
- 统一错误格式

## 快速开始
1. 安装依赖：`pip install -r requirements.txt`
2. 复制配置：`cp .env.example .env`，填入密钥
3. 启动：`uvicorn app.main:app --reload`
4. 探活：`curl http://127.0.0.1:8000/health`

## 环境变量
| 变量 | 说明 |
|---|---|
| GATEWAY_API_KEY | 调用本网关所需的密钥 |
| OPENAI_API_KEY | OpenAI 上游密钥 |
| CLAUDE_API_KEY | Claude 上游密钥 |

## 接口
（贴 Day 05 写好的 /health 和 /chat 说明 + curl 示例）

## 错误格式
统一为 `{"error":{"type","message","status"}}`
```

小白重点：README 的灵魂是「快速开始」那 4 步——它决定别人能不能 30 秒跑起来。字段表和 curl 示例紧随其后。

---

### 1.7 交付前 quickstart 自测

假装自己是第一次见这个项目的人，严格照 README 走一遍：

```bash
pip install -r requirements.txt
cp .env.example .env
# 编辑 .env 填入密钥
uvicorn app.main:app --reload
curl http://127.0.0.1:8000/health
```

任何一步卡住，就说明 README 有坑，回头补。

小白重点：交付质量 = README 能不能被「照抄执行」。自己走一遍是最便宜的验收。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

对照 1.2 的结构，逐个文件确认职责单一：

| 文件 | 唯一职责 | 检查点 |
|---|---|---|
| `main.py` | 组装应用 | 只挂路由和异常处理器，不写业务细节 |
| `settings.py` | 读配置 | 所有 `os.getenv` 都收在这 |
| `schemas.py` | 定义数据形状 | 请求/响应模型 |
| `auth.py` | 鉴权 | 只管验 Key |
| `providers/` | 对接上游 | 每个供应商一个文件 |

---

## 3. 练习任务

### 练习 1：整理项目结构

按 1.2 把文件归位，确保 `import` 都能跑通。

目标：目录清晰，`uvicorn app.main:app` 能启动。

---

### 练习 2：抽出 settings.py

按 1.3 把散落的 `os.getenv` 收拢到配置层，其他文件改为引用 `settings`。

目标：配置集中管理。

---

### 练习 3：补 requirements.txt 与 .env.example

按 1.4、1.5 写好依赖清单、配置样板和 `.gitignore`。

目标：别人一条命令装依赖，知道要配哪些变量。

---

### 练习 4：写完整 README

按 1.6 写出含 quickstart、环境变量表、接口说明、错误格式的 README。

目标：README 可被照抄执行。

---

### 练习 5：quickstart 自测

按 1.7 假装新人，严格照 README 跑一遍并记录卡点。

目标：确认交付可用。

---

## 4. JS/Node.js 类比

| llm-gateway | Node 项目类比 | PHP 项目类比 |
|---|---|---|
| `requirements.txt` | `package.json` | `composer.json` |
| `pip install -r ...` | `npm install` | `composer install` |
| `.env.example` | `.env.example` | `.env.example` |
| `settings.py` | `config/index.js` | `config/` |
| `uvicorn app.main:app` | `node server.js` | `php -S` / php-fpm |
| README quickstart | README | README |

---

## 5. AI Review 提问

把项目结构、README、settings.py 贴给 AI，然后问：

```text
我正在学习 FastAPI Day 06：把 llm-gateway 整理成可交付项目。

请你按资深后端工程师标准帮我检查：

1. 我的项目结构职责划分合理吗？
2. 配置层 settings.py 有没有遗漏或安全问题（密钥是否可能进仓库）？
3. 我的 README quickstart 能否让新人 5 分钟跑起来？
4. requirements.txt / .env.example / .gitignore 是否齐全正确？
5. 交付前还差什么（版本锁定、日志、健康检查细化）？

请用中文输出：交付清单缺口、安全隐患、下一步。
```

---

## 6. 今日产出

- [ ] 整理后的项目结构
- [ ] 配置层 `settings.py`
- [ ] `requirements.txt`
- [ ] `.env.example` 与 `.gitignore`
- [ ] 完整 README（quickstart + 接口 + 错误格式）
- [ ] quickstart 自测记录
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 可切换 2 模型（gpt-4o-mini / claude-3-5-sonnet）
- [ ] 项目结构清晰，职责分明
- [ ] 密钥全部走环境变量，代码里无硬编码密钥
- [ ] `.env` 不进仓库（在 `.gitignore` 中）
- [ ] `pip install -r requirements.txt` 能装齐依赖
- [ ] README 含可照抄的 quickstart
- [ ] 按 README 从零能把服务跑起来

---

## 8. 今日自测题

### 8.1 「能跑」和「可交付」的区别？

参考答案：

> ✅ 「能跑」只是我这台机器能启动；「可交付」是别人拿到项目，照 README 就能装依赖、配环境、跑起来，且不含安全隐患。差别在结构、配置、依赖清单、文档、安全。

---

### 8.2 为什么要抽 settings.py？

参考答案：

> ✅ 把散落的配置读取集中到一处，一眼看全、只改一处、方便测试替换，也避免密钥硬编码。类似 PHP 的 `config/` + `.env`。

---

### 8.3 `.env` 和 `.env.example` 的分工？

参考答案：

> ✅ `.env.example` 只给字段名、提交进仓库，告诉别人要配哪些变量；真 `.env` 含真实密钥、写进 `.gitignore` 永不提交。

---

### 8.4 requirements.txt 对应 PHP 里的什么？

参考答案：

> ✅ 对应 `composer.json` 的 `require` 段。`pip install -r requirements.txt` 对应 `composer install`。

---

### 8.5 判断 README 质量的最简单办法？

参考答案：

> ✅ 假装自己是没见过项目的新人，严格照 quickstart 一步步执行，看能不能跑起来。卡在哪，README 就该补哪。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 13 Day 06：LLM Gateway 交付 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 13 README](./README.md)
