# Week 23 Day 03：Workflow 设计

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

设计并实现一个「意图识别 → 路由」的 Workflow：用户说一句话，先判断他想干什么（意图），再路由到对应的 Skill 处理。

今天你要真正掌握这一句话：

> Workflow 就是把「意图识别 → 参数抽取 → 路由到 Skill → 组织回复」这几步串成一条固定的流水线；意图识别相当于「总机接线员」，负责把用户转接到正确的「分机」（Skill）。

如果这周你只记住一件事：Agent 不是一次调用就完事，而是「一条有明确步骤的流水线」，而路由是这条流水线的第一个关键岔路口。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解什么是 Workflow，为什么要固定步骤
2. 理解「意图识别」是干什么的
3. 画出这条 Workflow 的流程图
4. 定义意图枚举和路由表
5. 实现一个「关键词版」意图识别（先不依赖大模型，方便测试）
6. 实现路由器：把意图接到对应 Skill
7. 把昨天的 SkillLoader 接进来
8. 跑通「用户一句话 → 最终回复」的完整链路
9. 处理「识别不出意图」的兜底分支
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是 Workflow，为什么要固定步骤

Workflow（工作流）就是把一件事拆成固定的几步，按顺序执行。

不用 Workflow 时，代码常常是一坨 if-else 堆在一起，改一处牵动全身。用了 Workflow，每一步职责单一、可单独测试、可替换。

我们今天的 Workflow 是：

```text
用户输入
   │
   ▼
[1] 意图识别（这句话想干嘛？）
   │
   ▼
[2] 参数抽取（订单号是多少？重量多少？）
   │
   ▼
[3] 路由（根据意图找到对应 Skill）
   │
   ▼
[4] 执行 Skill（拿到结构化结果）
   │
   ▼
[5] 组织回复（套 Prompt 模板，说人话）
   │
   ▼
最终回复
```

小白重点：

> Workflow 的价值是「可预测」。每一步做什么、输出什么都固定，出问题时你能精确定位到是哪一步坏了，而不是在一大坨代码里瞎找。

---

### 1.2 意图识别是干什么的

「意图（intent）」就是「用户到底想干什么」。

同一件事用户有一百种说法：

```text
"我的订单到哪了" 
"发货了没"       
"订单 SO001 什么状态"   → 都是意图：查订单状态（order_status）

"寄一件多少钱"
"运费怎么算"           → 都是意图：算运费（shipping_fee）

"快递到哪了"
"物流信息"             → 都是意图：查物流（logistics_track）
```

意图识别就是把这一百种说法，归类到有限的几个「意图标签」上。

小白重点：

> 意图识别 = 把「自然语言的乱」收敛成「有限的、确定的标签」。有了标签，后面的路由才有依据。这一步是 Agent 从「聊天」走向「办事」的关键转折。

---

### 1.3 画出流程图并定义意图枚举

先用 PHP 8.1 的枚举把意图固定下来：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

enum Intent: string
{
    case OrderStatus = "order_status";
    case ShippingFee = "shipping_fee";
    case Logistics = "logistics_track";
    case Unknown = "unknown"; // 识别不出时的兜底
}
```

小白重点：

> 用枚举而不是「随手写字符串」，是因为枚举能防止拼写错误（写错了 IDE 会报错），也让所有可能的意图一目了然。这是「工程化」和「随手写」的分水岭。

---

### 1.4 意图与 Skill 的路由表

路由表就是「意图 → Skill 名字」的对应关系：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

class Router
{
    /** 意图 → Skill 名字 */
    private const ROUTES = [
        "order_status"    => "order-status",
        "shipping_fee"    => "shipping-fee",
        "logistics_track" => "logistics-track",
    ];

    public function resolveSkill(Intent $intent): ?string
    {
        return self::ROUTES[$intent->value] ?? null;
    }
}
```

对比表：

| 意图 Intent | 路由到的 Skill | 用途 |
|---|---|---|
| order_status | order-status | 查订单状态 |
| shipping_fee | shipping-fee | 算运费 |
| logistics_track | logistics-track | 查物流 |
| unknown | 无 | 走兜底回复 |

JS/Node 类比：

```js
// 这就像 Express 的路由表
const routes = {
  "order_status": orderStatusHandler,
  "shipping_fee": shippingFeeHandler,
};
```

小白重点：

> 路由表把「判断」和「执行」解耦：意图识别只管产出标签，路由表只管标签到 Skill 的映射。以后加新意图，只需在表里加一行。

---

### 1.5 实现「关键词版」意图识别

