# Week 23 Day 02：Skill 编写

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂 skill-library 里 Skill 的写法，并参考它写出 3 个可被 Agent 加载的 Skill。

今天你要真正掌握这一句话：

> Skill 就是一份「给 Agent 看的操作手册 + 一段可执行能力」的打包：一个 `SKILL.md` 说明「这个技能是干什么的、什么时候用、怎么用」，配套的代码/脚本提供「真正干活的能力」；就像 Node 里一个 npm 包有 `package.json`（说明书）+ 源码（能力）。

如果这周你只记住一件事：Skill = 说明（元信息）+ 能力（代码），而且说明写得越清楚，Agent 越知道什么时候该调用它。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 Skill 是什么、和 Prompt 的区别
2. 理解一个 Skill 的目录结构
3. 理解 `SKILL.md` 的元信息字段（尤其 name / description）
4. 理解 Agent 是「怎么根据 description 决定要不要用这个 Skill」的
5. 读一个示例 Skill，拆解它的结构
6. 动手写 Skill 1：查订单状态
7. 动手写 Skill 2：算运费
8. 动手写 Skill 3：查物流
9. 写一个能「扫描并加载」这些 Skill 的加载器
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 Skill 是什么，和 Prompt 有什么区别

昨天的 Prompt 是「发给大模型的话」。今天的 Skill 更进一步：它是「Agent 能调用的一个能力单元」。

打个比方：

- Prompt = 你对助理说的话
- Skill = 你给助理配的一个工具箱里的工具（比如「计算器」「查快递单」）

区别：

| 对比项 | Prompt | Skill |
|---|---|---|
| 本质 | 一段文本模板 | 说明书 + 可执行能力 |
| 谁执行 | 大模型 | 大多由后端代码执行 |
| 结果 | 一段回答 | 结构化数据 / 明确动作 |
| 何时用 | 需要「说话」时 | 需要「做事」时（查、算、调接口） |

小白重点：

> 大模型很会「说」，但不擅长「精确地做」（比如算运费、查真实订单）。Skill 就是把这些「精确的活」交给后端代码，让大模型只负责判断「什么时候该用哪个 Skill」。

---

### 1.2 一个 Skill 的目录结构

参考主流 skill-library 的约定，一个 Skill 通常是一个文件夹：

```text
skills/
  order-status/
    SKILL.md          # 说明书（元信息 + 使用说明）
    handler.php       # 真正干活的代码
  shipping-fee/
    SKILL.md
    handler.php
  logistics-track/
    SKILL.md
    handler.php
```

`SKILL.md` 给「人和 Agent」看，`handler.php` 给「机器」执行。

JS/Node 类比：

```text
一个 npm 包：
  package.json  ≈ SKILL.md（说明书：名字、描述、入口）
  index.js      ≈ handler.php（真正的能力）
```

小白重点：

> 一个 Skill 一个文件夹，自成一体、可以整体复制到别的项目。这就是「可复用资产」的物理形态。

---

### 1.3 SKILL.md 的元信息字段

`SKILL.md` 顶部一般用 YAML frontmatter（就是被 `---` 包起来的一段元信息），然后正文写详细说明。

示例：

```markdown
---
name: order-status
description: 根据订单号查询订单当前状态（待支付/已发货/已完成等）。当用户询问订单进度、发货情况、订单状态时使用。
version: 1.0.0
inputs:
  - order_id: 订单号，字符串
outputs:
  - status: 订单状态文本
handler: handler.php
---

# 订单状态查询 Skill

## 什么时候用
用户问「我的订单到哪了」「发货了吗」「订单状态」时。

## 怎么用
传入 order_id，返回该订单的状态。

## 示例
输入：{ "order_id": "SO20260707001" }
输出：{ "status": "已发货" }
```

字段解释：

| 字段 | 作用 | 小白理解 |
|---|---|---|
| name | 唯一标识 | Skill 的身份证号 |
| description | 描述 + 触发时机 | 最重要！Agent 靠它判断要不要用 |
| version | 版本 | 方便升级和回滚 |
| inputs | 需要什么输入 | 参数清单 |
| outputs | 会返回什么 | 结果清单 |
| handler | 执行入口 | 指向真正干活的文件 |

