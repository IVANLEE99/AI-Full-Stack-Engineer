# Week 23 Day 06：平台化交付

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把 Prompt、Skill、Workflow 三样东西打包成一套「别人拿去就能用、还能自己加功能」的可复用平台。

今天你要真正掌握这一句话：

> 平台化的核心不是「我做了一个能用的 Agent」，而是「我做了一套框架，让别人不改核心代码，只加一个 Skill 文件和一段 Prompt，就能给 Agent 加新能力」。这就是从「一个应用」升级成「一个平台」的分水岭。

如果这周你只记住一句：可复用 = 约定优于配置 + 目录即插件。把 Prompt/Skill/Workflow 抽象成资产，新增能力靠「放文件」而不是「改代码」。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解什么是「平台化」，它和「一个应用」的区别
2. 理解「约定优于配置」为什么能让平台好扩展
3. 设计平台的标准目录结构（Prompt/Skill/Workflow 各就各位）
4. 定义 Skill 的注册与发现机制（放文件即生效）
5. 把三样资产用一个统一入口串起来
6. 写一份「怎么加新 Skill」的开发者文档
7. 做一次「新增一个 Skill 不改核心代码」的实测
8. 整理交付包（代码 + 文档 + 示例）
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是「平台化」

先分清两个层次：

```text
应用（Application）：
  我写死了「客服/查询/FAQ」三个功能，能跑。
  想加第四个功能？得改核心代码。

平台（Platform）：
  我提供一套规则和骨架。
  加第四个功能？照规则放一个 Skill 文件 + 一段 Prompt 就行，核心代码一行不改。
```

| 维度 | 应用 | 平台 |
|---|---|---|
| 新增功能 | 改核心代码 | 放文件 |
| 谁能扩展 | 只有原作者 | 任何看懂约定的人 |
| 改动风险 | 每次都碰核心 | 核心稳定，只加插件 |
| 交付物 | 一坨代码 | 框架 + 约定 + 文档 |

小白重点：

> 平台化的判断标准很简单：加一个新能力，要不要改核心代码？不用改 = 平台；要改 = 只是应用。今天的目标就是做到「不改核心，只放文件」。

---

### 1.2 约定优于配置

平台好不好扩展，关键看「约定」定得清不清楚。

「约定优于配置」的意思是：只要你按约定放文件、起名字，系统自动就认得，不用你到处写配置注册。

```text
约定：
  1. 每个 Skill 是 skills/ 下的一个目录
  2. 目录里必须有 SKILL.md（说明）和 run.php（逻辑）
  3. SKILL.md 的 name 就是意图路由用的键

结果：
  你把 skills/refund/ 放进去，平台自动发现它、能路由到它。
  不用改任何注册表。
```

小白重点：

> 「约定优于配置」是几乎所有现代框架（Laravel、Next.js 的文件路由、Claude 的 Skill）的扩展哲学。约定越清晰，扩展越无脑。你的平台也要有一套清晰约定。

---

### 1.3 平台的标准目录结构

把三类资产各安其位：

```text
graduation-project/
├── composer.json
├── prompts/                  # Prompt 资产（Day 01）
│   ├── customer-service.md
│   ├── query.md
│   └── faq.md
├── skills/                   # Skill 资产（Day 02）
│   ├── order-status/
│   │   ├── SKILL.md
│   │   └── run.php
│   ├── faq-search/
│   │   ├── SKILL.md
│   │   └── run.php
│   └── ticket-create/
│       ├── SKILL.md
│       └── run.php
├── src/                      # 平台核心（稳定，不常改）
│   ├── Workflow/
│   │   ├── ChatWorkflow.php
│   │   ├── IntentRecognizer.php
│   │   └── Router.php
│   ├── Skill/
│   │   └── SkillLoader.php
│   ├── Prompt/
│   │   └── PromptManager.php
│   └── Support/
│       ├── Logger.php
│       └── SkillException.php
├── docs/
│   └── how-to-add-skill.md   # 开发者文档
└── index.php                 # 统一入口
```

小白重点：

