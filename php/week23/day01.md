# Week 23 Day 01：Prompt 库

> 所属周：Week 23：毕业项目：Agent 平台化  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project + skill-library`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解什么是 Prompt 模板库，并动手写出客服、查询、FAQ 三个可复用的 Prompt 模板。

今天你要真正掌握这一句话：

> Prompt 模板就是把「发给大模型的话」抽象成带「变量占位符」的可复用文件，就像后端把 SQL 抽象成带 `?` 参数的预处理语句、前端把 HTML 抽象成带 `{{ }}` 的组件模板一样；模板 + 变量 = 最终 Prompt。

如果这周你只记住一件事：不要把提示词写死在代码里到处复制粘贴，而要把它当成「资产」统一管理、统一渲染、统一版本化。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚「Prompt」到底是什么，为什么需要「库」
2. 理解写死的 Prompt 有什么问题（反面教材）
3. 理解模板 + 变量占位符的思路
4. 理解一个 Prompt 模板文件应该包含哪些字段
5. 用 PHP 写一个最小的 Prompt 渲染器（把变量填进模板）
6. 动手写客服模板
7. 动手写查询模板
8. 动手写 FAQ 模板
9. 给每个模板写「使用说明」
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是 Prompt，为什么要有「Prompt 库」

Prompt（提示词）就是你发给大模型的那段话。比如：

```text
你是一个电商客服，请用礼貌、简短的语气回答用户的问题。
用户问题：我的订单什么时候发货？
```

在毕业项目里，Agent 平台会有很多种任务：客服要一种语气、查询要一种格式、FAQ 要另一种结构。如果每次都临时手写，会出现三个问题：

1. 到处复制粘贴，改一处要改十处
2. 每个人写的风格不一样，质量不稳定
3. 没法测试、没法回滚、没法统计哪个版本效果好

所以我们要建「Prompt 库」：把这些提示词抽出来，变成统一管理的文件。

小白重点：

> 「库」这个字，意思不是数据库，而是「集中收纳、统一取用」的意思。就像你的组件库、工具函数库。

---

### 1.2 反面教材：把 Prompt 写死在代码里

先看一段很多新手会写的代码（这是错误示范）：

```php
<?php

declare(strict_types=1);

// ❌ 反面教材：Prompt 写死、和业务代码混在一起
function askCustomerService(string $userQuestion): string
{
    $prompt = "你是一个电商客服，请礼貌回答。用户问题：" . $userQuestion;
    // 调用大模型 ...
    return $prompt;
}

function askQuery(string $userQuestion): string
{
    $prompt = "你是一个查询助手，请返回 JSON。用户问题：" . $userQuestion;
    // 调用大模型 ...
    return $prompt;
}
```

问题在哪？

- 提示词和 PHP 代码绑死，产品同学、运营同学想改一个字都要找开发
- 三个函数里的「你是一个 XX」结构其实很像，却没有复用
- 想给客服话术加一句「结尾要问是否还有其他问题」，得逐个函数改

JS/Node 里同样的坏味道：

```js
// ❌ 同样的反面教材
function askCustomerService(question) {
  const prompt = `你是一个电商客服，请礼貌回答。用户问题：${question}`;
  // ...
}
```

小白重点：

> 只要你发现「同一类字符串在多个地方拼来拼去」，就应该想到抽模板。

---

### 1.3 正确思路：模板 + 变量占位符

正确做法是把「不变的部分」写进模板，把「每次变化的部分」用占位符表示。

模板文件（先用最直白的 `{{变量名}}` 语法）：

```text
你是一个专业的电商客服，语气礼貌、简短。
公司名称：{{company}}
用户问题：{{question}}
请用不超过 3 句话回答，并在结尾询问用户是否还有其他需要帮助的地方。
```

渲染（把变量填进去）之后：

```text
你是一个专业的电商客服，语气礼貌、简短。
公司名称：星辰商城
用户问题：我的订单什么时候发货？
请用不超过 3 句话回答，并在结尾询问用户是否还有其他需要帮助的地方。
```

这和后端的 SQL 预处理、前端的模板引擎是同一个思路：

| 场景 | 模板 | 占位符 | 填充后 |
|---|---|---|---|
| SQL | `SELECT * FROM t WHERE id = ?` | `?` | `id = 10` |
| 前端模板 | `<h1>{{ title }}</h1>` | `{{ title }}` | `<h1>首页</h1>` |
| Prompt | `用户问题：{{question}}` | `{{question}}` | `用户问题：怎么退货` |

小白重点：

> 占位符的意义就是「这里以后会被真实数据替换掉」。你现在只要认得 `{{question}}` 这种写法就行。

---

### 1.4 一个 Prompt 模板文件应该有哪些字段

只写一段话是不够的。一个能被平台管理的模板，建议至少包含这些元信息（metadata）：

```text
id：模板唯一标识，比如 customer_service_v1
name：给人看的名字，比如「客服-标准话术」
description：这个模板干什么用的
variables：需要哪些变量（占位符列表）
template：真正的提示词正文
```

我们用 PHP 数组把它表示出来（今天先不引入数据库，用文件即可）：

```php
<?php