小白重点：

> `description` 是全场 MVP。它不光要写「这个 Skill 干什么」，还要写「什么时候该用它」。写得越具体，Agent 越不会用错。

---

### 1.4 Agent 是怎么「挑」Skill 的

新手常有的疑问：Agent 怎么知道该调用哪个 Skill？

简化版流程是这样的：

```text
1. 平台把所有 Skill 的 name + description 收集起来，做成一张「工具清单」
2. 用户提问时，把这张清单连同问题一起给大模型
3. 大模型根据 description，判断「该不该用某个 Skill、用哪个」
4. 决定用了，就返回要调用的 Skill 名字 + 参数
5. 后端拿到名字，执行对应的 handler，把结果再喂回大模型
```

所以 `description` 写得好不好，直接决定 Agent 选得准不准。

对比两种写法：

```text
❌ 差：description: 查询订单
✅ 好：description: 根据订单号查询订单当前状态。当用户询问订单进度、
       发货情况、是否已发货、订单状态时使用。需要订单号。
```

小白重点：

> 把 description 想象成「给一个从没见过你系统的新同事看的一句话」，要让他一眼知道「什么情况下该点这个按钮」。

---

### 1.5 读一个示例 Skill，拆解结构

假设 skill-library 里有个 `weather` Skill，我们拆解它：

```markdown
---
name: weather
description: 查询指定城市的实时天气。当用户询问天气、气温、是否下雨时使用。
version: 1.0.0
inputs:
  - city: 城市名
outputs:
  - text: 天气描述
handler: handler.php
---
# 天气查询
...
```

对应的 handler：

```php
<?php

declare(strict_types=1);

// 约定：handler 接收一个数组参数，返回一个数组结果
return function (array $input): array {
    $city = $input["city"] ?? "";

    if ($city === "") {
        return ["ok" => false, "error" => "缺少城市名"];
    }

    // 真实项目里这里会调用天气 API，这里用假数据演示
    return [
        "ok" => true,
        "text" => "{$city}：晴，26℃",
    ];
};
```

拆解要点：

1. handler 是一个「接收数组、返回数组」的函数 —— 输入输出都结构化
2. 先校验输入，缺参数就返回错误，不抛异常中断整个 Agent
3. 返回里带 `ok` 字段，让调用方知道成功还是失败

小白重点：

> 统一「输入是数组、输出是数组、带 ok 标记」这套约定，是让所有 Skill 能被同一个加载器统一调用的关键。约定统一，才能平台化。

---

### 1.6 Skill 1：查订单状态

目录：`skills/order-status/`

`SKILL.md`：

```markdown
---
name: order-status
description: 根据订单号查询订单当前状态。当用户询问订单进度、是否发货、订单状态时使用。需要订单号 order_id。
version: 1.0.0
inputs:
  - order_id: 订单号，字符串
outputs:
  - status: 订单状态文本
handler: handler.php
---

# 订单状态查询 Skill

## 什么时候用
用户问「我的订单到哪了」「发货了吗」「订单什么状态」时。

## 示例
输入：{ "order_id": "SO20260707001" }
输出：{ "ok": true, "status": "已发货" }
```

`handler.php`：

```php
<?php

declare(strict_types=1);

return function (array $input): array {
    $orderId = $input["order_id"] ?? "";
    if ($orderId === "") {
        return ["ok" => false, "error" => "缺少订单号 order_id"];
    }

    // 演示用的假数据库
    $fakeDb = [
        "SO20260707001" => "已发货",
        "SO20260707002" => "待支付",
        "SO20260707003" => "已完成",
    ];

    if (!isset($fakeDb[$orderId])) {
        return ["ok" => false, "error" => "未找到该订单"];
    }

    return ["ok" => true, "status" => $fakeDb[$orderId]];
};
```

---

### 1.7 Skill 2：算运费

目录：`skills/shipping-fee/`

`SKILL.md`：

