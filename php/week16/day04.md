# Week 16 Day 04：售后 Node 链

> 所属周：Week 16：编排模式对比  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`pay-service + ai-lab`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂售后服务里 3 个典型 Node 的职责，并亲手画出「售后申请」这条 Node 链的流转图。

今天你要真正掌握这一句话：

> 售后 Node 链和支付 Node 链用的是同一套 NodeExecutionEngine 编排引擎，区别只在于「节点内容」和「Context 字段」不同。读懂支付链，就能用同样的套路读懂售后链——先找 Context 是什么，再顺着 Node 顺序看每一步对 Context 做了什么。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Day 02 读支付 Node 链的方法（先找 Context，再顺 Node）
2. 定位售后服务的 nodes 目录，列出有哪些 Node
3. 挑 3 个核心 Node 精读：申请校验、退款计算、状态流转
4. 弄清每个 Node 读了 Context 的什么、写回了什么
5. 把 3 个 Node 串成一条「售后申请」流转链
6. 画出这条链的流程图（含失败分支）
7. 对比支付链和售后链的异同
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 复习读 Node 链的「三步法」

Day 02 你学过读一条 Node 链的通用方法，今天原样复用：

```text
第一步：找 Context —— 这条链共享的那份数据长什么样？
第二步：顺 Node   —— 按执行顺序，一个个看
第三步：追字段    —— 每个 Node 读了 Context 的哪些字段、写回了哪些字段
```

小白重点：

> 读任何一条 Node 链，都不要一上来钻进某个 Node 的细节。先建立「Context + 顺序」的全局图，再逐个 Node 填细节。

---

### 1.2 定位售后 nodes 目录

售后服务的节点通常集中放在一个目录里：

```text
aftersale-service/common/services/nodes/
├── AftersaleContext.php          # 售后上下文（共享状态）
├── ValidateApplyNode.php         # 校验售后申请
├── CalcRefundNode.php            # 计算可退金额
├── ChangeStatusNode.php          # 流转售后单状态
├── NotifyBuyerNode.php           # 通知买家
└── NotifySellerNode.php          # 通知卖家
```

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

小白重点：

> 看到目录名带 `nodes/`，基本可以断定这里用了「链式 Node 编排」。目录里通常有一个 `XxxContext` 就是共享状态。

---

### 1.3 精读 Node ①：ValidateApplyNode（校验申请）

这个节点负责「把关」，脏数据不让往下走。

```php
<?php

declare(strict_types=1);

namespace App\Aftersale\Nodes;

class ValidateApplyNode
{
    public function handle(AftersaleContext $ctx): NodeResult
    {
        // 读 Context
        $order = $ctx->getOrder();
        $reason = $ctx->getApplyReason();

        // 规则 1：订单必须存在且已支付
        if ($order === null || $order->status !== OrderStatus::Paid) {
            return NodeResult::failed('订单状态不支持售后');
        }

        // 规则 2：必须在售后有效期内（示例：签收后 7 天）
        if ($order->isOverAftersaleWindow()) {
            return NodeResult::failed('已超过售后有效期');
        }

        // 规则 3：申请原因不能为空
        if ($reason === '') {
            return NodeResult::failed('请填写售后原因');
        }

        // 写回 Context：标记校验通过
        $ctx->markValidated();

        return NodeResult::success();
    }
}
```

读法总结：

| 项目 | 内容 |
|------|------|
| 读 Context | `order`、`applyReason` |
| 做了什么 | 三条校验规则 |
| 写回 Context | `validated = true` |
| 失败会怎样 | 返回 `failed`，引擎中断，后面节点不执行 |

---

### 1.4 精读 Node ②：CalcRefundNode（计算退款）

校验通过后，算「到底能退多少钱」。