真实系统会用大模型做意图识别，但今天我们先用「关键词匹配」做一个不依赖网络、方便测试的版本。等链路跑通，再换成大模型也不影响其它步骤。

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

class IntentRecognizer
{
    /** 每个意图对应的关键词 */
    private const KEYWORDS = [
        "order_status"    => ["订单", "发货", "状态", "到哪"],
        "shipping_fee"    => ["运费", "邮费", "多少钱", "寄"],
        "logistics_track" => ["快递", "物流", "轨迹", "单号"],
    ];

    public function recognize(string $text): Intent
    {
        foreach (self::KEYWORDS as $intent => $words) {
            foreach ($words as $word) {
                if (mb_strpos($text, $word) !== false) {
                    return Intent::from($intent);
                }
            }
        }
        return Intent::Unknown;
    }
}
```

小白重点：

> 先用「假的、简单的」实现把整条流水线跑通，再逐步替换成「真的、复杂的」实现。这叫「先通再优」，是新手最该养成的习惯 —— 别一上来就死磕最难的那一步。

---

### 1.6 参数抽取

识别出意图后，还要从用户的话里抠出参数（订单号、重量等）。这里也先用简单的正则演示：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

class ParamExtractor
{
    public function extract(Intent $intent, string $text): array
    {
        return match ($intent) {
            Intent::OrderStatus => $this->extractOrderId($text),
            Intent::ShippingFee => $this->extractShipping($text),
            Intent::Logistics   => $this->extractTracking($text),
            Intent::Unknown     => [],
        };
    }

    private function extractOrderId(string $text): array
    {
        // 抓形如 SO 开头 + 数字的订单号
        if (preg_match('/SO\d+/', $text, $m)) {
            return ["order_id" => $m[0]];
        }
        return [];
    }

    private function extractShipping(string $text): array
    {
        $params = [];
        if (preg_match('/(\d+(\.\d+)?)\s*(kg|千克|公斤)/u', $text, $m)) {
            $params["weight_kg"] = (float)$m[1];
        }
        foreach (["省内", "省外", "偏远"] as $region) {
            if (mb_strpos($text, $region) !== false) {
                $params["region"] = $region;
            }
        }
        return $params;
    }

    private function extractTracking(string $text): array
    {
        if (preg_match('/YT\d+/', $text, $m)) {
            return ["tracking_no" => $m[0]];
        }
        return [];
    }
}
```

小白重点：

> 参数抽取常常抽不全（用户没说订单号）。这很正常，别指望一次到位。真实系统会「反问用户」补齐参数，今天先允许抽不全，交给 Skill 去返回「缺参数」的错误。

---

### 1.7 组装 Workflow

现在把所有步骤串起来。Workflow 类是「总指挥」，它按顺序调用各个组件：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Skill\SkillLoader;

class ChatWorkflow
{
    public function __construct(
        private IntentRecognizer $recognizer,
        private ParamExtractor $extractor,
        private Router $router,
        private SkillLoader $skills,
    ) {}

    public function handle(string $userText): array
    {
        // [1] 意图识别
        $intent = $this->recognizer->recognize($userText);

        // 兜底：识别不出
        if ($intent === Intent::Unknown) {
            return [
                "intent" => "unknown",
                "reply"  => "抱歉，我没太理解您的意思，您可以问订单状态、运费或物流哦。",
            ];
        }

        // [2] 参数抽取
        $params = $this->extractor->extract($intent, $userText);

        // [3] 路由
        $skillName = $this->router->resolveSkill($intent);
        if ($skillName === null) {
            return [
                "intent" => $intent->value,
                "reply"  => "这个功能暂时还没上线呢。",
            ];
        }

        // [4] 执行 Skill
        $result = $this->skills->run($skillName, $params);

        // [5] 组织回复
        return [
            "intent" => $intent->value,
            "skill"  => $skillName,
            "params" => $params,
            "reply"  => $this->buildReply($intent, $result),
        ];
    }

    /** 把 Skill 的结构化结果，组织成一句人话 */
    private function buildReply(Intent $intent, array $result): string
    {
        if (($result["ok"] ?? false) === false) {
            return "查询遇到点问题：" . ($result["error"] ?? "未知错误") . "。您可以补充更多信息吗？";
        }

        return match ($intent) {
            Intent::OrderStatus => "您的订单当前状态是：{$result['status']}。",
            Intent::ShippingFee => "预计运费为 {$result['fee']} 元。",
            Intent::Logistics   => "物流轨迹：" . implode(" → ", $result["traces"]) . "。",
            default             => "已为您处理完成。",
        };
    }
}
```

小白重点：

> 注意 Workflow 自己不「做事」，它只「调度」。真正干活的是 IntentRecognizer、SkillLoader 这些组件。这种「指挥者只调度、不干活」的设计，让每一步都能单独换掉、单独测试。

---

### 1.8 跑通完整链路

入口文件 `chat.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Skill\SkillLoader;
use App\Workflow\ChatWorkflow;
use App\Workflow\IntentRecognizer;
use App\Workflow\ParamExtractor;
use App\Workflow\Router;