```markdown
---
name: shipping-fee
description: 根据重量和目的地计算运费。当用户询问运费、邮费多少、寄一件多少钱时使用。需要重量 weight_kg 和地区 region。
version: 1.0.0
inputs:
  - weight_kg: 重量（千克），数字
  - region: 地区，如 "省内" / "省外" / "偏远"
outputs:
  - fee: 运费金额
handler: handler.php
---

# 运费计算 Skill

## 规则
- 基础运费：省内 6 元，省外 10 元，偏远 20 元
- 每超过 1kg，加收 2 元
```

`handler.php`：

```php
<?php

declare(strict_types=1);

return function (array $input): array {
    $weight = (float)($input["weight_kg"] ?? 0);
    $region = (string)($input["region"] ?? "");

    if ($weight <= 0) {
        return ["ok" => false, "error" => "重量必须大于 0"];
    }

    $base = match ($region) {
        "省内" => 6,
        "省外" => 10,
        "偏远" => 20,
        default => null,
    };

    if ($base === null) {
        return ["ok" => false, "error" => "未知地区：{$region}"];
    }

    // 超过 1kg 的部分，每 kg 加 2 元（向上取整）
    $extra = (int)ceil(max(0, $weight - 1)) * 2;

    return ["ok" => true, "fee" => $base + $extra];
};
```

小白重点：

> 算运费这种「必须精确」的活，绝对不能让大模型自己算（它会算错）。这正是 Skill 存在的意义：把确定性逻辑交给代码。

---

### 1.8 Skill 3：查物流

目录：`skills/logistics-track/`

`SKILL.md`：

```markdown
---
name: logistics-track
description: 根据快递单号查询物流轨迹。当用户询问快递到哪了、物流信息、什么时候到时使用。需要快递单号 tracking_no。
version: 1.0.0
inputs:
  - tracking_no: 快递单号，字符串
outputs:
  - traces: 物流轨迹列表
handler: handler.php
---

# 物流轨迹查询 Skill

## 示例
输入：{ "tracking_no": "YT1234567890" }
输出：{ "ok": true, "traces": ["已揽收", "运输中", "派送中"] }
```

`handler.php`：

```php
<?php

declare(strict_types=1);

return function (array $input): array {
    $no = $input["tracking_no"] ?? "";
    if ($no === "") {
        return ["ok" => false, "error" => "缺少快递单号 tracking_no"];
    }

    // 演示假数据
    $fake = [
        "YT1234567890" => ["已揽收", "运输中", "派送中"],
        "YT0000000000" => ["已揽收"],
    ];

    if (!isset($fake[$no])) {
        return ["ok" => false, "error" => "未查询到该单号"];
    }

    return ["ok" => true, "traces" => $fake[$no]];
};
```

---

### 1.9 写一个 Skill 加载器

加载器负责：扫描 `skills/` 目录、读出每个 `SKILL.md` 的元信息、按 name 建立索引，需要时执行对应 handler。

先写一个解析 frontmatter 的小函数（简化版，只解析 name 和 description）：

```php
<?php

declare(strict_types=1);

namespace App\Skill;

class SkillLoader
{
    /** @var array<string, array{name:string, description:string, dir:string}> */
    private array $skills = [];

    public function __construct(private string $skillsDir) {}

    /** 扫描目录，加载所有 Skill 的元信息 */
    public function load(): void
    {
        foreach (glob($this->skillsDir . "/*", GLOB_ONLYDIR) as $dir) {
            $mdPath = $dir . "/SKILL.md";
            if (!file_exists($mdPath)) {
                continue;
            }

            $meta = $this->parseFrontmatter(file_get_contents($mdPath));
            if (isset($meta["name"])) {
                $this->skills[$meta["name"]] = [
                    "name" => $meta["name"],
                    "description" => $meta["description"] ?? "",
                    "dir" => $dir,
                ];
            }
        }
    }

    /** 给大模型看的「工具清单」 */
    public function toToolList(): array
    {
        return array_map(
            fn ($s) => ["name" => $s["name"], "description" => $s["description"]],
            array_values($this->skills)
        );
    }

    /** 执行某个 Skill 的 handler */
    public function run(string $name, array $input): array
    {
        if (!isset($this->skills[$name])) {
            return ["ok" => false, "error" => "未找到 Skill：{$name}"];
        }

        $handlerPath = $this->skills[$name]["dir"] . "/handler.php";
        if (!file_exists($handlerPath)) {
            return ["ok" => false, "error" => "Skill 缺少 handler.php"];
        }

        $handler = require $handlerPath; // 返回一个闭包
        return $handler($input);
    }

    /** 只解析 --- 之间的 name / description（简化版） */
    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*(.*?)\s*---/s', $content, $m)) {
            return [];
        }

        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^(name|description|version):\s*(.+)$/', trim($line), $kv)) {
                $meta[$kv[1]] = trim($kv[2]);
            }
        }
        return $meta;
    }
}
```

