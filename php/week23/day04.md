# Week 23 Day 04：集成测试

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

对整条 Workflow 做多场景集成测试，找出失败 case 并修复。

今天你要真正掌握这一句话：

> 单元测试测「一个零件」，集成测试测「整条流水线跑通」；对 Agent 来说，集成测试就是模拟一堆真实用户的话，检查「意图识别 → 路由 → Skill → 回复」端到端有没有出错。

如果这周你只记住一件事：写完功能不等于做完，能证明它在各种输入下都不崩，才叫做完。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 分清单元测试、集成测试、E2E 测试
2. 理解为什么 Agent 特别需要集成测试
3. 设计测试用例：正常 case + 边界 case + 异常 case
4. 用最朴素的「断言脚本」跑一遍（不依赖框架）
5. 引入 PHPUnit 写规范的集成测试
6. 记录失败 case
7. 逐个定位并修复
8. 回归测试：确认修复没引入新问题
9. 生成一份简单的测试报告
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 三种测试的区别

| 测试类型 | 测什么 | 例子 |
|---|---|---|
| 单元测试 | 一个函数/类 | 测 `ParamExtractor` 能不能抽出订单号 |
| 集成测试 | 多个组件协作 | 测「输入一句话 → 得到回复」整条链路 |
| E2E 测试 | 整个系统（含界面/网络） | 用户在真实页面输入并看到回复 |

今天我们主要做「集成测试」：把昨天的 `ChatWorkflow` 当成一个整体，喂各种输入，检查输出对不对。

小白重点：

> 单元测试像「检查每个零件合格」，集成测试像「把零件装成发动机，点火看能不能转」。零件都合格，装起来也可能不转 —— 所以两种测试都要做。

---

### 1.2 为什么 Agent 特别需要集成测试

普通 CRUD 接口输入输出很确定，Agent 却要面对「自然语言」这种极其发散的输入：

```text
同样是查订单，用户可能说：
  "订单到哪了"
  "SO001 呢"
  "我买的东西发了没"
  "查一下订单"（没给单号）
  "订单 XYZ"（乱写的单号）
```

每一种说法都可能踩到不同的坑。所以 Agent 的集成测试要覆盖大量「说法变体」和「缺参数/错参数」的情况。

小白重点：

> Agent 的 bug 往往不在「主干功能」，而在「用户换个说法」「用户少说一个字」这些边角。集成测试就是专门用来扫这些边角的。

---

### 1.3 设计测试用例：三类 case

好的测试用例要覆盖三类：

```text
正常 case：标准输入，应该成功
  "订单 SO001 发货了吗" → 意图 order_status，返回状态

边界 case：临界/特殊输入
  "" (空字符串)          → 应走兜底，不崩溃
  "订单"（只有关键词没单号）→ 意图对，但缺参数

异常 case：非法/意外输入
  "sdjfklsjdf"（乱码）   → 走兜底
  "订单 SO999"（不存在） → Skill 返回「查无此单」
```

小白重点：

> 新手只测「正常 case」，觉得能跑通就完事了。真正的 bug 几乎都藏在边界和异常里。测试用例里边界+异常的数量，通常应该比正常 case 还多。

---

### 1.4 最朴素的断言脚本（先跑起来）

在引入测试框架前，先用一个纯 PHP 脚本理解「断言」的本质：拿到实际结果，和期望比较，不一致就报警。

`tests/smoke.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use App\Skill\SkillLoader;
use App\Workflow\ChatWorkflow;
use App\Workflow\IntentRecognizer;
use App\Workflow\ParamExtractor;
use App\Workflow\Router;

$loader = new SkillLoader(__DIR__ . "/../skills");
$loader->load();

$workflow = new ChatWorkflow(
    new IntentRecognizer(),
    new ParamExtractor(),
    new Router(),
    $loader,
);

// 测试用例：[输入, 期望意图]
$cases = [
    ["订单 SO001 发货了吗", "order_status"],
    ["寄 3kg 到省外多少钱", "shipping_fee"],
    ["快递 YT123 到哪了", "logistics_track"],
    ["", "unknown"],
    ["asdfghjkl", "unknown"],
];

$pass = 0;
$fail = 0;

foreach ($cases as [$input, $expectIntent]) {
    $out = $workflow->handle($input);
    $actual = $out["intent"];
    if ($actual === $expectIntent) {
        echo "[PASS] \"{$input}\" → {$actual}\n";
        $pass++;
    } else {
        echo "[FAIL] \"{$input}\" 期望 {$expectIntent}，实际 {$actual}\n";
        $fail++;
    }
}

echo str_repeat("=", 40) . "\n";
echo "通过 {$pass} 个，失败 {$fail} 个\n";
```