> 目录结构本身就是文档。看到 `skills/` 下每个子目录是一个 Skill，`prompts/` 下每个 md 是一个模板，新人不用问就懂大概。这种「一眼能懂」的结构是平台化的基础。

---

### 1.4 Skill 的注册与发现机制

平台启动时自动扫描 `skills/`，谁在那谁就被注册（沿用 Day 02 的 `SkillLoader`）：

```php
<?php

declare(strict_types=1);

namespace App\Skill;

class SkillLoader
{
    private array $skills = [];

    public function __construct(private string $baseDir) {}

    /** 扫描目录，自动发现所有 Skill —— 这就是「放文件即生效」的核心 */
    public function discover(): void
    {
        foreach (glob($this->baseDir . "/*", GLOB_ONLYDIR) as $dir) {
            $skillFile = $dir . "/SKILL.md";
            if (!is_file($skillFile)) {
                continue; // 没有 SKILL.md 的目录直接跳过
            }

            $meta = $this->parseMeta($skillFile);
            $this->skills[$meta["name"]] = [
                "dir"     => $dir,
                "meta"    => $meta,
                "runFile" => $dir . "/run.php",
            ];
        }
    }

    /** 列出所有已发现的 Skill（给文档/调试用） */
    public function list(): array
    {
        return array_map(fn ($s) => $s["meta"], $this->skills);
    }

    public function run(string $name, array $params): array
    {
        if (!isset($this->skills[$name])) {
            throw new \App\Support\SkillException(
                "skill not found: {$name}",
                userHint: "暂不支持这个功能",
            );
        }

        $run = require $this->skills[$name]["runFile"];
        return $run($params);
    }

    private function parseMeta(string $file): array
    {
        // 解析 SKILL.md 头部的 name/description（Day 02 已实现）
        $content = file_get_contents($file);
        preg_match('/name:\s*(.+)/', $content, $n);
        preg_match('/description:\s*(.+)/', $content, $d);
        return [
            "name"        => trim($n[1] ?? "unknown"),
            "description" => trim($d[1] ?? ""),
        ];
    }
}
```

小白重点：

> `discover()` 是平台化的灵魂：它不硬编码任何 Skill 名字，而是「扫目录、看谁在」。所以你新放一个 `skills/refund/`，重跑一次自动就有了。核心代码完全不用动。

---

### 1.5 用统一入口把三样资产串起来

`index.php` 把 Prompt + Skill + Workflow 组装成完整平台：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Skill\SkillLoader;
use App\Prompt\PromptManager;
use App\Workflow\IntentRecognizer;
use App\Workflow\Router;
use App\Workflow\ChatWorkflow;

// 1. 装载 Skill 资产（自动发现）
$loader = new SkillLoader(__DIR__ . "/skills");
$loader->discover();

// 2. 装载 Prompt 资产
$prompts = new PromptManager(__DIR__ . "/prompts");

// 3. 组装 Workflow
$workflow = new ChatWorkflow(
    recognizer: new IntentRecognizer(),
    router: new Router(),
    loader: $loader,
    prompts: $prompts,
);

// 4. 对外提供统一的处理入口
$userInput = $argv[1] ?? "订单 SO001 到哪了";
$result = $workflow->handle($userInput);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
```

运行：

```bash
php index.php "订单 SO001 到哪了"
```

输出：

```json
{
    "ok": true,
    "intent": "order_status",
    "reply": "订单 SO001 已发货，预计明天送达"
}
```

小白重点：

> 统一入口是平台的「总装车间」：把三类零件（Prompt/Skill/Workflow）组装成整机。用的人只需要调 `$workflow->handle()`，内部怎么发现 Skill、怎么路由，他不用关心。这就是「封装」。

---

### 1.6 写开发者文档：怎么加一个新 Skill

平台的价值一半在代码，一半在文档。写一份让别人照着做就能扩展的 `docs/how-to-add-skill.md`：

```markdown
# 如何给平台新增一个 Skill

## 3 步搞定，不用改核心代码

### 第 1 步：建目录
在 `skills/` 下新建一个目录，名字用中划线，如 `skills/refund/`。

