# Week 13 Day 05：类比日与测试

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

curl 测全部接口，完成打卡。

今天你要真正掌握这一句话：

> 「类比日」不是学新东西，而是把这周学的 FastAPI 和你之前会的 PHP/Express 摆在一起对照，用 curl 把每个接口的「正常路径 + 出错路径」都跑一遍，确认它们真的按你以为的方式工作——能把接口讲清楚、测明白，才算真的掌握。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾本周做出的接口清单
2. 建立「三语对照」：PHP(Yii2/TP) ↔ Express ↔ FastAPI
3. 系统性 curl 测试每个接口的正常路径
4. 系统性 curl 测试每个接口的出错路径
5. 把测试结果记成表格
6. 用 Swagger UI 交互式验证
7. 写一份接口 README（供别人调用）
8. 完成本周打卡
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 本周接口清单

到今天，`llm-gateway` 应该有这些接口：

| 方法 | 路径 | 鉴权 | 作用 |
|---|---|---|---|
| GET | `/health` | 否 | 探活 |
| POST | `/chat` | 是 | 多模型对话，带请求校验 |

小白重点：接口不多，但每个都要经得起「正常 + 各种异常」的推敲。类比日的价值就在于系统性地把它们过一遍。

---

### 1.2 三语对照：PHP ↔ Express ↔ FastAPI

你现在同时会三套后端范式，把它们摆一起，理解会更牢：

| 概念 | PHP (Yii2/TP) | Express (Node) | FastAPI (Python) |
|---|---|---|---|
| 定义路由 | 路由配置 / 注解 | `app.get('/x', fn)` | `@app.get("/x")` |
| 读取 JSON body | `$request->post()` | `req.body` | Pydantic model 参数 |
| 请求校验 | 表单验证器 rules | Zod / Joi | Pydantic |
| 鉴权 | 中间件 / behaviors | 中间件 | `Depends(...)` |
| 抛业务错误 | 抛异常 / 返回错误码 | `res.status().json()` | `HTTPException` |
| 全局错误处理 | 异常处理组件 | 错误中间件 | `@app.exception_handler` |
| 自动文档 | 需额外配置(Swagger) | 需额外配置 | 内置 `/docs` |
| 异步 | 通常同步 | 天生异步 | `async def` |

小白重点：三者「形状」高度一致——都是路由 + 校验 + 鉴权 + 错误处理。学会迁移这套心智模型，换框架只是换语法。

---

### 1.3 系统测试：正常路径

`/health`：

```bash
curl -i http://127.0.0.1:8000/health
```

预期：200，`{"status":"ok"}`。

`/chat`（带对的 Key）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer sk-gateway-demo-123" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"你好"}]}'
```

预期：200（若真连上游），返回 `{"model":..., "reply":...}`。

---

### 1.4 系统测试：出错路径

出错路径才是接口质量的试金石。逐个测：

无 Key（预期 401）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}]}'
```

错 Key（预期 401）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer wrong" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}]}'
```

messages 为空（预期 422）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer sk-gateway-demo-123" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[]}'
```

未知 model（预期 400）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer sk-gateway-demo-123" \
  -H "Content-Type: application/json" \
  -d '{"model":"no-such-model","messages":[{"role":"user","content":"hi"}]}'
```

非法 role（预期 422）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer sk-gateway-demo-123" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"boss","content":"hi"}]}'
```

小白重点：`curl -i` 的 `-i` 会打印响应头，能看到状态码。测出错路径时，重点确认「状态码对不对」和「错误体是不是统一的 `{"error":{...}}`」。

---

### 1.5 把测试结果记成表格

边测边填这张表，这就是你的「测试记录」产出：

| # | 场景 | 命令要点 | 预期码 | 实际码 | 响应体是否统一 | 通过 |
|---|---|---|---|---|---|---|
| 1 | health 探活 | GET /health | 200 | | - | |
| 2 | chat 正常 | 对 Key + 合法体 | 200 | | - | |
| 3 | 无 Key | 不带 Authorization | 401 | | ✓/✗ | |
| 4 | 错 Key | Bearer wrong | 401 | | ✓/✗ | |
| 5 | messages 空 | `"messages":[]` | 422 | | ✓/✗ | |
| 6 | 未知 model | no-such-model | 400 | | ✓/✗ | |
| 7 | 非法 role | role=boss | 422 | | ✓/✗ | |

小白重点：这张表就是「我的接口经过验证」的证据。以后交付、面试、复盘都能拿出来。

---

### 1.6 用 Swagger UI 交互验证

浏览器打开：

```text
http://127.0.0.1:8000/docs
```

在 `/chat` 上点 Authorize（或直接在请求里带 header），填 body，点 Execute。对照你 curl 的结果。

小白重点：Swagger 适合快速点测和给别人演示，curl 适合脚本化和记录。两者互补。PHP 项目要看到这种自动文档通常得额外接 Swagger 组件，FastAPI 免费送。

---

### 1.7 写接口 README

给「调用你网关的人」写一份最小 README（`README.md` 里的接口章节）：