```php
<?php

declare(strict_types=1);

namespace App\Aftersale\Nodes;

class CalcRefundNode
{
    public function handle(AftersaleContext $ctx): NodeResult
    {
        $order = $ctx->getOrder();

        // 基础退款 = 商品实付金额
        $refund = $order->paidAmount;

        // 规则：运费是否可退（示例：买家责任不退运费）
        if ($ctx->getResponsibility() === 'buyer') {
            $refund -= $order->shippingFee;
        }

        // 规则：使用过的优惠券不返还，需扣减
        $refund -= $order->couponDeduction;

        // 兜底：退款金额不能为负
        $refund = max($refund, 0);

        // 写回 Context：把算好的金额放进去，给后续节点用
        $ctx->setRefundAmount($refund);

        return NodeResult::success();
    }
}
```

读法总结：

| 项目 | 内容 |
|------|------|
| 读 Context | `order`、`responsibility` |
| 做了什么 | 按规则计算可退金额 |
| 写回 Context | `refundAmount` |
| 关键点 | 它不发钱，只算钱。金额算好后交给后面的节点 |

小白重点：

> 注意「单一职责」：这个节点只负责**算**退款金额，不负责**改**状态、不负责**发**通知。每个 Node 只干一件事，这正是 Day 02 强调的原则。

---

### 1.5 精读 Node ③：ChangeStatusNode（状态流转）

金额算好后，把售后单推进到下一个状态。

```php
<?php

declare(strict_types=1);

namespace App\Aftersale\Nodes;

class ChangeStatusNode
{
    public function handle(AftersaleContext $ctx): NodeResult
    {
        $aftersale = $ctx->getAftersaleOrder();
        $refund = $ctx->getRefundAmount();

        // 根据是否需要人工审核，决定下一个状态
        if ($refund > $ctx->getAutoApproveLimit()) {
            // 金额超过自动通过阈值 → 转人工审核
            $aftersale->status = AftersaleStatus::WaitReview;
        } else {
            // 小额 → 自动通过，等待退款
            $aftersale->status = AftersaleStatus::WaitRefund;
        }

        $aftersale->save();

        // 写回 Context：把最新状态放进去
        $ctx->setAftersaleStatus($aftersale->status);

        return NodeResult::success();
    }
}
```

读法总结：

| 项目 | 内容 |
|------|------|
| 读 Context | `aftersaleOrder`、`refundAmount`、`autoApproveLimit` |
| 做了什么 | 根据金额决定「转人工」还是「自动通过」 |
| 写回 Context | `aftersaleStatus` |
| 关键点 | 这里出现了**分支**：大额转审核，小额自动过 |

小白重点：

> 注意这里的分支是写在节点**内部**的 `if`。这正是链式编排处理分支的典型方式——和昨天（Day 03）说的「图用条件边、链用 if」完全对应上了。

---

### 1.6 把 3 个 Node 串成售后申请链

现在把三个节点按顺序连起来，加上通知节点，画出完整流转图。

```text
                        AftersaleContext
                              │
            ┌─────────────────▼─────────────────┐
            │        ValidateApplyNode           │  校验申请
            └─────────────────┬─────────────────┘
                   校验失败 ◀──┤ (return failed → 中断)
                              │ 校验通过
            ┌─────────────────▼─────────────────┐
            │          CalcRefundNode            │  算退款金额
            └─────────────────┬─────────────────┘
                              │
            ┌─────────────────▼─────────────────┐
            │         ChangeStatusNode           │  流转状态
            └───────┬──────────────────┬────────┘
              大额  │                  │ 小额
        ┌───────────▼──────┐   ┌───────▼──────────┐
        │  status=待审核    │   │  status=待退款    │
        └───────────┬──────┘   └───────┬──────────┘
                    └────────┬─────────┘
            ┌────────────────▼────────────────┐
            │  NotifyBuyerNode / NotifySeller  │  通知买卖双方
            └────────────────┬────────────────┘
                             ▼
                           完成
```

小白重点：

> 这张图就是今天的核心产出。它清楚展示了：一份 Context 顺着 4 个节点流转，中间有一处「校验失败中断」和一处「大额/小额分支」。

---