### 第 2 步：写 SKILL.md
```
---
name: refund
description: 处理退款申请，需要订单号
---

## 触发场景
用户说「退款」「我要退货」「钱没退」时使用。

## 参数
- order_id：订单号（必填）
```

### 第 3 步：写 run.php
```php
<?php
return function (array $params): array {
    if (empty($params["order_id"])) {
        throw new \App\Support\SkillException("missing order_id", userHint: "请提供订单号");
    }
    return ["ok" => true, "message" => "退款申请已提交：{$params['order_id']}"];
};
```

### 完成
重新运行 `php index.php "我要退款 SO001"`，平台会自动发现并调用你的新 Skill。
核心代码一行都不用改。

## 记得同步
- 在 `IntentRecognizer` 里补充 refund 的触发关键词
- 在 `prompts/` 里加对应的回复语气模板（可选）
```

小白重点：

> 好文档的标准：新人不问你就能照着做完。写文档时想象「一个刚入职的同事，只看这篇能不能加出一个 Skill」。这份文档就是你平台可复用性的证明。

---

### 1.7 实测「不改核心代码就能扩展」

这是验收平台化是否成功的关键动作：

```text
实测步骤：
1. 记录当前 src/ 核心代码的状态（git status 应干净）
2. 完全照 docs 文档，新增 skills/refund/
3. 补 IntentRecognizer 的关键词（这是配置层，不是核心逻辑）
4. 运行 php index.php "我要退款 SO001"
5. 确认新功能生效
6. 检查 src/Workflow/ChatWorkflow.php、SkillLoader.php 有没有被改

验收标准：
✅ 新 Skill 生效
✅ SkillLoader / ChatWorkflow 等核心文件零改动
```

小白重点：

> 「加功能不碰核心」是平台化最硬的验收标准。如果加个 Skill 还得改 `ChatWorkflow`，说明你的抽象漏了，还是应用不是平台。IntentRecognizer 加关键词属于「配置」范畴，可以接受（进阶可做成从 SKILL.md 读触发词，彻底零改动）。

---

### 1.8 整理交付包

最后把东西整理成一个能交付的整体：

```text
交付包清单
====================================
□ 可运行的平台代码（src/ + index.php）
□ 三个 Prompt 模板（prompts/）
□ 三个 Skill（skills/，含 SKILL.md + run.php）
□ 开发者文档（docs/how-to-add-skill.md）
□ README（怎么跑起来、目录说明）
□ 一个「新增 Skill」的可复现示例
□ composer.json（依赖 + autoload 配置）
```

一个交付级 README 应该包含：

```markdown
# Agent 平台

## 快速开始
composer install
php index.php "订单 SO001 到哪了"

## 目录说明
- prompts/  回复语气模板
- skills/   能力插件（放文件即生效）
- src/      平台核心（一般不用改）
- docs/     怎么扩展

## 已内置能力
- order-status：查订单状态
- faq-search：查常见问题
- ticket-create：建工单