用一下：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Skill\SkillLoader;

$loader = new SkillLoader(__DIR__ . "/skills");
$loader->load();

// 1. 打印工具清单（这就是给大模型看的）
print_r($loader->toToolList());

// 2. 执行订单查询 Skill
$result = $loader->run("order-status", ["order_id" => "SO20260707001"]);
print_r($result);

// 3. 执行运费计算 Skill
$fee = $loader->run("shipping-fee", ["weight_kg" => 3.2, "region" => "省外"]);
print_r($fee);
```

小白重点：

> 加载器是「平台」的雏形：它不关心具体是哪个 Skill，只按统一约定扫描、索引、执行。你之后再加第 4 个、第 5 个 Skill，加载器一行都不用改 —— 这就是「可扩展」。

---

### 1.10 Skill 与 Prompt 如何配合

一次完整的对话，Skill 和 Prompt 通常这样配合：

```text
1. 平台用「工具清单」+ 用户问题，让大模型判断要不要用 Skill
2. 大模型说：用 order-status，参数 { order_id: "SO..." }
3. 后端用 SkillLoader.run 执行，拿到 { ok:true, status:"已发货" }
4. 平台把这个结果，套进「客服 Prompt 模板」再让大模型组织成一句人话
5. 用户收到：「您的订单已发货啦，还有什么可以帮您？」
```

小白重点：

> Skill 拿「事实」，Prompt 负责「说人话」。两者配合，Agent 才既准确又自然。

---

## 2. 源码阅读

- `graduation-project/skills/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 每个 Skill 是不是一个独立文件夹
2. `SKILL.md` 的 frontmatter 有哪些字段
3. `description` 是怎么写「触发时机」的
4. handler 的输入输出约定是什么（是不是数组进、数组出）
5. 加载器是怎么扫描和执行 Skill 的

建议整理这张表：

| 观察点 | 项目里怎么做 | 我的理解 |
|---|---|---|
| Skill 目录结构 |  |  |
| SKILL.md 字段 |  |  |
| description 写法 |  |  |
| handler 输入输出约定 |  |  |
| 加载/执行机制 |  |  |

---

## 3. 练习任务

### 练习 1：搭目录

```bash
mkdir -p skill-lib/src/Skill
mkdir -p skill-lib/skills/order-status
mkdir -p skill-lib/skills/shipping-fee
mkdir -p skill-lib/skills/logistics-track
cd skill-lib
```

`composer.json`：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

```bash
composer dump-autoload
```

---

### 练习 2：写三个 Skill

按 1.6 / 1.7 / 1.8，为每个 Skill 目录创建 `SKILL.md` 和 `handler.php`。

---

### 练习 3：写加载器

把 1.9 的 `SkillLoader` 保存到 `src/Skill/SkillLoader.php`。

---

### 练习 4：跑通

用 1.9 底部的 `index.php` 运行：

```bash
php index.php
```

目标：

- 工具清单能打印出 3 个 Skill 的 name + description
- 三个 Skill 都能被 `run()` 正确执行
- 故意传错参数（如缺 order_id），能看到 `ok:false` 的错误返回

---

### 练习 5：手写触发时机

给三个 Skill 各写 3 个「应该触发」的用户问法，再各写 1 个「不该触发」的问法。例如：