declare(strict_types=1);

return [
    "id" => "customer_service_v1",
    "name" => "客服-标准话术",
    "description" => "电商客服通用话术，语气礼貌简短",
    "variables" => ["company", "question"],
    "template" => <<<PROMPT
你是一个专业的电商客服，语气礼貌、简短。
公司名称：{{company}}
用户问题：{{question}}
请用不超过 3 句话回答，并在结尾询问用户是否还有其他需要帮助的地方。
PROMPT,
];
```

这里的 `<<<PROMPT ... PROMPT` 是 PHP 的 Heredoc 语法，用来写多行字符串，非常适合放 Prompt。

JS/Node 类比：

```js
// JS 里用模板字符串（反引号）写多行
export default {
  id: "customer_service_v1",
  name: "客服-标准话术",
  description: "电商客服通用话术，语气礼貌简短",
  variables: ["company", "question"],
  template: `你是一个专业的电商客服，语气礼貌、简短。
公司名称：{{company}}
用户问题：{{question}}
请用不超过 3 句话回答。`,
};
```

| 对比项 | PHP | JS |
|---|---|---|
| 多行字符串 | Heredoc `<<<PROMPT` | 模板字符串 反引号 |
| 导出配置 | `return [...]` | `export default {...}` |
| 变量占位 | `{{name}}`（自定义） | `{{name}}`（自定义） |

小白重点：

> 注意：PHP 的 Heredoc 里如果直接写 `$question` 会被当成 PHP 变量替换。我们故意用 `{{question}}` 这种双花括号，就是为了避开 PHP 自己的变量替换，留给我们自己的渲染器处理。

---

### 1.5 写一个最小的 Prompt 渲染器

渲染器负责一件事：把模板里的 `{{变量}}` 换成真实值。

```php
<?php

declare(strict_types=1);

namespace App\Prompt;

class PromptRenderer
{
    /**
     * 把模板里的 {{key}} 替换成 $vars[key]
     *
     * @param string $template 模板正文
     * @param array<string, string> $vars 变量键值对
     */
    public function render(string $template, array $vars): string
    {
        $result = $template;

        foreach ($vars as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $result = str_replace($placeholder, $value, $result);
        }

        return $result;
    }
}
```

用一下：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Prompt\PromptRenderer;

$config = require __DIR__ . "/prompts/customer_service.php";

$renderer = new PromptRenderer();

$finalPrompt = $renderer->render($config["template"], [
    "company" => "星辰商城",
    "question" => "我的订单什么时候发货？",
]);

echo $finalPrompt . PHP_EOL;
```

运行后你会看到占位符被真实值替换掉的最终 Prompt。

JS/Node 类比（用正则替换）：

```js
function render(template, vars) {
  return template.replace(/\{\{(\w+)\}\}/g, (_, key) => vars[key] ?? "");
}
```

小白重点：