运行：

```bash
php tests/smoke.php
```

小白重点：

> 「断言」本质就一句话：`if (实际 !== 期望) 报警`。所有测试框架的核心都是这个，别被框架吓到。

---

### 1.5 引入 PHPUnit 写规范测试

朴素脚本能跑，但缺少统计、隔离、报告等能力。真实项目用 PHPUnit。

安装：

```bash
composer require --dev phpunit/phpunit
```

写测试类 `tests/ChatWorkflowTest.php`：

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use App\Skill\SkillLoader;
use App\Workflow\ChatWorkflow;
use App\Workflow\IntentRecognizer;
use App\Workflow\ParamExtractor;
use App\Workflow\Router;
use PHPUnit\Framework\TestCase;

class ChatWorkflowTest extends TestCase
{
    private ChatWorkflow $workflow;

    // 每个测试方法运行前都会先执行这里，准备好被测对象
    protected function setUp(): void
    {
        $loader = new SkillLoader(__DIR__ . "/../skills");
        $loader->load();

        $this->workflow = new ChatWorkflow(
            new IntentRecognizer(),
            new ParamExtractor(),
            new Router(),
            $loader,
        );
    }

    public function testOrderStatusIntent(): void
    {
        $out = $this->workflow->handle("订单 SO001 发货了吗");
        $this->assertSame("order_status", $out["intent"]);
    }

    public function testShippingFeeIntent(): void
    {
        $out = $this->workflow->handle("寄 3kg 到省外多少钱");
        $this->assertSame("shipping_fee", $out["intent"]);
    }

    public function testEmptyInputGoesToUnknown(): void
    {
        $out = $this->workflow->handle("");
        $this->assertSame("unknown", $out["intent"]);
    }

    public function testReplyIsNeverEmpty(): void
    {
        // 无论输入什么，回复都不能为空（保证不沉默）
        foreach (["订单 SO001", "乱码xyz", ""] as $text) {
            $out = $this->workflow->handle($text);
            $this->assertNotEmpty($out["reply"], "输入「{$text}」时回复为空");
        }
    }
}
```

在 `composer.json` 里补上测试的自动加载：

```json
{
  "autoload-dev": {
    "psr-4": {
      "App\\Tests\\": "tests/"
    }
  }
}
```

然后：

```bash
composer dump-autoload
./vendor/bin/phpunit tests/
```

小白重点：

> `setUp()` 会在每个测试方法前重新准备一次对象，保证测试之间互不影响（这叫「测试隔离」）。`assertSame`、`assertNotEmpty` 就是各种「断言」的封装。

---

### 1.6 断言方法对照

| 断言 | 含义 | 用途 |
|---|---|---|
| `assertSame($a, $b)` | 全等（含类型） | 精确比对意图字符串 |
| `assertEquals($a, $b)` | 相等（松散） | 数值比对 |
| `assertNotEmpty($x)` | 非空 | 回复不能为空 |
| `assertTrue($x)` | 为真 | 检查 `ok` 标志 |
| `assertContains($n, $arr)` | 数组含某项 | 检查轨迹里有某节点 |
| `assertArrayHasKey($k, $arr)` | 数组有某键 | 检查参数抽到了 order_id |

小白重点：

> 不用记全，先记住 `assertSame`（比对精确值）和 `assertNotEmpty`（防空）这两个，80% 场景够用了。

---

### 1.7 记录失败 case

跑测试时如果有 `FAIL`，别急着改代码，先记下来。建个简单的失败清单：

```text
失败 case 记录
----------------------------------------
编号  输入                    期望            实际           初步原因
F1   "订单"（无单号）        order_status    order_status   意图对，但 Skill 崩了（缺 order_id）
F2   "运费"（无重量）        shipping_fee    shipping_fee   同上，缺 weight_kg
F3   "SO001状态"（无空格）    order_status    unknown        关键词没命中"状态"前的连写
```

小白重点：

> 「先记录、再批量分析、最后统一修」比「边测边改」更高效。边测边改很容易改出新问题又忘了之前测到哪了。

---

### 1.8 定位与修复

以 F1 为例（缺订单号导致 Skill 崩溃）。Skill 内部应该「优雅地返回错误」而不是崩溃：

```php
<?php
// skills/order-status/run.php （修复后）

