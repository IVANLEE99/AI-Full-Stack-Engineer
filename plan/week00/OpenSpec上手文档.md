# OpenSpec 上手文档

## 1. OpenSpec 是什么

OpenSpec 是一个面向 AI 编码助手的 Spec-Driven Development（规格驱动开发）框架。

它解决的问题很直接：

- 先把这次要改什么说清楚，再让 AI 开始实现
- 把需求、规格、设计、任务拆开记录，减少上下文漂移
- 让一次变更的原因、范围、实现路径都留在仓库里，后续可追溯

它不是一个只适用于新项目的大而全流程，也不是瀑布式文档系统。OpenSpec 的设计重点是：

- 适合已有项目渐进接入
- 适合和 AI 结对开发
- 文档可以边做边修，不要求一次写死

一句话理解：**OpenSpec 是把“先对齐，再编码”这件事制度化。**

## 2. OpenSpec 的核心概念

### 2.1 specs

`specs/` 表示系统当前真实行为，是当前版本的事实基线。

这里记录的是：

- 系统现在应该做什么
- 某个能力的需求和场景
- 可验证的行为定义

重点是“做什么”，而不是“怎么实现”。

### 2.2 changes

`changes/` 表示一次正在提议的变更。

每个 change 通常是一个独立目录，里面放这次改动需要的所有材料。它不是整个系统文档，而是**这一次改动的工作区**。

### 2.3 proposal

`proposal.md` 说明：

- 为什么要做这次变更
- 范围是什么
- 预期怎么推进

它主要负责讲清楚意图和边界，不展开太多技术细节。

### 2.4 design

`design.md` 说明：

- 技术方案怎么设计
- 为什么这样选
- 可能影响哪些模块

它回答的是“怎么实现”和“为什么这么实现”。

### 2.5 tasks

`tasks.md` 是实施清单。

它会把工作拆成一条条可执行、可勾选的任务，方便 AI 或开发者逐项推进。

### 2.6 archive

当这次变更完成后，需要归档。

归档时会做两件事：

- 把本次 change 中的规格增量合并回主 `specs/`
- 把 change 移到归档区，保留历史上下文

## 3. 一个最小心智模型

你可以这样理解 OpenSpec：

- `specs/` = 当前系统真实状态
- `changes/` = 正在提议的变化
- `proposal` = 为什么改
- `specs delta` = 行为改了什么
- `design` = 技术上怎么做
- `tasks` = 具体怎么执行
- `archive` = 完成后把变化并回主规格

最常见的链路是：

`proposal -> specs -> design -> tasks -> 实现 -> archive`

不是每次都必须写得很重，但这个顺序很适合避免 AI 直接开始写错方向的代码。

## 4. 安装与初始化

### 4.1 环境要求

需要 Node.js `20.19.0` 或更高版本。

### 4.2 全局安装

```bash
npm install -g @fission-ai/openspec@latest
```

除了 `npm` 之外，文档也提到支持：

- `pnpm`
- `yarn`
- `bun`
- `nix`

### 4.3 在项目中初始化

```bash
cd your-project
openspec init
```

初始化完成后，OpenSpec 会把所需结构和命令集接入你的项目工作流。

## 5. 两类命令要分清

这是新手最容易混淆的地方。

### 5.1 终端命令

这类命令在 shell 里运行，例如：

```bash
openspec init
openspec update
openspec config profile
```

### 5.2 AI 聊天命令

这类命令是在 AI 对话里输入的 slash commands，例如：

```text
/opsx:explore
/opsx:propose add-dark-mode
/opsx:apply
/opsx:verify
/opsx:archive
```

不要把 `/opsx:*` 命令拿到终端里执行，也不要把 `openspec init` 发到聊天里当成 slash command。

## 6. 新手推荐上手流程

如果你是第一次用 OpenSpec，建议按下面这条最短路径走。

### 第 1 步：初始化项目