```markdown
## 接口

### GET /health
探活，无需鉴权。返回 `{"status":"ok"}`。

### POST /chat
多模型对话。需鉴权：请求头 `Authorization: Bearer <GATEWAY_API_KEY>`。

请求体：
| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| model | string | 是 | gpt-4o-mini / claude-3-5-sonnet |
| messages | array | 是 | 至少 1 条，每条含 role/content |
| temperature | float | 否 | 0~2，默认 0.7 |

curl 示例：
（贴一条正常请求的 curl）

错误格式：统一为 `{"error":{"type","message","status"}}`
```

小白重点：好文档的核心是「让别人不用问你就能调通」。写清鉴权方式、字段表、一条能直接跑的 curl、错误格式，就够了。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

回看你本周写的全部文件，确认它们协同工作：

- `app/main.py`：路由 + 异常处理器
- `app/schemas.py`：Pydantic 请求/响应模型
- `app/auth.py`：鉴权依赖
- `app/providers/`：OpenAI/Claude 适配

在笔记里画一张「一次 /chat 请求的完整流转图」：

```text
请求 → 鉴权(Depends) → 请求校验(Pydantic) → 路由到 provider → 调上游 → 组装响应
  ↘ 任一步出错 → 异常处理器 → 统一错误格式
```

---

## 3. 练习任务

### 练习 1：跑完 7 条测试

按 1.3、1.4 把 7 个场景全部 curl 一遍。

目标：每条都拿到响应。

---

### 练习 2：填测试记录表

按 1.5 把实际状态码、响应体是否统一、是否通过填全。

目标：产出可交付的测试记录。

---

### 练习 3：三语对照表

按 1.2 补全 PHP ↔ Express ↔ FastAPI 对照，并各挑一行用自己的话解释。

目标：把三套范式在脑中打通。

---

### 练习 4：Swagger 交互测试

在 `/docs` 里点测 `/chat` 的正常和一个出错场景，截图或记录。

目标：会用自动文档做验证。

---

### 练习 5：写接口 README 章节

按 1.7 写出 `/health` 和 `/chat` 的接口说明，含字段表和一条可运行 curl。

目标：产出「别人能照着调」的文档。

---

## 4. JS/Node.js 类比

| FastAPI | Express 类比 | 说明 |
|---|---|---|
| `@app.get/post` | `app.get/post` | 路由 |
| Pydantic 参数 | Zod 中间件 | 请求校验 |
| `Depends` | 中间件 | 鉴权 |
| `HTTPException` | `res.status().json()` | 业务错误 |
| `/docs` 自动生成 | 需接 swagger-ui-express | 交互文档 |
| `curl -i` | `curl -i` / Postman | 接口测试 |

---

## 5. AI Review 提问

完成测试后，把测试记录表和接口 README 贴给 AI，然后问：

```text
我正在学习 FastAPI Day 05：系统测试 llm-gateway 的全部接口并写文档。

请你按资深后端工程师标准帮我检查：

1. 我的测试场景覆盖全了吗？还漏了哪些边界（超长输入、并发、超时）？
2. 每个场景的预期状态码是否合理？
3. 我的接口 README 够不够让别人不问我就能调通？
4. 我做的 PHP ↔ Express ↔ FastAPI 三语对照有没有错误？
5. 交付前还应该补哪些测试？

请用中文输出：覆盖漏洞、文档改进、下一步。
```

---

## 6. 今日产出

- [ ] 7 条 curl 测试全部执行
- [ ] 测试记录表（含实际码、是否通过）
- [ ] PHP ↔ Express ↔ FastAPI 三语对照表
- [ ] Swagger 交互测试记录
- [ ] 接口 README 章节
- [ ] 本周打卡完成
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 全部接口通过（正常路径返回预期结果）
- [ ] 全部出错路径返回正确状态码
- [ ] 所有错误体都是统一 `{"error":{...}}` 格式
- [ ] 能用 curl 和 Swagger 两种方式测接口
- [ ] 完成测试记录表
- [ ] 完成接口 README
- [ ] 能讲清 PHP / Express / FastAPI 的对应关系

---

## 8. 今日自测题

### 8.1 类比日的目的是什么？

参考答案：

> ✅ 不学新东西，而是把本周的 FastAPI 与已会的 PHP/Express 对照，并系统 curl 测试全部接口的正常和出错路径，确认它们真按预期工作。

---

### 8.2 为什么出错路径比正常路径更值得测？

参考答案：

> ✅ 正常路径大家都会走，出错路径（无 Key、空 messages、未知 model、非法 role）才暴露接口的健壮性。状态码对不对、错误格式统不统一，都在这里检验。

---

### 8.3 curl 的 `-i` 有什么用？

参考答案：

> ✅ 打印响应头，能看到 HTTP 状态码，方便确认接口返回的是 200 / 401 / 422 / 400 还是 500。

---

### 8.4 FastAPI 相比 PHP/Express 在文档上的优势？

参考答案：

> ✅ FastAPI 内置 `/docs`(Swagger) 自动文档，不用额外配置。PHP/Express 通常要手动接 Swagger 组件。

---

### 8.5 一份好的接口 README 至少要有什么？

参考答案：

> ✅ 鉴权方式、字段表（名/类型/必填/说明）、一条可直接运行的 curl 示例、统一的错误格式说明——让别人不问你就能调通。

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
我正在进行 Week 13 Day 05：类比日与测试 的学习。
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