```text
order-status 应触发：
- 我的订单到哪了？
- 发货了吗？
- 帮我看下订单 SO001 状态
order-status 不该触发：
- 你们运费怎么算？（这该给 shipping-fee）
```

目标：体会 description 写得准，Agent 才选得对。

---

## 4. JS/Node.js 类比

| PHP / Skill | Node.js 类比 | 说明 |
|---|---|---|
| 一个 Skill 文件夹 | 一个 npm 包 | 自成一体、可复制复用 |
| `SKILL.md` frontmatter | `package.json` | 元信息说明书 |
| `description` 触发时机 | 包的文档/关键词 | 让人/Agent 知道何时用 |
| `handler.php`（闭包） | 包的入口函数 | 真正干活的代码 |
| SkillLoader 扫描目录 | require 遍历插件目录 | 动态发现并加载能力 |
| 工具清单 toToolList | 插件注册表 | 统一列出所有可用能力 |

---

## 5. AI Review 提问

完成练习后，把你的 3 个 Skill 和加载器贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 02：Skill 编写（订单状态/运费/物流三个 Skill）。

请你按资深工程师标准帮我检查：

1. 我的 SKILL.md 元信息字段是否齐全？description 的「触发时机」写得够清楚吗？
2. handler 的「数组进、数组出、带 ok 标记」约定是否合理？
3. 加载器动态扫描和执行 Skill 的方式有没有安全隐患（比如任意文件加载）？
4. 我用 npm 包做的类比是否准确？
5. 企业级 Skill 平台还需要补什么（权限、超时、幂等、日志）？

请用中文输出：做对的地方、不完整的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] 3 个 Skill 文件夹（各含 `SKILL.md` + `handler.php`）
- [✅] `SkillLoader` 加载器
- [✅] 能打印工具清单、能执行三个 Skill 的 `index.php`
- [✅] 每个 Skill 的触发时机问法清单
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Skill 和 Prompt 的区别
- [ ] 能说出一个 Skill 文件夹里应该有哪些文件
- [ ] 能列出 SKILL.md 的关键字段并说明 description 为何最重要
- [ ] 能解释 Agent 是怎么根据 description 挑 Skill 的
- [ ] 写出订单状态、运费、物流三个 Skill 且都能执行
- [ ] handler 遵循「数组进、数组出、带 ok 标记」约定
- [ ] 加载器能扫描目录、生成工具清单、执行指定 Skill
- [ ] 传错参数时能得到明确的错误返回

---

## 8. 今日自测题

### 8.1 Skill 和 Prompt 的核心区别是什么？

参考答案：

> ✅ Prompt 是发给大模型的文本模板，由大模型「说话」；Skill 是「说明书 + 可执行代码」，由后端代码「做事」并返回结构化数据。Skill 拿事实，Prompt 说人话。

---

### 8.2 SKILL.md 里哪个字段最重要，为什么？

参考答案：

> ✅ `description` 最重要。Agent 是把所有 Skill 的 name + description 做成工具清单，交给大模型判断该用哪个。description 写清楚「干什么 + 什么时候用」，Agent 才选得准。

---

### 8.3 为什么算运费这种事要交给 Skill 而不是让大模型算？

参考答案：

> ✅ 大模型不擅长精确计算，容易算错。运费这类确定性逻辑必须交给代码保证准确。Skill 的意义就是把「必须精确的活」交给后端执行。

---

### 8.4 为什么所有 handler 都约定「数组进、数组出、带 ok 标记」？

参考答案：

> ✅ 为了让加载器能用统一方式调用所有 Skill，不用针对每个 Skill 写特殊代码。约定统一才能平台化、可扩展，加新 Skill 时加载器不用改。

---

### 8.5 加载器扫描 `skills/*` 目录、动态 require handler，有什么潜在风险？

参考答案：

> ✅ 动态加载文件意味着「目录里放什么就执行什么」，如果目录可被外部写入就有任意代码执行风险。企业里要限制 Skill 来源、校验签名/白名单，并给 handler 加超时和异常隔离。

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
我正在进行 Week 23 Day 02：Skill 编写 的学习。
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