```bash
npm install -g @fission-ai/openspec@latest
cd your-project
openspec init
```

### 第 2 步：需求不清楚时先探索

如果你还不确定该怎么做，先在 AI 聊天里用：

```text
/opsx:explore
```

适合这些场景：

- 你只知道问题，不知道改法
- 你想先让 AI 阅读现有代码
- 你需要比较几种方案再决定

### 第 3 步：需求明确后生成提案

```text
/opsx:propose add-dark-mode
```

这一步会为本次变更生成规划材料。对于第一次使用的人来说，这是最重要的入口。

### 第 4 步：进入实现

```text
/opsx:apply
```

这一步会基于 `tasks.md` 推进实现，并逐项完成任务。

### 第 5 步：做一致性检查

```text
/opsx:verify
```

这里主要检查：

- 实现是否符合规格
- 任务是否真正完成
- 设计与代码是否一致
- 有没有遗漏项或偏差

### 第 6 步：归档变更

```text
/opsx:archive
```

当需求已经完成并确认无误后，再归档，把这次 change 合并回主规格。

## 7. 新手最需要会的命令

### 7.1 默认核心命令

#### `/opsx:explore`

先探索问题、阅读代码、讨论方案。

适合：需求不明确、代码不熟、风险不明时。

#### `/opsx:propose`

创建 change，并生成实现前的规划文档。

适合：目标已经明确，准备正式进入这次改动。

#### `/opsx:apply`

按任务清单实施代码改动。

适合：proposal/spec/design/tasks 已经基本齐备。

#### `/opsx:verify`

核对实现结果与规划文档是否一致。

适合：完成开发后做收口检查。

#### `/opsx:archive`

归档已完成变更，并更新主规格。

适合：确认需求关闭之后。

### 7.2 扩展工作流命令

README 里还提供了一组扩展命令：

- `/opsx:new`
- `/opsx:continue`
- `/opsx:ff`
- `/opsx:verify`
- `/opsx:bulk-archive`
- `/opsx:onboard`

如果要启用扩展工作流，需要先在终端执行：

```bash
openspec config profile
openspec update
```

可以先用默认核心命令，等熟悉后再切扩展流。

## 8. proposal、specs、design、tasks 分别怎么写

这是上手时最重要的理解点。

### 8.1 proposal.md 写什么

`proposal.md` 用来回答：

- 为什么要做
- 这次改动的范围边界是什么
- 预期结果是什么

它偏产品和变更意图，不需要写成技术方案文档。

### 8.2 specs/ 写什么

这里写的是**行为变化**，不是实现细节。

重点应该是：

- 新增了什么能力
- 修改了什么行为
- 删除了什么旧规则
- 哪些场景必须成立

OpenSpec 的概念文档强调，spec 应该尽量可验证，通常会用 requirement + scenario 的形式表达。

### 8.3 design.md 写什么

当改动涉及技术选型、架构调整、复杂流程时，再写 `design.md`。

一般适合记录：

- 模块改动点
- 数据流或调用链
- 关键权衡
- 为什么不用其他方案

### 8.4 tasks.md 写什么

`tasks.md` 要拆成可以执行的动作，而不是泛泛一句“完成开发”。

好的任务清单通常具有这些特征：

- 粒度适中
- 每条都能独立完成
- 可以勾选
- 能映射到真实代码工作

## 9. 推荐你这样使用 OpenSpec

### 9.1 对小改动不要过度使用

如果只是一个极小修复，比如改个错别字、改一个明显 bug，未必值得完整走一轮 OpenSpec。

OpenSpec 更适合：

- 需求有一定模糊度
- 改动跨多个模块
- 需要先统一行为定义
- 你打算让 AI 参与较多实现

### 9.2 对已有项目尤其有价值

OpenSpec 不要求你先把全项目规格补全。

它支持在现有系统上按 change 写 delta，也就是只描述这次新增、修改、删除了什么。这一点非常适合存量项目。