### 1.7 支付链 vs 售后链：异同对比

把 Day 02 的支付链和今天的售后链放一起对比。

| 对比项 | 支付 Node 链 | 售后 Node 链 |
|--------|-------------|-------------|
| 编排引擎 | NodeExecutionEngine | 同一套引擎 |
| 共享状态 | `PayContext` | `AftersaleContext` |
| 典型节点 | 校验 → 扣库存 → 调渠道 → 通知 | 校验 → 算退款 → 流转状态 → 通知 |
| 分支处理 | 节点内 `if` | 节点内 `if`（大额/小额） |
| 失败处理 | 返回 failed 中断 | 返回 failed 中断 |
| 方向 | 单向往前 | 单向往前 |

三处相同：

1. 用同一套引擎，同一套「Context + Node 数组」骨架。
2. 每个 Node 单一职责，只读写 Context。
3. 失败都靠 `NodeResult::failed()` 中断。

三处不同：

1. Context 字段不同（一个装支付信息，一个装退款信息）。
2. 节点业务含义不同（一个扣款，一个退款）。
3. 分支判断条件不同（支付看渠道，售后看金额阈值）。

---

## 2. 源码阅读

- `aftersale-service/common/services/nodes/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找：

1. `AftersaleContext` 里有哪些字段？
2. nodes 目录里一共有几个 Node？
3. 哪个文件负责把这些 Node 串起来（引擎/组装处）？
4. 哪个 Node 里出现了分支 `if`？分支条件是什么？
5. 哪些字段是「上游写、下游读」的（比如 `refundAmount`）？

建议在笔记里写出售后 Context 字段表：

| 字段 | 类型 | 谁写入 | 谁读取 | 含义 |
|------|------|--------|--------|------|
| `order` | Order | 入口 | ValidateApplyNode | 原订单 |
| `applyReason` | string | 入口 | ValidateApplyNode | 售后原因 |
| `validated` | bool | ValidateApplyNode | 引擎 | 是否校验通过 |
| `refundAmount` | int | CalcRefundNode | ChangeStatusNode | 可退金额 |
| `aftersaleStatus` | enum | ChangeStatusNode | NotifyNode | 售后单状态 |

---

## 3. 练习任务

### 练习 1：读 3 个 Node 并填职责表

对照真实代码（或上面的示例），填这张表：

| Node | 读了什么 | 做了什么 | 写回什么 |
|------|----------|----------|----------|
| ValidateApplyNode |  |  |  |
| CalcRefundNode |  |  |  |
| ChangeStatusNode |  |  |  |

---

### 练习 2：画售后申请 Node 链图

用文本箭头图（或纸笔）画出售后申请的完整流转，要求包含：

- 4 个以上节点
- 至少 1 处「失败中断」
- 至少 1 处「分支」

---

### 练习 3：找出「跨节点传递」的字段

在售后链里，找出至少 2 个「一个节点写、另一个节点读」的 Context 字段，并画出传递方向。

参考答案：

```text
refundAmount:   CalcRefundNode 写 ──▶ ChangeStatusNode 读
aftersaleStatus: ChangeStatusNode 写 ──▶ NotifyNode 读
```

---

### 练习 4：给售后链加一个新需求

需求：「如果售后原因是『商品质量问题』，运费必须全退」。

问：这个改动应该加在哪个 Node？为什么？

参考答案：

> ✅ 加在 `CalcRefundNode`。因为「运费退不退」属于退款金额计算逻辑，符合该节点「只算钱」的单一职责。不应该加在校验或状态节点。

---

### 练习 5：把售后链改写成图编排（伪代码）

用昨天（Day 03）学的图编排写法，把售后链改写成 LangGraph 风格伪代码，重点体现「大额/小额」用条件边表达。

参考骨架：

```text
graph.add_node("validate", validate)
graph.add_node("calc", calc)
graph.add_node("wait_review", wait_review)
graph.add_node("wait_refund", wait_refund)

graph.set_entry("validate")
graph.add_edge("validate", "calc")

