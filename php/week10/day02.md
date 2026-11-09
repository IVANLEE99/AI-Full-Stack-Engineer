# Week 10 Day 02：processingType 策略模式

> 所属周：Week 10：售后服务 + Console 任务  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`aftersale-service`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂 `processingType` 策略模式，理解 `ReturnGoodsRefund`、`Reissue` 等不同售后处理类型为什么要拆成独立策略类，以及新增售后类型时应该如何扩展。

今天你要真正掌握这一句话：

> 售后 processingType 策略模式的价值是：把“退货退款、仅退款、补发、换货”等不同处理逻辑从大 Service 的 switch/if 中拆出去，让每种售后类型有独立、可测试、可扩展的处理类。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天售后域为什么复杂
2. 理解什么是策略模式
3. 打开 `ReturnGoodsRefund.php`，看它处理什么售后类型
4. 打开 `Reissue.php`，和退货退款做对比
5. 找它们是否实现同一个接口或继承同一个基类
6. 对比每个策略的入参、校验、状态变化和输出
7. 思考新增售后类型要改哪些地方
8. 用 Node.js handlers map / strategy class 做类比
9. 用 AI Review 检查扩展方式是否合理

---

## 1. 学习内容

### 1.1 什么是 processingType？

`processingType` 可以理解为售后处理类型。

常见类型：

| processingType | 含义 |
|---|---|
| return_goods_refund | 退货退款 |
| refund_only | 仅退款 |
| reissue | 补发 |
| exchange | 换货 |
| repair | 维修 |

不同类型业务差异很大。

例如：

- 退货退款：需要用户寄回商品、仓库收货、再触发退款
- 仅退款：不需要物流，审核通过后可能直接退款
- 补发：不退款，而是创建补发单/物流任务

---

### 1.2 如果不用策略模式会怎样？

大 Service 中可能出现：

```php
<?php

if ($type === 'return_goods_refund') {
    // 退货退款逻辑
} elseif ($type === 'reissue') {
    // 补发逻辑
} elseif ($type === 'refund_only') {
    // 仅退款逻辑
}
```

问题：

- `AfterSaleService` 越来越长
- 新增类型要改老代码
- 每个类型测试困难
- 很容易改坏别的售后类型
- 状态流转散落在 if/else 中

策略模式把它拆成：

```text
ReturnGoodsRefund Strategy
Reissue Strategy
RefundOnly Strategy
```

---

### 1.3 策略模式怎么理解？

策略模式的核心：

```text
同一个接口，不同实现。
```

伪代码：

```php
<?php

interface ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult;
}
```

退货退款：

```php
<?php

final class ReturnGoodsRefund implements ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult
    {
        // 退货退款逻辑
    }
}
```

补发：

```php
<?php

final class Reissue implements ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult
    {
        // 补发逻辑
    }
}
```

---

### 1.4 ReturnGoodsRefund 关注什么？

退货退款通常关注：

- 是否允许退货
- 是否需要上传凭证
- 退货地址
- 用户退货物流单号
- 仓库是否收到货
- 可退金额
- 退款状态
- 支付退款调用

流程可能是：

```text
用户申请退货退款
  ↓
客服审核通过
  ↓
用户寄回商品
  ↓
仓库确认收货
  ↓
触发退款
  ↓
售后完成
```

---

### 1.5 Reissue 关注什么？

补发通常关注：

- 是否需要用户退货
- 补发商品/数量
- 收货地址
- 物流单号
- 是否创建补发订单/任务
- 是否通知仓库
- 是否通知用户

补发不一定涉及退款。

流程可能是：

```text
用户申请补发
  ↓
客服审核通过
  ↓
创建补发任务
  ↓
仓库发货
  ↓
用户收货
  ↓
售后完成
```

---

### 1.6 策略对比表