$loader = new SkillLoader(__DIR__ . "/skills");
$loader->load();

$workflow = new ChatWorkflow(
    new IntentRecognizer(),
    new ParamExtractor(),
    new Router(),
    $loader,
);

// 模拟几句用户输入
$inputs = [
    "我的订单 SO20260707001 发货了吗",
    "寄一件 3.2kg 到省外要多少钱",
    "快递 YT1234567890 到哪了",
    "今天天气怎么样",       // 兜底
];

foreach ($inputs as $text) {
    $out = $workflow->handle($text);
    echo "用户：{$text}\n";
    echo "意图：{$out['intent']}\n";
    echo "回复：{$out['reply']}\n";
    echo str_repeat("-", 40) . "\n";
}
```

期望输出类似：

```text
用户：我的订单 SO20260707001 发货了吗
意图：order_status
回复：您的订单当前状态是：已发货。
----------------------------------------
用户：寄一件 3.2kg 到省外要多少钱
意图：shipping_fee
回复：预计运费为 16 元。
----------------------------------------
用户：快递 YT1234567890 到哪了
意图：logistics_track
回复：物流轨迹：已揽收 → 运输中 → 派送中。
----------------------------------------
用户：今天天气怎么样
意图：unknown
回复：抱歉，我没太理解您的意思，您可以问订单状态、运费或物流哦。
----------------------------------------
```

小白重点：

> 看到这四种情况都被正确处理（尤其是「天气」走了兜底），你就完成了一个真正意义上的「会路由的 Agent」。这比昨天单个 Skill 又进了一大步。

---

### 1.9 兜底分支为什么重要

「兜底」就是「所有识别/匹配都失败时的默认处理」。

新手最容易忽略兜底，结果一旦用户说了系统没预料的话，程序要么报错崩溃，要么沉默不回复，体验极差。

好的兜底应该：

1. 明确告诉用户「我没听懂」
2. 提示用户「我能做什么」（引导回正轨）
3. 绝不崩溃、绝不沉默

```php
// 兜底回复的好例子
"抱歉，我没太理解您的意思，您可以问订单状态、运费或物流哦。"

// 坏例子（用户一脸懵）
"unknown"
"错误"
（什么都不返回）
```

小白重点：

> 判断一个 Agent 是不是「工程级」，就看它怎么处理意料之外的输入。兜底做得好，用户永远不会撞见「死路」。

---

### 1.10 之后如何把关键词识别换成大模型

今天的关键词识别很脆弱（换个说法就识别不出）。真实系统会这样升级：

```text
把「所有意图 + 每个意图的描述」做成清单，连同用户的话发给大模型：

  请判断用户属于以下哪个意图，只返回意图标签：
  - order_status：查询订单状态
  - shipping_fee：计算运费
  - logistics_track：查询物流
  - unknown：都不是

  用户说：{{user_text}}