## 扩展新能力
见 docs/how-to-add-skill.md
```

小白重点：

> 交付不是「把代码发过去」，而是「让接手的人 5 分钟能跑起来、10 分钟能加个新功能」。README + 文档 + 可复现示例，缺一不可。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

回看这周自己写的 `SkillLoader`、`ChatWorkflow`，用「平台 vs 应用」的眼光审视：

1. 加新 Skill 要不要改这些文件？
2. 目录结构别人一眼能不能看懂？
3. 有没有一个统一入口？

---

## 3. 练习任务

### 练习 1：整理标准目录结构

- 按 1.3 把 prompts/、skills/、src/、docs/ 摆好
- 确保三个 Skill、三个 Prompt 各就各位

---

### 练习 2：实现自动发现

- 完善 `SkillLoader::discover()`，扫描 `skills/` 自动注册
- 加一个 `list()` 方法，打印所有已发现的 Skill
- 运行确认三个 Skill 都被发现

---

### 练习 3：统一入口

- 写 `index.php`，组装 Prompt + Skill + Workflow
- 支持从命令行传入用户输入
- 输出统一 JSON 格式

---

### 练习 4：写开发者文档

- 照 1.6 写 `docs/how-to-add-skill.md`
- 要求：新人只看这篇就能加出一个 Skill

---

### 练习 5：实测不改核心扩展

- 照文档新增第四个 Skill（如 refund）
- 运行确认生效
- 用 `git diff` 证明 `SkillLoader` / `ChatWorkflow` 零改动
- 整理交付包 + README

---

## 4. JS/Node.js 类比

| PHP 平台化 | Node.js 类比 | 说明 |
|---|---|---|
| `skills/` 目录即插件 | Next.js 的 `pages/` 文件路由 | 放文件即生效 |
| `SkillLoader::discover()` | 插件自动加载 / glob import | 扫目录注册 |
| 约定优于配置 | 各大框架的默认约定 | 少写配置 |
| SKILL.md 头部元数据 | package.json 的字段 | 声明能力 |
| 统一入口 index.php | Express app 组装 / main.ts | 总装车间 |
| docs/how-to-add-skill | 插件开发文档 | 让别人能扩展 |
| 交付包 + README | npm 包 + README | 可复用交付物 |

---

## 5. AI Review 提问

完成练习后，把平台结构和文档贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 06：Agent 平台化交付。

请你按资深工程师标准帮我检查：

1. 我的目录结构算不算「平台化」？加新功能要不要改核心？
2. 我的「约定优于配置」定得清晰吗？别人能照着扩展吗？
3. SkillLoader 的自动发现机制有没有漏洞（比如坏目录、重名）？
4. 我的开发者文档，新人真能照着加出一个 Skill 吗？
5. 交付包还缺什么？README 够不够让人 5 分钟跑起来？
6. 用 Next.js 文件路由 / npm 包做的类比准吗？

请用中文输出：做对的地方、漏洞、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] 标准化的平台目录结构
- [✅] `SkillLoader::discover()` 自动发现机制
- [✅] 统一入口 `index.php`
- [✅] 开发者文档 `how-to-add-skill.md`
- [✅] 「不改核心新增 Skill」的实测证据
- [✅] 完整交付包 + README
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清「应用」和「平台」的区别
- [ ] 能解释「约定优于配置」以及它为何利于扩展
- [ ] 有清晰、一眼能懂的标准目录结构
- [ ] `SkillLoader` 能自动发现 `skills/` 下的所有 Skill
- [ ] 有把三类资产串起来的统一入口
- [ ] 有一份新人能照着扩展的开发者文档
- [ ] 能实测「新增一个 Skill 不改核心代码」
- [ ] 交付包完整（代码 + Prompt + Skill + 文档 + README + 示例）

---

## 8. 今日自测题

### 8.1 「应用」和「平台」最本质的区别是什么？

参考答案：

> ✅ 加一个新功能要不要改核心代码。不用改（放文件即生效）就是平台；要改核心就还只是应用。

---

### 8.2 「约定优于配置」是什么意思？对平台有什么好处？

参考答案：

> ✅ 只要按约定放文件、起名字，系统自动识别，不用到处写配置注册。好处是约定越清晰，扩展越无脑，别人不看源码也能加功能。

---

### 8.3 `SkillLoader::discover()` 为什么是平台化的灵魂？

参考答案：

> ✅ 因为它不硬编码任何 Skill 名字，而是扫目录看谁在。所以新放一个 Skill 目录，重跑一次自动就有了，核心代码一行不用动。

---

### 8.4 判断平台化是否成功的最硬标准是什么？

参考答案：

> ✅ 新增一个 Skill 时，核心文件（如 SkillLoader、ChatWorkflow）零改动。如果加功能还得改核心，说明抽象漏了，仍然是应用。

---

### 8.5 交付一套平台，除了代码还必须给什么？

参考答案：

> ✅ 至少要有 README（怎么跑起来）、开发者文档（怎么扩展）、一个可复现的新增示例。标准是接手的人 5 分钟能跑、10 分钟能加个新功能。

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
我正在进行 Week 23 Day 06：平台化交付 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 23 README](./README.md)