> 渲染器只做「填空」这一件事，不要在里面塞业务逻辑。这样它才能被所有模板复用。

---

### 1.6 校验：变量少填了怎么办

真实项目里，最怕模板需要 `question` 你却忘了传。加一个校验：

```php
<?php

declare(strict_types=1);

namespace App\Prompt;

class PromptRenderer
{
    /**
     * @param array<string, string> $vars
     * @param string[] $required 模板声明的必填变量
     */
    public function render(string $template, array $vars, array $required = []): string
    {
        // 校验必填变量
        foreach ($required as $name) {
            if (!array_key_exists($name, $vars)) {
                throw new \InvalidArgumentException("缺少必填变量：{$name}");
            }
        }

        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace("{{" . $key . "}}", $value, $result);
        }

        return $result;
    }
}
```

调用时把模板声明的 `variables` 传进去：

```php
$finalPrompt = $renderer->render(
    $config["template"],
    ["company" => "星辰商城", "question" => "怎么退货？"],
    $config["variables"], // ["company", "question"]
);
```

小白重点：

> 「早失败」比「悄悄出错」好。少传变量时直接抛异常，比生成一段带着 `{{question}}` 的残缺 Prompt 发给大模型强得多。

---

### 1.7 三个模板的分工

今天要写三个模板，先理解它们的差异：

| 模板 | 用途 | 语气/输出要求 | 关键变量 |
|---|---|---|---|
| 客服 customer_service | 回答用户咨询 | 礼貌、简短、口语化 | company, question |
| 查询 query | 把用户问题转成结构化查询意图 | 严格 JSON，不要废话 | question, fields |
| FAQ | 从知识库里匹配答案 | 引用知识库、答不了就说不知道 | knowledge, question |

小白重点：

> 三个模板最大的区别是「输出格式约束」。客服要自然语言，查询要 JSON，FAQ 要「基于给定知识库回答」。把这些约束写进模板，就能稳定拿到你想要的结果。

---

### 1.8 客服模板

文件：`prompts/customer_service.php`

```php
<?php

declare(strict_types=1);

return [
    "id" => "customer_service_v1",
    "name" => "客服-标准话术",
    "description" => "电商客服通用话术，语气礼貌简短，用于回答用户日常咨询",
    "variables" => ["company", "question"],
    "template" => <<<PROMPT
你是「{{company}}」的专业在线客服。
要求：
1. 语气礼貌、简短、口语化，不要长篇大论。
2. 回答控制在 3 句话以内。
3. 如果无法确定答案，请引导用户联系人工客服，不要编造。
4. 结尾询问用户是否还有其他需要帮助的地方。

用户问题：{{question}}
PROMPT,
];
```

---

### 1.9 查询模板

文件：`prompts/query.php`

```php
<?php

declare(strict_types=1);

return [
    "id" => "query_intent_v1",
    "name" => "查询-意图结构化",
    "description" => "把用户的自然语言问题转成结构化 JSON，供后端查询使用",
    "variables" => ["question", "fields"],
    "template" => <<<PROMPT
你是一个查询意图解析器。请把用户问题解析成 JSON。
可用字段：{{fields}}
规则：
1. 只输出 JSON，不要任何多余文字、不要 Markdown 代码块。
2. 无法识别的字段留空字符串。
3. JSON 结构固定为：{"intent": "查询意图", "params": {"字段": "值"}}

用户问题：{{question}}
PROMPT,
];
```

小白重点：

> 让大模型「只输出 JSON、不要 Markdown 代码块」这句话非常关键。否则它常常会给你套一层 ```json ``` 包裹，后端解析就会报错。

---

### 1.10 FAQ 模板

文件：`prompts/faq.php`