declare(strict_types=1);

$params = $params ?? [];

// 参数校验：缺 order_id 时返回错误，而不是继续往下崩
if (empty($params["order_id"])) {
    return [
        "ok"    => false,
        "error" => "缺少订单号，请提供形如 SO001 的订单号",
    ];
}

$orderId = $params["order_id"];

// ...正常查询逻辑...
return [
    "ok"     => true,
    "status" => "已发货",
];
```

Workflow 的 `buildReply` 里已经处理了 `ok === false` 的情况（Day 03 写过），所以修好 Skill 后，用户会收到友好提示而不是看到崩溃。

小白重点：

> 修 bug 的方向应该是「让程序优雅处理这种输入」，而不是「让用户别这么输入」。用户永远会各种乱输，兜底和参数校验是你的责任。

---

### 1.9 回归测试

修完 F1、F2、F3 后，一定要把**所有**测试再跑一遍，确认：

1. 原来失败的现在通过了
2. 原来通过的没有被改坏

```bash
./vendor/bin/phpunit tests/
```

看到全绿（OK）才算真的修好。

小白重点：

> 「回归」= 改完再全测一遍。很多新手改好一个 bug 却悄悄弄坏了另一个功能，就是因为没做回归测试。这也是「自动化测试」价值最大的地方 —— 全测一遍只要几秒。

---

### 1.10 生成简单测试报告

给毕业项目留一份人看的测试报告（纯文本即可）：

```text
Agent Workflow 集成测试报告
====================================
测试日期：2026-07-07
测试对象：ChatWorkflow（意图识别→路由→Skill→回复）

用例统计：
  总用例：12
  通过：12
  失败：0

覆盖场景：
  [√] 三种正常意图识别
  [√] 空输入兜底
  [√] 乱码输入兜底
  [√] 缺参数（无订单号/无重量）
  [√] 不存在的订单号
  [√] 回复永不为空

本轮修复：
  F1 缺订单号 Skill 崩溃 → 加参数校验，优雅返回错误
  F2 缺重量 → 同上
  F3 "SO001状态"连写识别失败 → 关键词增加"状态"独立匹配