| 对比项 | ReturnGoodsRefund | Reissue |
|---|---|---|
| 是否退款 | 是 | 通常否 |
| 是否退货 | 是 | 通常否 |
| 是否需要物流 | 用户退货物流 | 商家补发物流 |
| 是否调用支付退款 | 是 | 否 |
| 是否创建发货任务 | 可能否 | 是 |
| 核心风险 | 超额退款、重复退款 | 重复补发、地址错误 |
| 完成条件 | 退款成功 | 补发完成 |

---

### 1.7 新增类型如何扩展？

假设新增“维修”类型：

1. 新增 processingType 常量
2. 新增 `Repair` 策略类
3. 实现统一接口
4. 注册到工厂/映射表
5. 增加状态流转规则
6. 增加表单校验和权限校验
7. 增加测试和 AI Review

重点：尽量少改已有策略类。

---

### 1.8 Node.js 类比

Node 中 handlers map：

```js
const strategies = {
  return_goods_refund: new ReturnGoodsRefundStrategy(),
  reissue: new ReissueStrategy(),
};

const strategy = strategies[processingType];
await strategy.handle(context);
```

PHP 的策略模式类似。

---

## 2. 源码阅读

- `aftersale-service/common/services/processingType/concrete/ReturnGoodsRefund.php`
- `aftersale-service/common/services/processingType/concrete/Reissue.php`（建议同时阅读）
- processingType 接口/基类、工厂/映射表（本地缺失，见 2.10）

> 说明：路径均为公开代号 + 相对路径。本地参考源码见 [ReturnGoodsRefund.php](./ReturnGoodsRefund.php)、[Reissue.php](./Reissue.php)。基类 `ProcessingType.php`、工厂 `AfterSaleHandleService.php`、枚举 `AfterSaleProcessTypeEnum.php`、配置 `params.php` 都不在本地；分流名单用仓库指南 [06-aftersale-service.md](../06-aftersale-service.md) 佐证。下文区分「源码可确认」「指南说明」「推断」。

阅读记录：

### 2.1 一句话结论：策略模式是“注册制”

> `concrete/` 下有类文件，**不等于**它会被工厂调用。判断某个 `processing_type` 走哪条路，顺序是：① 看 `params.php` 的 `processing_through_the_factory_class` 名单 → ② 看 `AfterSaleService` 的 5 个受保护分流点 → ③ 才去看具体策略类。

- **`Reissue`（71 补发）**：在名单内，是真实策略类，967 行 / 25 个方法，内部仍然组合 Node 链。
- **`ReturnGoodsRefund`（63 退货退款）**：不在名单内，10 个钩子里 **9 个是 `404 no action`**；真实退货退款主流程走 Node 链。但它仍会被实例化——`installServiceCancelNotify` 不查名单，直接按售后单的 `processing_type` 取具体类（见 2.6）。

### 2.2 策略对比表（Day 02 核心产出）

| 对比项 | ReturnGoodsRefund | Reissue |
|---|---|---|
| 文件/规模 | 105 行，10 个 public 方法 | 967 行，25 个方法（20 public + 5 private） |
| 实现接口/继承 | `extends \common\services\processingType\ProcessingType`（L16） | 同左（L41） |
| `processingTypeValue` | `AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND`（L20） | `AfterSaleProcessTypeEnum::REISSUE`（L45） |
| `processingTypeName` | `'退货退款'`（L22） | `'补发'`（L47） |
| 是否在工厂名单 | **否**（指南名单 7 项不含它） | **是**（第 1 项，71 补发） |
| 核心方法 | 9 个 `404 no action` + `installationServiceCancelNotify` 委托 | 10 个生命周期钩子 + 15 个辅助方法 |
| 状态变化 | 类内不写状态 | 0 / 7 / 91 / 16 / 10 / 4 / 31 / 35 等 |
| 是否退款 | 类内不退款（仅安装服务取消回调间接建退款单） | 取消已支付补发时 `createdRefund` → 31 |
| 是否创建下游单 | 否 | AMS 补发单 + 商城补发订单 + 支付单 |
| 主要风险 | 误加入名单会让退货退款主流程全 404 | AMS 单与 DB 无事务、重复补发、`refundNotify` 状态不一致 |