```php
<?php

declare(strict_types=1);

return [
    "id" => "faq_v1",
    "name" => "FAQ-知识库问答",
    "description" => "基于给定知识库回答用户问题，答不了就明确说不知道",
    "variables" => ["knowledge", "question"],
    "template" => <<<PROMPT
你是一个 FAQ 助手。请「只根据下面提供的知识库」回答用户问题。
知识库内容：
{{knowledge}}

规则：
1. 只能使用知识库里的信息，禁止编造。
2. 如果知识库里没有相关内容，请回答「抱歉，我暂时没有找到相关信息」。
3. 回答尽量引用知识库中的原文关键点。

用户问题：{{question}}
PROMPT,
];
```

小白重点：

> FAQ 模板的核心是「只根据给定知识库回答」。这在业内叫「防幻觉」，是让 Agent 可信的关键约束。

---

### 1.11 给每个模板写「使用说明」

模板写完还不够，别人（包括三个月后的你自己）要能看懂怎么用。使用说明建议放在同目录的 `README.md` 或每个模板的 `description` 里，至少说明：

```text
- 这个模板解决什么问题
- 需要传哪些变量、每个变量是什么
- 一个完整的调用示例
- 期望的输出长什么样
```

示例（客服模板使用说明）：

```text
## customer_service_v1 使用说明

用途：回答用户的日常咨询。
变量：
- company：公司/商城名称，如「星辰商城」
- question：用户原始问题

调用示例：
$renderer->render($config["template"], [
  "company" => "星辰商城",
  "question" => "怎么申请退货？",
], $config["variables"]);

期望输出：3 句话以内的礼貌回答，结尾追问是否还需要帮助。
```

---

## 2. 源码阅读

- `graduation-project/prompts/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 每个模板文件是不是都有 `id`、`name`、`variables`、`template`
2. 占位符用的是什么语法（`{{}}` 还是别的）
3. 有没有统一的渲染器
4. 有没有对必填变量做校验
5. 输出格式约束（JSON、字数限制等）写在哪里

建议你在笔记里整理这样一张表：

| 观察点 | 项目里是怎么做的 | 我的理解 |
|---|---|---|
| 占位符语法 |  |  |
| 是否有元信息字段 |  |  |
| 是否有统一渲染器 |  |  |
| 是否校验变量 |  |  |

---

## 3. 练习任务

### 练习 1：搭好目录

```bash
mkdir -p prompt-lib/prompts prompt-lib/src/Prompt
cd prompt-lib
```

创建 `composer.json`：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

生成自动加载：

```bash
composer dump-autoload
```

---

### 练习 2：写渲染器

把 1.6 的 `PromptRenderer` 保存到：

```text
src/Prompt/PromptRenderer.php
```

---

### 练习 3：写三个模板

分别创建：

```text
prompts/customer_service.php
prompts/query.php
prompts/faq.php
```

内容用 1.8 / 1.9 / 1.10。

---

### 练习 4：跑通三个模板

创建 `index.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Prompt\PromptRenderer;

$renderer = new PromptRenderer();

// 客服
$cs = require __DIR__ . "/prompts/customer_service.php";
echo "=== 客服 ===" . PHP_EOL;
echo $renderer->render($cs["template"], [
    "company" => "星辰商城",
    "question" => "怎么申请退货？",
], $cs["variables"]) . PHP_EOL . PHP_EOL;

// 查询
$q = require __DIR__ . "/prompts/query.php";
echo "=== 查询 ===" . PHP_EOL;
echo $renderer->render($q["template"], [
    "question" => "我想看最近一周的订单",
    "fields" => "order_id, status, created_at",
], $q["variables"]) . PHP_EOL . PHP_EOL;