### 9.3 文档不是一次写死

实现过程中发现 proposal、design 或 specs 不准确，可以回头修。

它们的目的不是增加流程负担，而是帮助你在 AI 实现前后保持一致性。

## 10. 一个典型目录结构长什么样

README 给出的典型 change 目录包含：

```text
openspec/
  changes/
    add-dark-mode/
      proposal.md
      design.md
      tasks.md
      specs/
```

可以把它理解为：一次功能变更的完整上下文都集中放在这里。

## 11. 更新与维护

当 OpenSpec 升级后，除了重新安装包，还要在项目目录中更新本地工作流配置：

```bash
npm install -g @fission-ai/openspec@latest
openspec update
```

如果只升级全局包，但不在项目里执行 `openspec update`，本地命令与说明可能还是旧版本。

## 12. 本地开发命令

如果你想参与 OpenSpec 本身的开发，README 给出了这些命令：

```bash
pnpm install
pnpm run build
pnpm test
pnpm run dev
pnpm run dev:cli
```

这部分不是普通使用者上手必须掌握的内容，但对二次开发有用。

## 13. 遥测与隐私

README 提到 OpenSpec 会收集匿名使用统计，主要用于了解命令使用情况。

文档明确说明：

- 收集的是命令名和版本
- 不收集参数
- 不收集路径
- 不收集文件内容
- 不收集 PII
- CI 环境会自动禁用

如果你想手动关闭，可以设置：

```bash
export OPENSPEC_TELEMETRY=0
export DO_NOT_TRACK=1
```

## 14. 新手常见问题

### 14.1 我什么时候该先用 explore？

当你满足下面任意一种情况，就先用 `/opsx:explore`：

- 你不知道该改哪里
- 你不确定应该怎么设计
- 你想先让 AI 读代码再提方案
- 你担心 AI 一上来就写偏

### 14.2 我什么时候直接 propose？

当你已经明确：

- 要做什么
- 功能边界是什么
- 这次变更是一个独立事项

就可以直接：

```text
/opsx:propose <change-name>
```

### 14.3 每次都要写 design 吗？

不一定。

如果改动很简单，设计文档可以很轻，甚至不需要展开复杂设计。`design.md` 主要服务于复杂改动和关键技术决策。

### 14.4 archive 之前为什么还要 verify？

因为 archive 是把这次 change 合回主规格的收口动作。

如果实现和规格不一致就直接 archive，会把错误状态写进新的基线里。所以先 verify，再 archive，是更稳妥的做法。

## 15. 最短可执行清单

第一次使用时，你可以直接照着下面做：

1. 安装 OpenSpec

```bash
npm install -g @fission-ai/openspec@latest
```

2. 在项目里初始化

```bash
cd your-project
openspec init
```

3. 在 AI 聊天里先探索或直接提案

```text
/opsx:explore
```

或者：

```text
/opsx:propose your-change-name
```

4. 实施

```text
/opsx:apply
```

5. 检查

```text
/opsx:verify
```

6. 归档

```text
/opsx:archive
```

## 16. 推荐阅读顺序

如果你想继续深入，建议按这个顺序看官方文档：

1. `README.md`
2. `docs/getting-started.md`
3. `docs/overview.md`
4. `docs/concepts.md`
5. `docs/commands.md`
6. `docs/workflows.md`
7. `docs/existing-projects.md`
8. `docs/cli.md`

## 17. 总结

对初学者来说，OpenSpec 最重要的不是记住所有命令，而是建立一个正确习惯：

**在让 AI 开始实现之前，先把变更意图、行为边界和任务拆解明确下来。**

如果你只记住一条工作流，就记住这一条：

```text
/opsx:explore -> /opsx:propose -> /opsx:apply -> /opsx:verify -> /opsx:archive
```

这已经足够支撑大多数第一次上手场景。