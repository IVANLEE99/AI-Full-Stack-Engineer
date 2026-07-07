# Week 23 Day 05：优化与类比日

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

优化 Agent 的响应速度，并把错误处理做到「生产就绪」。

今天你要真正掌握这一句话：

> 「能跑」和「能上线」之间隔着两件事：够快（用户不等到烦）和够稳（出错也不崩、还能说清哪错了）。今天就是把 demo 级的 Agent 打磨成能交给别人用的产品。

如果这周你只记住一句：优化不是拍脑袋猜哪里慢，而是先测量再动手；错误处理不是加一堆 try-catch，而是让每个错误都有清晰的边界和友好的出口。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解「先测量，再优化」的铁律
2. 学会用简单方式测一段代码的耗时
3. 找出 Agent 里最慢的环节（通常是 Skill 加载和外部调用）
4. 用缓存优化重复工作（Skill 索引、意图结果）
5. 理解错误处理的三层结构
6. 用异常和统一错误出口重构
7. 加超时与降级，避免一个慢环节拖垮全局
8. 加结构化日志，方便线上排查
9. 做一次优化前后的对比
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 优化的铁律：先测量，再动手

新手最常犯的错：凭感觉猜「这里应该慢」，改了半天发现根本不是瓶颈。

正确顺序永远是：

```text
测量 → 找到真正的瓶颈 → 只优化瓶颈 → 再测量确认变快了
```

小白重点：

> 「过早优化是万恶之源」。没测量就优化，等于闭着眼睛打靶。先测量，让数据告诉你哪里慢。

---

### 1.2 最简单的耗时测量

PHP 里用 `microtime(true)` 打时间戳，前后一减就是耗时：

```php
<?php

declare(strict_types=1);

$start = microtime(true);

// 被测代码
$workflow->handle("订单 SO001 发货了吗");

$elapsed = (microtime(true) - $start) * 1000; // 转成毫秒
echo "耗时：" . round($elapsed, 2) . " ms\n";
```

封装成一个小工具，方便到处用：

```php
<?php

declare(strict_types=1);

namespace App\Support;

class Timer
{
    /** 测量一个闭包的执行耗时（毫秒） */
    public static function measure(callable $fn): array
    {
        $start = microtime(true);
        $result = $fn();
        $ms = (microtime(true) - $start) * 1000;

        return [
            "result" => $result,
            "ms"     => round($ms, 2),
        ];
    }
}
```

用法：

```php
<?php

$m = \App\Support\Timer::measure(fn () => $workflow->handle("订单 SO001"));
echo "结果意图：{$m['result']['intent']}，耗时：{$m['ms']} ms\n";
```

小白重点：

> `microtime(true)` 返回带小数的秒数，两次相减 ×1000 就是毫秒。这就是最基础的性能分析，够用。JS 里对应 `performance.now()` 或 `console.time()`。

---

### 1.3 找出 Agent 里最慢的环节

给 Workflow 每个阶段都打点，看时间花在哪：

```php
<?php

declare(strict_types=1);

// 在 ChatWorkflow::handle 里分段计时
$t = [];

$s = microtime(true);
$intent = $this->recognizer->recognize($text);
$t["意图识别"] = (microtime(true) - $s) * 1000;

$s = microtime(true);
$params = $this->extractor->extract($text, $intent);
$t["参数抽取"] = (microtime(true) - $s) * 1000;

$s = microtime(true);
$skillName = $this->router->route($intent);
$result = $this->loader->run($skillName, $params);
$t["Skill执行"] = (microtime(true) - $s) * 1000;

// 打印分段耗时
foreach ($t as $stage => $ms) {
    echo "{$stage}: " . round($ms, 2) . " ms\n";
}
```

典型结果（示意）：

```text
意图识别: 0.15 ms
参数抽取: 0.08 ms
Skill执行: 42.30 ms   ← 瓶颈在这
```

小白重点：

> 分段打点是定位瓶颈最直接的手段。看到 Skill 执行占了 99% 的时间，你就知道该往那里挖 —— 通常是它里面调了数据库或外部 API。

---

### 1.4 优化手段一：缓存重复工作