### 2.3 契约：`extends` 基类，不是 `implements` 接口

- 课程本文 1.3 节的 `ProcessingTypeInterface` / `implements` 是**教学伪代码**；源码是 `extends \common\services\processingType\ProcessingType`（基类本地缺失）。
- 共同钩子（均无参，入参统一放 `$this->params`）：`apply` / `temporaryData` / `submitConfirm` / `cancel` / `paidNotify` / `refundNotify` / `auditPass` / `auditReject` / `toOmsOrderNotify` / `installationServiceCancelNotify`。
- `Reissue` 实现其中 9 个，并额外多一个 `amsNotify()`；**没有** `installationServiceCancelNotify()`。`ReturnGoodsRefund` 有全部 10 个。
- 返回约定：`{code,data,info}`，调用点统一用 `1 == $result['code']` 判成功；不支持的动作返回 `returnError(404, 'no action')`。
- 基类提供 `returnSuccess/returnError`、`$this->params`、`doneHandle(...)`、`createdRefund(...)`（本地缺失，只能从调用点反推；指南提到基类创建退款单失败会抛 `1010004`）。

### 2.4 ReturnGoodsRefund 逐方法（10 个）

| 方法 | 行号 | 行为 | 状态/退款 | 备注 |
|---|---|---|---|---|
| `apply` | [L27](./ReturnGoodsRefund.php#L27) | `returnError(404, 'no action')` | 无 | 占位 |
| `temporaryData` | [L35](./ReturnGoodsRefund.php#L35) | 同上 | 无 | 占位 |
| `submitConfirm` | [L43](./ReturnGoodsRefund.php#L43) | 同上 | 无 | 占位 |
| `cancel` | [L51](./ReturnGoodsRefund.php#L51) | 同上 | 无 | 占位 |
| `paidNotify` | [L59](./ReturnGoodsRefund.php#L59) | 同上 | 无 | 占位 |
| `refundNotify` | [L67](./ReturnGoodsRefund.php#L67) | 同上 | 无 | 占位 |
| `auditPass` | [L78](./ReturnGoodsRefund.php#L78) | 同上 | 无 | 占位 |
| `auditReject` | [L88](./ReturnGoodsRefund.php#L88) | 同上 | 无 | 占位 |
| `toOmsOrderNotify` | [L93](./ReturnGoodsRefund.php#L93) | 同上 | 无 | 占位 |
| `installationServiceCancelNotify` | [L101](./ReturnGoodsRefund.php#L101) | 唯一真实实现：委托 `AfterSaleService::installationServiceCancelNotify(...)` | 由被委托方写 80 →（有退款单）31 / 失败 35 | 参数键 `after_sale_data`、`success`、`failed_reason`、`operator` |

补充：类内 4 个 import 完全未使用（`AfterSaleStatusEnum`、`AfterSaleGoodsService`、`AfterSaleLogService`、`AuditService`），像是从完整实现裁剪后的残留。

### 2.5 Reissue 逐方法（25 个）

#### 生命周期钩子（10 个）

| 方法 | 行号 | 行为 | 状态/支付 | 风险/备注 |
|---|---|---|---|---|
| `apply` | [L49](./Reissue.php#L49) | `ManageApplyContext` + 节点链：OrderRelatedData → `applyCheck` → `applyContextValueSet` → GoodsInsertDataFormat → InsertDataFormat → SaveData | 初态由 `applyContextValueSet` 决定（0 或 7） | 支付节点 `reissuePaymentHandle` 在 L66 被注释 |
| `temporaryData` | [L76](./Reissue.php#L76) | 同上，多 `GetAfterSaleDataNode` + `UpdateDataNode` | 暂存固定 7 | 支付节点 L97 被注释 |
| `submitConfirm` | [L108](./Reissue.php#L108) | 同上，用 `submitConfirmContextValueSet` | 固定 0 | 支付节点 L128 被注释 |
| `auditPass` | [L138](./Reissue.php#L138) | `AuditContext` + GetAuditData → OrderRelatedData → `auditCheckStatus` → `auditPassHandle` | `auditPassHandle` 推 AMS 补发单后写 **91** | 无 try/catch；AMS 成功但 DB 失败会不一致 |
| `auditReject` | [L165](./Reissue.php#L165) | GetAuditData → OrderRelatedData → `auditCheckStatus` → `AuditService::auditReject` | 由 AuditService 决定 | 未设置 `handleAction` |
| `cancel` | [L188](./Reissue.php#L188) | GetAuditData → `cancelStatusCheck` → `reissueCancelHandle` | 无需退款 → **4**；需退款 → **31** | 状态白名单 35/0/10/70/7 |
| `paidNotify` | [L210](./Reissue.php#L210) | 校验状态必须是 10 → 写 16 + `is_paid`/`pay_at` → `createReissueOrder` → `doneHandle` | 10 → **16** → 完结 | 更新与建单无事务；建单失败状态停在 16 |
| `refundNotify` | [L247](./Reissue.php#L247) | 校验状态必须是 31 → 写 **4** + `refunded_at` → 发 MQ（状态传 **DONE**） | 31 → 4（DB）/ MQ 报 6 | ⚠️ DB 与 MQ 状态语义不一致 |
| `toOmsOrderNotify` | [L280](./Reissue.php#L280) | `returnError(404, 'no action')` | 无 | 唯一占位钩子 |
| `amsNotify` | [L290](./Reissue.php#L290) | 按 `reissue_status` 分发 `amsPass` / `amsReject` | 见下 | ReturnGoodsRefund 没有这个钩子 |

#### 申请/上下文辅助（5 个）

| 方法 | 行号 | 行为 | 关键点 |
|---|---|---|---|
| `applyCheck` | [L318](./Reissue.php#L318) | 五类校验：支付金额复算（31102）、售后原因是否支持补发、商品发货状态（31103）、重复补发（31104）、整套补发次日达（31107）；补发场景强制 `applyRefundAmount=0` | 金额用 `!=` 比较 bcmath 字符串；`modifyContent` 为空时 L410 可能 undefined index；重复校验是 check-then-act 无锁 |
| `applyContextValueSet` | [L434](./Reissue.php#L434) | 生成 `afterSaleNo`、写汇率、`initStatus` = 暂存 7 / 申请 0 | 未调用 `setNextStatus` |
| `submitConfirmContextValueSet` | [L461](./Reissue.php#L461) | 写汇率、`initStatus` 固定 0 | L467-471 的“沿用已支付单”逻辑已注释 |
| `exchangeRateSet` | [L610](./Reissue.php#L610) | 取订单币种到 usd/cny 汇率写入 context | 汇率缺失会放大金额异常 |
| `setNextStatus` | [L958](./Reissue.php#L958) | 重提场景下把 `initStatus` 改为 10（待支付） | **本文件内无调用点** |

#### 支付/审核/取消/AMS 辅助（10 个）

| 方法 | 行号 | 行为 | 关键点 |
|---|---|---|---|
| `reissuePaymentHandle` | [L482](./Reissue.php#L482) | 按 `pay_payment_no`/`pay_at`/`pay_amount` 决定 0 或 10，必要时 `createPayment`，事务内回写状态 + 日志 9101 | **L66/L97/L128 三处调用全被注释，当前是死代码**；L518 条件疑似写反 |
| `createPayment` | [L571](./Reissue.php#L571) | `PayInternal::paymentCreate(site, currency, pay_amount, afterSaleNo, orderNo, 1)` | 在事务外，失败会留孤儿支付单 |
| `createReissuePayment` | [L590](./Reissue.php#L590) | 同上的纯参数版本，仅 `amsPass` 使用 | — |
| `auditCheckStatus` | [L617](./Reissue.php#L617) | 只允许状态 0（待审核），否则抛 9004 | 审核前置状态机 |
| `auditPassHandle` | [L626](./Reissue.php#L626) | 取地址/原因/支付单 → `AmsApi::pushReissueOrder` → 校验补发单号 → 事务内回写 `reissue_order_no/id` 并写 **91** | AMS 推单在事务外；L712 `//todo 调用取消amd补发单` 承认缺补偿 |
| `cancelStatusCheck` | [L719](./Reissue.php#L719) | 允许 35/0/10/70/7，否则抛 2030049 | 取消前置状态机 |
| `reissueCancelHandle` | [L732](./Reissue.php#L732) | 状态 10 先 `paymentCancel`；`pay_amount==0` 或无 `pay_at` → **4**，否则 `createdRefund` → **31**；事务内更新 + 日志 9104 | `paymentCancel` 返回值未检查 |
| `amsPass` | [L807](./Reissue.php#L807) | 要求状态 91 → `createReissuePayment`；已支付 → 写 **16** + `createReissueOrder` + `doneHandle`；未支付 → 写 **10** | 支付单先建、状态后写；重复回调会重复建单 |
| `createReissueOrder` | [L880](./Reissue.php#L880) | `AmsApi::getReissueOrderInfo` + `OrderService::createReissueOrder(...)` 生成商城补发订单 | 15 个位置参数；无幂等，重复调用会重复建单 |
| `amsReject` | [L917](./Reissue.php#L917) | 事务内写 **35** + 日志 9107 | 无前置状态校验，重复回调会重复写 |

### 2.6 工厂与映射：谁在名单里

**分流开关在 `AfterSaleService`，不在工厂内部**（源码可确认）。全文 7 处 `getConcreteClass`：

| 入口 | 行号 | 是否受名单保护 | 调用钩子 |
|---|---|---|---|
| `manageApply` | [L280](./AfterSaleService.php#L280) | 是 | `apply()` |
| `submitAuditOrTemporaryData` | [L580](./AfterSaleService.php#L580) | 是 | `temporaryData()` / `submitConfirm()` |
| `auditPass` | [L1054](./AfterSaleService.php#L1054) | 是 | `auditPass()` |
| `auditReject` | [L1101](./AfterSaleService.php#L1101) | 是 | `auditReject()` |
| `cancelAfterSaleOrder` | [L1225](./AfterSaleService.php#L1225) | 是 | `cancel()` |
| `modifyOrderNotice` | [L1496](./AfterSaleService.php#L1496) | **否**（硬编码 `MODIFY_INSTALLATION_SERVICE`） | `toOmsOrderNotify()` |
| `installServiceCancelNotify` | [L3094](./AfterSaleService.php#L3094) | **否**（按售后单自身 `processing_type`） | `installationServiceCancelNotify()` |

受保护的 5 处条件都是：

```php
if (in_array($processing_type, \Yii::$app->params['processing_through_the_factory_class'])) {
    $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($processing_type);
    $result = $handleClass->{hook}();
} else {
    // Context + NodeExecutionEngine
}
```

**指南记录的名单**（[06-aftersale-service.md §5.4](../06-aftersale-service.md#L358)，配置文件 `bm-aftersale-api/config/params.php` 本地缺失）：

```php
'processing_through_the_factory_class' => [
    AfterSaleProcessTypeEnum::REISSUE,                    // 71 补发
    AfterSaleProcessTypeEnum::PROTECTION,                 // 72 保养
    AfterSaleProcessTypeEnum::TAX_REBATE,                 // 73 退税费
    AfterSaleProcessTypeEnum::OFFSET_ORDER_REFUND,        // 69 补差单退款
    AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU,       // 74 非销售SKU换货
    AfterSaleProcessTypeEnum::REFUND_AFTER_SALE_PAYMENT,  // 75 退售后款项
    AfterSaleProcessTypeEnum::MODIFY_INSTALLATION_SERVICE,// 76 修改安装服务
],
```

结论：

- `REISSUE` 在名单内 → 后台申请/提交审核/审核/取消都走 `Reissue`；但 **C 端 `clientApply` 与 `selfExchangeApply` 没有工厂分流**，补发的 C 端申请仍走节点链。
- `RETURN_GOODS_REFUND` **不在名单内** → 63 的申请/审核/取消主流程走 Node 链（`manageApply` 的 default 全链、`submitAuditOrTemporaryData` 的 17 节点链、审核的 3/4 节点链）。
- 但 `installServiceCancelNotify` 不查名单，63 单仍会命中 `ReturnGoodsRefund::installationServiceCancelNotify()`。所以“不在名单”只影响那 5 个受保护分流点。
- 工厂内部按 `concrete/*.php` 的 `processingTypeValue` 反射匹配（指南 L373-375/L384）；`AfterSaleHandleService.php` 本地缺失，扫描范围、未命中行为、是否缓存都无法确认。进阶指南也把“动态扫描 + 反射”列为性能/测试/静态分析上的技术债。

### 2.7 Reissue 核心链路（补发）

```text
后台申请 apply
  OrderRelatedData → applyCheck（金额/原因/发货/重复/次日达）
  → applyContextValueSet（单号/汇率/initStatus=0）
  → GoodsInsertDataFormat → InsertDataFormat → SaveData
  （reissuePaymentHandle 被注释，不建支付单）

提交审核 submitConfirm
  → ... → submitConfirmContextValueSet（initStatus=0）→ UpdateData

审核通过 auditPass
  → auditCheckStatus（必须 0）→ auditPassHandle
  → AmsApi::pushReissueOrder 建 AMS 补发单
  → 事务回写 reissue_order_no/id，状态 → 91 待生成补发订单

AMS 回调 amsNotify
  ├─ pass → amsPass：createReissuePayment
  │     ├─ 已支付 → 写 16 → createReissueOrder（商城补发单）→ doneHandle（完结）
  │     └─ 未支付 → 写 10 待支付
  └─ reject → amsReject：写 35 待客服介入

支付回调 paidNotify（状态必须 10）
  → 写 16 + is_paid/pay_at → createReissueOrder → doneHandle

取消 cancel
  → cancelStatusCheck（35/0/10/70/7）
  → reissueCancelHandle
      ├─ 状态 10：先 paymentCancel
      ├─ pay_amount==0 或无 pay_at → 写 4 已取消
      └─ 已支付 → createdRefund → 写 31 退款中

退款回调 refundNotify（状态必须 31）
  → 写 4 已取消 + refunded_at
  → MQ 上报 DONE(6)「补发售后完成」   ⚠️ 与 DB 状态不一致
```

### 2.8 风险清单

| 级别 | 风险 | 证据 |
|---|---|---|
| High | `ReturnGoodsRefund` 是空壳：9/10 钩子返回 404。若某环境把 63 加进工厂名单，退货退款的申请/审核/取消会直接失败 | ReturnGoodsRefund L29-L95；名单见指南 L358-366 |
| High | `auditPassHandle` 先推 AMS 补发单、后开事务写库；DB 失败会留下“AMS 有单、售后单没更新”的不一致，代码只留 TODO | Reissue L656-716（L712 `//todo 调用取消amd补发单`） |
| High | `paidNotify` / `amsPass` 先改状态或先建支付单，再创建商城补发订单；中途失败不回滚，重试可能重复建单/重复支付 | Reissue L224-244、L817-851、L880-910 |
| High | `refundNotify` 把 DB 状态写成 4（已取消），却向 MQ 上报 DONE(6)「补发售后完成」，下游状态语义不一致 | Reissue L256 vs L266 |
| Medium | `reissuePaymentHandle` 是死代码（三处调用被注释），`setNextStatus` 无调用点；阅读时容易误以为补发申请会建支付单 | Reissue L66/L97/L128、L958 |
| Medium | `applyCheck` 用 `!=` 比较 bcmath 字符串金额；`modifyContent` 为空时 L410 可能 undefined index；重复补发校验是 check-then-act，无锁 | Reissue L332、L410、L386-408 |
| Medium | `createReissueOrder` 用 15 个位置参数调 `OrderService::createReissueOrder`，且无幂等键 | Reissue L903-905 |
| Medium | `reissueCancelHandle` 调 `paymentCancel` 不检查返回值；`amsReject` 无前置状态校验，重复回调会重复写 | Reissue L736-738、L917-950 |
| Low | 两个类都有未使用 import（ReturnGoodsRefund 4 个、Reissue 的 `RefundCreateParams`），说明是裁剪快照 | ReturnGoodsRefund L10-L14；Reissue L39 |
| Low | 潜在 fatal：`Reissue` 没有 `installationServiceCancelNotify()`，若基类没有默认实现，对补发单调用该入口会触发未定义方法 | Reissue 全文 0 次出现；AfterSaleService L3094 直调 |

### 2.9 新增一个 processingType 的 checklist

1. **枚举**：`AfterSaleProcessTypeEnum` 增加常量与 `$list`（必要时 `$msgType`、`$refundProcessTypeList`）。
2. **策略类**：`concrete/Xxx.php`，`extends ProcessingType`，设置 `processingTypeValue` 与 `processingTypeName`。
3. **决定路径**：走工厂 → 加入 `params.php` 的 `processing_through_the_factory_class`；不走工厂 → 在 `AfterSaleService::getManageApplyNodeChain()` 增加 `case`，否则落到 default 全链（含 OMS 指令 + 自动审核）。
4. **实现钩子**：至少 `apply` / `temporaryData` / `submitConfirm` / `auditPass` / `auditReject` / `cancel`；按需 `paidNotify` / `refundNotify` / `toOmsOrderNotify` / `installationServiceCancelNotify` / `amsNotify`；不支持的动作显式返回 `404 no action`。
5. **状态**：`AfterSaleStatusEnum` 补常量、`$list`、`$enList`，并加入 `getAllTypeStatusMapping()` 对应 `AfterSaleTypeEnum` 的集合（注意映射键是 `type`，不是 `processing_type`）。
6. **同步既有白名单/互斥表**：`checkRepeat` 互斥组、`checkSubmitAudit` 自动审核白名单、安装服务/运输险相关白名单、地址校验白名单（`MODIFY_ORDER_INFORMATION` / `REISSUE`）。
7. **DB 配置**：按需补 `after_sale_process_method` 等配置数据。
8. **外部时序**：确认与 OMS/AMS/支付的先后顺序（先拦截再改单、先建 AMS 单再回写、先退件审核再退款）。
9. **MQ/邮件/神策**：申请成功统一 `pushAfterSaleStatusChange`；争议上报与申请邮件目前只在节点链分支。
10. **表单与权限**：Controller/Form 层补校验；注意 C 端入口没有工厂分流。
11. **测试**：状态允许/拒绝迁移、金额方向、事务中途失败回滚、并发提交、重复回调、MQ 重投、终态不可倒退。
12. **验证注册**：`grep processingTypeValue` 确认类能被扫描到；确认新类放在 `concrete/` 顶层（子目录是否递归扫描未知）。

### 2.10 待确认（本地缺失）

- `ProcessingType.php`（基类）：`$params` 注入方式、`doneHandle` / `createdRefund` 语义、10 个钩子是否有默认实现（决定 2.8 最后一条是否真的是 fatal）。
- `AfterSaleHandleService.php`（工厂）：反射匹配细节、未命中行为、是否递归子目录。
- `AfterSaleProcessTypeEnum.php`：`RETURN_GOODS_REFUND=63`、`REISSUE=71` 等数值只来自指南，非源码。
- `bm-aftersale-api/config/params.php`：**最关键**——名单在 dev/test/prod 的真实值，以及是否有环境把 63 加进名单。
- 全部 Node/Context/Repository/API：退货退款 default 链的真实行为、状态 1/2/3/22/25/28 的写入点、`AuditHandleNode` 是否做状态机校验。
- 仓库无 tests 目录，以上结论均为静态阅读，无法用测试验证。

### 2.11 JS/Node 类比

```js
// 策略模式本身
const strategies = { reissue: new ReissueStrategy(), ... };
await strategies[processingType].apply(context);

// 但这个仓库多了一层“注册表”
const factoryList = ['reissue', 'protection', 'tax_rebate', ...]; // params.php
if (factoryList.includes(processingType)) {
  return strategies[processingType].apply(context);   // 工厂路径
}
return nodePipeline(context);                          // 节点链路径
```

关键差异：Node 里 `strategies[type]` 取不到会 `undefined` 报错；这里“类存在但没注册”会静默走另一条链，所以**必须先看注册表，再看实现类**。

---

## 3. 练习任务

### 练习 1：读 ReturnGoodsRefund

记录它的输入、核心方法、状态变化、退款触发点。

### 练习 2：读 Reissue

记录它与退货退款的差异。

### 练习 3：对比接口实现

完成策略对比表，说明新增售后类型如何扩展。

参考产出：[§2.2 策略对比表](./day02.md#L275)、[§2.9 新增 processingType checklist](./day02.md#L456)。

---

## 4. JS/Node.js 类比

- processingType ≈ switch 拆成 Strategy class
- Strategy class ≈ handler object
- processingType factory ≈ handlers map
- ReturnGoodsRefund ≈ refund strategy
- Reissue ≈ reissue shipment strategy

---

## 5. AI Review 提问

```text
我正在学习售后 processingType 策略模式。
我已经对比 ReturnGoodsRefund 和 Reissue，并整理了新增售后类型的扩展步骤。
请你检查：
1. 我对策略模式的理解是否正确？
2. ReturnGoodsRefund 和 Reissue 的职责边界是否清楚？
3. 新增类型时还需要改哪些配置、状态和权限？
4. 哪些逻辑不应该继续留在 AfterSaleService 大类里？
5. 与 Node handlers map 的类比是否准确？
```

---

## 6. 今日产出

- [ ] `ReturnGoodsRefund` 阅读笔记（[§2.4](./day02.md#L298)）
- [ ] `Reissue` 阅读笔记（[§2.5](./day02.md#L315)）
- [ ] 策略对比表（[§2.2](./day02.md#L275)）
- [ ] 新增售后类型 checklist（[§2.9](./day02.md#L456)）
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释策略模式
- [ ] 能说明 processingType 是什么
- [ ] 能对比 ReturnGoodsRefund 与 Reissue
- [ ] 能说明新增售后类型要改哪些地方
- [ ] 能用 Node handlers map 类比策略模式

---

## 8. 今日自测题

### 8.1 什么是 processingType？

参考答案：

> ✅ processingType 表示售后处理类型，例如退货退款、仅退款、补发、换货、维修。不同类型的业务处理逻辑差别很大，用它来区分走哪种处理流程。

---

### 8.2 策略模式的核心思想是什么？

参考答案：

> ✅ 同一个接口，不同实现。定义一个统一接口（如 `ProcessingTypeInterface`），每种售后类型写一个实现类，各自处理自己的逻辑，调用方按类型选择对应策略。

---

### 8.3 不用策略模式、全写在大 Service 里会有什么问题？

参考答案：

> ✅ Service 会因大量 `if/elseif` 越来越长；新增类型要改老代码，容易改坏已有类型；每个类型难以单独测试；状态流转逻辑散落在各分支里，维护成本高。

---

### 8.4 ReturnGoodsRefund 和 Reissue 的核心区别是什么？

参考答案：

> ✅ 退货退款需要用户寄回、仓库收货、再调用支付退款；补发通常不退款，而是创建补发任务/物流单由商家发货。前者核心风险是超额/重复退款，后者是重复补发和地址错误。

---

### 8.5 新增一个“维修”售后类型要做哪些扩展？

参考答案：

> ✅ 新增 processingType 常量、新建 `Repair` 策略类并实现统一接口、注册到工厂/映射表、补充状态流转规则、增加表单校验和权限校验、补测试。重点是尽量不改已有策略类。

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
我正在进行 Week 10 Day 02：processingType 策略模式 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 10 README](./README.md)