// FAQ
$faq = require __DIR__ . "/prompts/faq.php";
echo "=== FAQ ===" . PHP_EOL;
echo $renderer->render($faq["template"], [
    "knowledge" => "退货政策：7 天无理由退货，运费由买家承担。",
    "question" => "多久可以退货？",
], $faq["variables"]) . PHP_EOL;
```

运行：

```bash
php index.php
```

目标：三个模板都能正确渲染，占位符全部被替换。

---

### 练习 5：写使用说明

在 `prompts/` 下建一个 `README.md`，参考 1.11，给三个模板都写清楚：用途、变量、调用示例、期望输出。

---

## 4. JS/Node.js 类比

| PHP / Prompt 库 | Node.js / 前端类比 | 说明 |
|---|---|---|
| Prompt 模板文件 | 可复用组件模板 | 把重复的东西抽出来统一管理 |
| `{{变量}}` 占位符 | 模板引擎的插值 | 运行时被真实数据替换 |
| PromptRenderer | 模板引擎（如 Handlebars） | 负责把模板 + 数据合成结果 |
| `variables` 字段 | 组件 props 声明 | 声明这个模板需要哪些输入 |
| 必填变量校验 | props 类型校验 | 少传就报错，早失败 |
| Heredoc 多行字符串 | 反引号模板字符串 | 写多行文本更舒服 |

---

## 5. AI Review 提问

完成练习后，把你的三个模板和渲染器贴给 AI，然后问：

```text
我正在学习 PHP Week 23 Day 01：Prompt 库（客服/查询/FAQ 三模板）。

请你按资深工程师标准帮我检查：

1. 我把 Prompt 抽成模板 + 变量的做法是否合理？
2. 三个模板的「输出格式约束」写得够不够清楚？会不会出现幻觉或格式错乱？
3. 我的渲染器有没有安全或健壮性问题（比如变量注入、少传变量）？
4. 我用前端组件/模板引擎做的类比有没有误导？
5. 如果这是企业级 Agent 平台，我还需要补哪些字段（版本、语言、A/B 测试）？

请用中文输出：
- 我做对的地方
- 我做得不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `PromptRenderer` 渲染器（带变量校验）
- [✅] 客服模板 `customer_service.php`
- [✅] 查询模板 `query.php`
- [✅] FAQ 模板 `faq.php`
- [✅] 三个模板都能通过 `index.php` 跑通
- [✅] `prompts/README.md` 使用说明
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释什么是 Prompt 模板、为什么要建 Prompt 库
- [ ] 能说出写死 Prompt 的三个坏处
- [ ] 能解释「模板 + 占位符」的思路，并类比 SQL/前端模板
- [ ] 能写出一个把 `{{变量}}` 替换成真实值的渲染器
- [ ] 渲染器能对少传的必填变量抛异常
- [ ] 写出客服、查询、FAQ 三个模板
- [ ] 三个模板都能正确渲染出最终 Prompt
- [ ] 给三个模板写了使用说明

---

## 8. 今日自测题

### 8.1 为什么不建议把 Prompt 直接写死在业务代码里？

参考答案：

> ✅ 因为写死会导致到处复制粘贴、改一处要改多处；提示词和代码绑死后产品/运营改一个字都要找开发；而且无法统一测试、版本化和复用。抽成模板库后可以集中管理、统一渲染。

---

### 8.2 `{{question}}` 这种占位符和 SQL 的 `?` 有什么共同点？

参考答案：

> ✅ 都是「先留个空位，运行时再填真实数据」。模板保持不变，变化的部分用占位符表示，最后由渲染器/预处理把真实值填进去。

---

### 8.3 为什么查询模板里要强调「只输出 JSON、不要 Markdown 代码块」？

参考答案：

> ✅ 因为大模型经常会自作主张给 JSON 套一层 ```json ``` 代码块，后端直接 `json_decode` 会解析失败。明确约束输出格式，才能稳定拿到可解析的结果。

---

### 8.4 FAQ 模板里「只根据给定知识库回答」这句话的作用是什么？

参考答案：

> ✅ 用来防止大模型「幻觉」（编造知识库里没有的内容）。限制它只能用给定信息回答、答不了就说不知道，能让 FAQ 的答案更可信、可追溯。

---

### 8.5 渲染器为什么要做「必填变量校验」？

参考答案：

> ✅ 为了「早失败」。如果少传变量，最终 Prompt 里会残留 `{{question}}` 这种占位符发给大模型，导致答非所问。提前校验并抛异常，能在问题扩散前拦住它。

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
我正在进行 Week 23 Day 01：Prompt 库 的学习。
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