```

关键在于：因为 Workflow 各步骤解耦，你只需把 `IntentRecognizer` 换成「调用大模型的版本」，其它步骤（路由、执行、回复）一行都不用改。

小白重点：

> 这就是解耦设计的回报：今天用关键词跑通，明天换大模型无痛升级。好的架构让「换实现」变得便宜。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

如果项目里已有 Workflow 相关代码，重点观察：

1. 意图是用枚举还是字符串定义的
2. 路由表放在哪里、怎么维护
3. Workflow 是「调度者」还是自己塞满了业务逻辑
4. 兜底分支是怎么写的

---

## 3. 练习任务

### 练习 1：准备目录

在昨天的 `skill-lib` 基础上，新增 Workflow 目录：

```bash
mkdir -p skill-lib/src/Workflow
```

确保 `composer.json` 的 PSR-4 仍是 `"App\\": "src/"`，然后：

```bash
composer dump-autoload
```

---

### 练习 2：定义意图与路由

- 把 1.3 的 `Intent` 枚举放到 `src/Workflow/Intent.php`
- 把 1.4 的 `Router` 放到 `src/Workflow/Router.php`

---

### 练习 3：实现识别与抽取

- 把 1.5 的 `IntentRecognizer` 放到 `src/Workflow/IntentRecognizer.php`
- 把 1.6 的 `ParamExtractor` 放到 `src/Workflow/ParamExtractor.php`

---

### 练习 4：组装并跑通

- 把 1.7 的 `ChatWorkflow` 放到 `src/Workflow/ChatWorkflow.php`
- 用 1.8 的 `chat.php` 运行：

```bash
php chat.php
```

目标：四种输入（三个正常意图 + 一个兜底）都得到合理回复。

---

### 练习 5：加一个新意图

自己再加一个意图，比如「退换货政策」（`return_policy`），路由到一个新的 `return-policy` Skill（返回一段固定政策文本）。

体会：加一个意图，你需要改哪几个地方？

参考答案：

```text
1. Intent 枚举加一个 case
2. Router 路由表加一行
3. IntentRecognizer 关键词加一组（如「退货」「换货」）
4. ParamExtractor 一般不用改（这个意图不需要参数）
5. buildReply 加一个分支
6. 新建 skills/return-policy 这个 Skill
```

目标：亲手体会「解耦的代价是分散」—— 加功能要动几处，但每处都很小、很清晰。

---

## 4. JS/Node.js 类比

| PHP / Workflow | Node.js 类比 | 说明 |
|---|---|---|
| ChatWorkflow 流水线 | Express 中间件链 | 请求依次经过每一步 |
| Router 路由表 | Express 的 router | 按标签分发到处理器 |
| Intent 枚举 | TS 的 union 类型/enum | 固定可能的取值 |
| IntentRecognizer | 请求解析/分类中间件 | 判断请求属于哪类 |
| 兜底分支 | 404 / 默认路由 | 没匹配上时的处理 |
| SkillLoader.run | controller 调用 service | 真正执行业务 |

---

## 5. AI Review 提问

完成练习后，把你的 Workflow 代码贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 03：Workflow 设计（意图识别 → 路由）。

请你按资深工程师标准帮我检查：

1. 我把流程拆成「识别→抽取→路由→执行→回复」是否合理？还能怎么拆？
2. 意图用枚举、路由用映射表，这样解耦有没有过度设计？
3. 兜底分支写得够不够健壮？还有哪些异常情况我没处理？
4. 我用 Express 中间件/路由做的类比准确吗？
5. 以后要把关键词识别换成大模型，我现在的设计换起来方便吗？

请用中文输出：做对的地方、不完整的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] `Intent` 意图枚举
- [✅] `Router` 路由表
- [✅] `IntentRecognizer` 关键词版意图识别
- [✅] `ParamExtractor` 参数抽取
- [✅] `ChatWorkflow` 完整流水线
- [✅] 能跑通四种输入的 `chat.php`
- [✅] 新增一个意图的练习记录
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能画出「识别→抽取→路由→执行→回复」的流程图
- [ ] 能解释意图识别是把「自然语言的乱」收敛成「有限标签」
- [ ] 用枚举定义了意图，用映射表定义了路由
- [ ] 实现的 Workflow 能正确路由三种意图
- [ ] 有明确、友好的兜底分支，不崩溃、不沉默
- [ ] Workflow 只调度不干活，各组件可单独替换
- [ ] 能说清「以后换成大模型识别」为什么改动很小

---

## 8. 今日自测题

### 8.1 意图识别在整条 Workflow 里的作用是什么？

参考答案：

> ✅ 它是流水线的第一步，把用户五花八门的说法收敛成有限的意图标签，为后续路由提供依据。相当于总机接线员，负责把用户转接到正确的分机（Skill）。

---

### 8.2 为什么意图要用枚举而不是随手写字符串？

参考答案：

> ✅ 枚举能防止拼写错误（写错 IDE/运行时会报错），并让所有可能的意图集中、一目了然，方便维护。这是工程化和随手写的分水岭。

---

### 8.3 路由表把什么和什么解耦了？带来什么好处？

参考答案：

> ✅ 把「意图识别」和「Skill 执行」解耦。识别只产出标签，路由表只管标签到 Skill 的映射。好处是加新意图只需在表里加一行，互不影响。

---

### 8.4 兜底分支为什么重要？一个好的兜底应该做到什么？

参考答案：

> ✅ 用户总会说出系统没预料的话，没有兜底就会崩溃或沉默。好的兜底应：明确告知没听懂、提示能做什么、绝不崩溃不沉默。

---

### 8.5 为什么说以后把关键词识别换成大模型「改动很小」？

参考答案：

> ✅ 因为 Workflow 各步骤解耦，识别只是其中一个组件。只要新识别器仍返回同样的 Intent，路由、执行、回复都不用改，属于「只换实现，不换接口」。

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
我正在进行 Week 23 Day 03：Workflow 设计 的学习。
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