如果每次请求都重新扫 `skills/` 目录、重新解析每个 `SKILL.md`，那是巨大的浪费。把「Skill 索引」缓存起来只建一次：

```php
<?php

declare(strict_types=1);

namespace App\Skill;

class SkillLoader
{
    private array $skills = [];
    private bool $loaded = false;

    public function __construct(private string $baseDir) {}

    public function load(): void
    {
        // 已加载过就直接返回，避免重复扫盘
        if ($this->loaded) {
            return;
        }

        foreach (glob($this->baseDir . "/*", GLOB_ONLYDIR) as $dir) {
            $meta = $this->parseSkillMeta($dir . "/SKILL.md");
            $this->skills[$meta["name"]] = [
                "dir"  => $dir,
                "meta" => $meta,
            ];
        }

        $this->loaded = true;
    }

    // ...省略 parseSkillMeta / run ...
}
```

对「意图识别」这种纯函数，还能加结果缓存（同一句话不重复算）：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

class IntentRecognizer
{
    private array $cache = [];

    public function recognize(string $text): string
    {
        // 命中缓存直接返回
        if (isset($this->cache[$text])) {
            return $this->cache[$text];
        }

        $intent = $this->doRecognize($text);
        $this->cache[$text] = $intent;

        return $intent;
    }

    private function doRecognize(string $text): string
    {
        // ...原来的关键词匹配逻辑...
        return "unknown";
    }
}
```

| 缓存对象 | 为什么能缓存 | 效果 |
|---|---|---|
| Skill 索引 | 目录内容一次请求内不变 | 省掉重复扫盘和解析 |
| 意图识别结果 | 同输入同输出（纯函数） | 相同问题秒回 |
| 外部查询结果 | 短时间内数据变化不大 | 减少 API/DB 调用 |

小白重点：

> 缓存的本质是「把算过的结果存起来，下次直接用」。但缓存也有代价：数据可能过期。所以只缓存「短时间内不变」或「变了也无所谓」的东西。

---

### 1.5 错误处理的三层结构

好的错误处理分三层，各司其职：

```text
第一层：预防（参数校验）
  在错误发生前拦住 —— 缺订单号就别往下走

第二层：捕获（try-catch）
  真出了意外（如外部 API 超时），抓住它，别让它炸穿整个程序

第三层：出口（统一错误响应）
  无论哪层出错，最终都转成一个「用户能看懂 + 程序能识别」的统一格式
```

小白重点：

> 新手只会第二层（到处 try-catch）。真正稳的系统是三层配合：能预防的先预防，防不住的才捕获，捕获后统一出口。

---

### 1.6 用异常和统一出口重构

定义一个业务异常类，让「预期内的错误」有明确类型：

```php
<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

// 业务异常：预期内的、可以友好提示用户的错误
class SkillException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $userHint = "",
    ) {
        parent::__construct($message);
    }
}
```

Skill 里遇到预期内错误就抛它：

```php
<?php
// skills/order-status/run.php

declare(strict_types=1);

use App\Support\SkillException;

if (empty($params["order_id"])) {
    throw new SkillException(
        "missing order_id",
        userHint: "请提供订单号，例如 SO001",
    );
}
```

Workflow 的统一出口负责把各种情况都转成统一格式：

```php
<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Support\SkillException;
use Throwable;

class ChatWorkflow
{
    public function handle(string $text): array
    {
        try {
            $intent = $this->recognizer->recognize($text);
            $params = $this->extractor->extract($text, $intent);
            $skillName = $this->router->route($intent);
            $result = $this->loader->run($skillName, $params);

            return [
                "ok"     => true,
                "intent" => $intent,
                "reply"  => $this->buildReply($result),
            ];
        } catch (SkillException $e) {
            // 预期内错误：给用户友好提示
            return [
                "ok"     => false,
                "intent" => $intent ?? "unknown",
                "reply"  => $e->userHint ?: "抱歉，这个请求暂时没法处理",
            ];
        } catch (Throwable $e) {
            // 预期外错误：不暴露内部细节，只记日志
            error_log("[Agent] 未预期错误: " . $e->getMessage());
            return [
                "ok"     => false,
                "intent" => "error",
                "reply"  => "系统开小差了，请稍后再试",
            ];
        }
    }
}
```

小白重点：

> 分成两个 catch 是关键：`SkillException` 是「我们预料到的错」，可以直接把提示给用户；`Throwable` 是「没想到的错」，绝不能把内部报错原文抛给用户（会泄露信息也吓到用户），只记日志、给通用提示。

---

### 1.7 优化手段二：超时与降级

如果某个 Skill 要调外部 API，一旦对方卡住，整个 Agent 就被拖死。要设超时，超时后降级：

```php
<?php