function route(state):
    return "wait_review" if state.refund > state.limit else "wait_refund"

graph.add_conditional_edge("calc", route)
```

---

## 4. JS/Node.js 类比

| 售后链概念 | PHP | JS/Node 类比 |
|-----------|-----|-------------|
| AftersaleContext | 共享上下文对象 | Express `req`（一路传下去） |
| 单个 Node | 一个类的 `handle()` | 一个 middleware 函数 |
| 失败中断 | `return failed` | `return res.status(400)`（不调 next） |
| 节点内分支 | `if ($refund > $limit)` | middleware 里的 `if` 判断 |
| 节点串联 | Node 数组 | `app.use(a); app.use(b);` |

一句话类比：

> 售后 Node 链就像一串 Express 中间件：`AftersaleContext` 是 `req`，每个 Node 校验/修改它，校验不过就直接返回错误（不往下调 next）。

---

## 5. AI Review 提问

完成 Node 链图后，贴给 AI 提问：

```text
我正在学习 PHP Week16 Day04：售后 Node 链（架构理解）。

我读了售后服务里的 3 个 Node（校验申请、计算退款、流转状态），
画了售后申请的 Node 链流转图，并列了 Context 字段表。

请你按资深 PHP 后端工程师标准帮我检查：

1. 我对每个 Node 职责的理解是否准确？有没有把逻辑放错节点？
2. 我画的 Node 链流转图对不对？失败分支和条件分支画得对吗？
3. 我列的 Context 字段「谁写谁读」是否正确？
4. 售后链和支付链的异同，我总结得对吗？
5. 真实项目里，售后退款这种涉及钱的链路还要注意什么（幂等、并发、审计）？

请用中文输出：我对的地方、我错或不完整的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] 3 个 Node 的职责表
- [ ] 售后申请 Node 链流转图（含失败 + 分支）
- [ ] 售后 Context 字段表（谁写谁读）
- [ ] 「跨节点传递字段」方向图
- [ ] 支付链 vs 售后链异同对比
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出售后 3 个核心 Node 各自的职责
- [ ] 能画出售后申请 Node 链（含失败中断和分支）
- [ ] 能列出售后 Context 至少 5 个字段
- [ ] 能指出至少 2 个「跨节点传递」的字段
- [ ] 能说出支付链和售后链的 3 同 3 异
- [ ] 能判断「新需求该加到哪个 Node」

---

## 8. 今日自测题

### 8.1 读一条 Node 链的「三步法」是什么？

参考答案：

> ✅ 第一步找 Context（共享状态长什么样）；第二步顺 Node（按执行顺序逐个看）；第三步追字段（每个 Node 读了什么、写回了什么）。

---

### 8.2 CalcRefundNode 为什么不直接改状态、发通知？

参考答案：

> ✅ 单一职责原则。它只负责「算退款金额」，改状态交给 ChangeStatusNode，发通知交给 NotifyNode。每个 Node 只干一件事，链路才清晰、好维护。

---

### 8.3 售后链里的「大额转审核、小额自动过」这个分支，是怎么实现的？

参考答案：

> ✅ 写在 ChangeStatusNode 内部的 `if` 判断（`refund > autoApproveLimit`）。链式编排的分支通常写在节点内部的 if 里，而不是像图编排那样用条件边。

---

### 8.4 `refundAmount` 这个字段体现了什么设计？

参考答案：

> ✅ 体现了「跨节点数据传递」：CalcRefundNode 算好后写入 Context，ChangeStatusNode 从 Context 读出来用。Context 就是节点之间传数据的载体。

---

### 8.5 支付链和售后链最大的共同点是什么？

参考答案：

> ✅ 用的是同一套 NodeExecutionEngine 编排引擎，同一套「Context + Node 数组 + 引擎驱动」的骨架。区别只在于 Context 字段和节点业务不同。

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
我正在进行 Week 16 Day 04：售后 Node 链 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 16 README](./README.md)