结论：主要场景全部通过，可进入 Day 05 性能与错误处理优化。
```

小白重点：

> 报告不用花哨，但要能回答三个问题：测了什么、通过率多少、修了什么。毕业答辩时这份报告就是你「做完了、且验证过」的证据。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

如果项目里已有测试目录，重点观察：

1. 测试文件放在哪（一般是 `tests/`）
2. 用了什么断言
3. `setUp` 里准备了什么
4. 有没有覆盖异常/边界 case

---

## 3. 练习任务

### 练习 1：跑通朴素断言脚本

- 把 1.4 的 `smoke.php` 放到 `tests/smoke.php`
- 运行 `php tests/smoke.php`，观察 PASS/FAIL 统计

---

### 练习 2：引入 PHPUnit

- `composer require --dev phpunit/phpunit`
- 把 1.5 的 `ChatWorkflowTest` 放到 `tests/`
- 配好 `autoload-dev` 并 `composer dump-autoload`
- `./vendor/bin/phpunit tests/`

---

### 练习 3：设计并补齐 12 个用例

按三类各补几个：

```text
正常：三种意图各 1 个
边界：空输入、只有关键词无参数、超长输入
异常：乱码、不存在的订单号、参数格式错误（如重量写成"很重"）
```

目标：把用例数量堆到 12 个以上，边界+异常要多于正常。

---

### 练习 4：制造并修复一个失败 case

- 故意输入一个当前会失败的 case（如 1.7 的 F3）
- 记录到失败清单
- 定位原因、修复
- 回归全测，确认全绿

---

### 练习 5：产出测试报告

参照 1.10 写一份 `tests/REPORT.txt`，覆盖：测了什么、通过率、修了什么、结论。

目标：形成毕业项目可交付的测试证据。

---

## 4. JS/Node.js 类比

| PHP 测试 | Node.js 类比 | 说明 |
|---|---|---|
| PHPUnit | Jest / Mocha | 测试框架 |
| `./vendor/bin/phpunit` | `npm test` | 运行测试 |
| `setUp()` | `beforeEach()` | 每个用例前准备 |
| `assertSame` | `expect().toBe()` | 断言 |
| 集成测试整条 Workflow | supertest 打接口 | 端到端验证 |
| 回归测试 | CI 上全量跑一遍 | 防止改坏别处 |

---

## 5. AI Review 提问

完成练习后，把测试代码和报告贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 04：Agent Workflow 集成测试。

请你按资深工程师标准帮我检查：

1. 我对单元测试 / 集成测试 / E2E 的区分是否正确？
2. 我的测试用例覆盖够不够？还缺哪些边界和异常场景？
3. 我修 bug 的方向对吗（优雅处理输入 vs 限制输入）？
4. 我做的回归测试到位吗？
5. 用 Jest/beforeEach 做的类比准确吗？
6. 真实项目里，Agent 的集成测试还应该加什么（比如响应时间、并发）？

请用中文输出：做对的地方、遗漏的场景、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] 朴素断言脚本 `smoke.php`
- [✅] PHPUnit 集成测试 `ChatWorkflowTest`
- [✅] 12+ 个覆盖三类场景的测试用例
- [✅] 失败 case 记录清单
- [✅] 至少 1 个失败 case 的定位与修复
- [✅] 回归测试全绿
- [✅] 测试报告 `REPORT.txt`
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清单元测试、集成测试、E2E 的区别
- [ ] 能解释 Agent 为什么特别需要集成测试
- [ ] 测试用例覆盖了正常 + 边界 + 异常三类
- [ ] 能用 PHPUnit 写并运行集成测试
- [ ] 能记录失败 case 并定位根因
- [ ] 修 bug 的方向是「优雅处理」而非「限制用户」
- [ ] 修完做了回归测试，确认没改坏别处
- [ ] 产出了一份能说清「测了什么、通过率、修了什么」的报告

---

## 8. 今日自测题

### 8.1 单元测试和集成测试有什么区别？

参考答案：

> ✅ 单元测试测单个零件（如某个函数），集成测试测多个组件协作跑通整条链路。零件都合格，装起来也可能不转，所以两者都要做。

---

### 8.2 为什么 Agent 特别需要集成测试？

参考答案：

> ✅ 因为 Agent 面对自然语言这种极发散的输入，同一意图有无数说法，bug 常藏在「换个说法」「少说一个字」的边角，需要用大量说法变体和缺参数场景去扫。

---

### 8.3 好的测试用例应覆盖哪三类？哪类最容易被忽略？

参考答案：

> ✅ 正常 case、边界 case、异常 case。新手最容易只测正常 case，而真正的 bug 几乎都藏在边界和异常里。

---

### 8.4 发现「缺订单号导致 Skill 崩溃」，正确的修复方向是什么？

参考答案：

> ✅ 让 Skill 优雅处理：校验参数，缺失时返回 `ok=false` 和友好错误信息，而不是崩溃，更不是要求用户别这么输入。

---

### 8.5 什么是回归测试？为什么修完 bug 一定要做？

参考答案：

> ✅ 回归测试是改完代码后把所有测试再全跑一遍。因为修一个 bug 可能悄悄弄坏另一个功能，只有全测通过才算真修好。自动化测试让全测只要几秒，价值最大。

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
我正在进行 Week 23 Day 04：集成测试 的学习。
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