declare(strict_types=1);

// 伪代码：给外部调用设超时
$ctx = stream_context_create([
    "http" => ["timeout" => 3], // 最多等 3 秒
]);

$raw = @file_get_contents($apiUrl, false, $ctx);

if ($raw === false) {
    // 降级：外部拿不到就返回一个「稍后再试」的兜底结果
    return [
        "ok"    => false,
        "error" => "查询服务暂时不可用",
    ];
}
```

| 概念 | 含义 | 例子 |
|---|---|---|
| 超时 | 等待上限，到点就放弃 | 外部 API 最多等 3 秒 |
| 降级 | 主路走不通时的备用方案 | 返回缓存/兜底提示 |
| 熔断 | 连续失败就暂时不再调 | 连错 5 次，1 分钟内直接降级 |

小白重点：

> 「一个慢环节拖垮全局」是分布式系统头号杀手。超时是底线：宁可给用户一句「稍后再试」，也不能让他转圈等 30 秒。

---

### 1.8 结构化日志

线上出问题时，日志是你唯一的眼睛。别只 `echo`，要打「结构化」日志（带上下文、可检索）：

```php
<?php

declare(strict_types=1);

namespace App\Support;

class Logger
{
    public static function log(string $level, string $msg, array $ctx = []): void
    {
        $line = json_encode([
            "time"  => date("c"),
            "level" => $level,
            "msg"   => $msg,
            "ctx"   => $ctx,
        ], JSON_UNESCAPED_UNICODE);

        error_log($line);
    }
}
```

在 Workflow 里记录每次请求：

```php
<?php

\App\Support\Logger::log("info", "handle", [
    "input"  => $text,
    "intent" => $intent,
    "ok"     => $out["ok"],
    "ms"     => $elapsed,
]);
```

产生的日志行（一行一条 JSON，方便机器解析）：

```text
{"time":"2026-07-07T10:30:00+08:00","level":"info","msg":"handle","ctx":{"input":"订单 SO001","intent":"order_status","ok":true,"ms":2.31}}
```

小白重点：

> 结构化日志（JSON 一行一条）和随手 `echo` 的区别是：前者能被日志系统检索、聚合、报警。线上排查全靠它。记住带上 input、intent、耗时这几个关键上下文。

---

### 1.9 优化前后对比

优化收尾一定要拿数据说话：

```text
优化前后对比
====================================
指标              优化前      优化后
单次冷启动         48 ms       46 ms
重复请求（命中缓存） 42 ms       3 ms
Skill 重复加载      每次        仅一次
外部超时上限        无（可能卡死） 3 秒
未预期错误处理      直接崩       统一兜底 + 日志
```

小白重点：

> 「我优化了」不是结论，「重复请求从 42ms 降到 3ms」才是结论。任何优化都要有优化前的基线数据对比，否则你不知道到底有没有变好。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

如果项目里已有日志或缓存相关代码，重点观察：

1. 日志有没有带上下文（不是光一句话）
2. 有没有区分「预期错误」和「未预期错误」
3. 外部调用有没有设超时

---

## 3. 练习任务

### 练习 1：给 Workflow 分段计时

- 用 1.3 的方式给 `handle` 每个阶段打点
- 跑 10 次取平均，找出最慢环节
- 记录基线数据

---

### 练习 2：加缓存

- 给 `SkillLoader::load` 加 `loaded` 标志，避免重复扫盘
- 给 `IntentRecognizer` 加结果缓存
- 用同一句话连续调 5 次，对比第一次和后续的耗时

---

### 练习 3：三层错误处理重构

- 定义 `SkillException`（带 `userHint`）
- Skill 里缺参数时抛它
- Workflow 用两个 catch（`SkillException` + `Throwable`）统一出口
- 分别测：缺参数、乱码、正常输入，确认回复都友好

---

### 练习 4：加超时与降级

- 给任意一个「模拟外部调用」的 Skill 设 3 秒超时
- 模拟超时（如 sleep 5 秒），确认降级返回「稍后再试」而不是卡死

---

### 练习 5：结构化日志 + 对比表

- 加 1.8 的 `Logger`，Workflow 里每次请求记一条
- 产出 1.9 的优化前后对比表

---

## 4. JS/Node.js 类比

| PHP 优化/容错 | Node.js 类比 | 说明 |
|---|---|---|
| `microtime(true)` | `performance.now()` | 计时 |
| 结果缓存数组 | Map 缓存 / memoize | 记住算过的结果 |
| `SkillException` | 自定义 Error 类 | 区分业务错误 |
| 两层 catch | `catch (e)` 里 `instanceof` 判断 | 分类处理 |
| `stream_context` timeout | `AbortController` / `fetch timeout` | 超时控制 |
| 结构化日志 JSON | pino / winston | 可检索日志 |
| 降级返回兜底 | 熔断库（如 opossum） | 主路失败走备路 |

---

## 5. AI Review 提问

完成练习后，把优化代码和对比数据贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 05：Agent 响应速度与错误处理优化。

请你按资深工程师标准帮我检查：

1. 我「先测量再优化」的做法对吗？瓶颈定位准不准？
2. 我加的缓存有没有风险（数据过期、内存无限增长）？
3. 我的三层错误处理（预防/捕获/出口）设计合理吗？
4. 我有没有把内部报错细节泄露给用户？
5. 超时和降级设置得合理吗？
6. 结构化日志的字段够不够排查线上问题？
7. 用 performance.now / pino 做的类比准吗？

请用中文输出：做对的地方、隐患、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] Workflow 分段计时与基线数据
- [✅] Skill 索引缓存 + 意图结果缓存
- [✅] `SkillException` 与三层错误处理
- [✅] 超时与降级机制
- [✅] 结构化日志 `Logger`
- [✅] 优化前后对比表
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出「先测量再优化」的铁律并解释为什么
- [ ] 能用 `microtime` 给代码分段计时并找出瓶颈
- [ ] 能给合适的对象加缓存，并说清缓存的代价
- [ ] 能设计预防/捕获/出口三层错误处理
- [ ] 能区分预期错误和未预期错误，且不向用户泄露内部细节
- [ ] 能给外部调用加超时并降级
- [ ] 能打出带上下文的结构化日志
- [ ] 有优化前后的数据对比，证明确实变好

---

## 8. 今日自测题

### 8.1 为什么优化前一定要先测量？

参考答案：

> ✅ 因为凭感觉猜的瓶颈往往不是真瓶颈，没测量就优化等于闭眼打靶。先测量让数据告诉你哪里慢，只优化真正的瓶颈才有效。

---

### 8.2 缓存有什么代价？什么样的东西才适合缓存？

参考答案：

> ✅ 代价是数据可能过期、内存可能增长。适合缓存的是「短时间内不变」或「变了也无所谓」的东西，比如 Skill 索引、纯函数的意图识别结果。

---

### 8.3 错误处理的三层结构是什么？

参考答案：

> ✅ 预防（参数校验，错误发生前拦住）、捕获（try-catch 抓住意外）、出口（统一转成用户能看懂+程序能识别的格式）。三层配合才稳，不能只靠到处 try-catch。

---

### 8.4 为什么 `SkillException` 和 `Throwable` 要分开处理？

参考答案：

> ✅ `SkillException` 是预期内错误，其 `userHint` 可直接给用户；`Throwable` 是未预期错误，绝不能把内部报错原文抛给用户（泄露信息、吓到用户），只能记日志并返回通用提示。

---

### 8.5 为什么外部调用一定要设超时？

参考答案：

> ✅ 因为一个慢环节会拖垮整个系统。设了超时，宁可给用户「稍后再试」的降级提示，也不让他转圈等几十秒。超时是分布式系统的底线。

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
我正在进行 Week 23 Day 05：优化与类比日 的学习。
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
