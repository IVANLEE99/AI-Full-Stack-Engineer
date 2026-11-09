# Week 10 Day 01：AfterSaleService 概览

> 所属周：Week 10：售后服务 + Console 任务  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`aftersale-service`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

浏览售后核心 `AfterSaleService` 结构，理解售后域为什么同时连接用户、订单、支付、退款、物流、客服审核和 MQ 通知，并能列出 10 个 public 方法的职责。

今天你要真正掌握这一句话：

> 售后域不是简单“退款接口”，而是一条从用户申请、订单归属校验、审核、退货/退款/补发处理、支付退款、状态流转到通知用户的复杂业务链路。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 09 的 `user_id` 归属校验
2. 回顾 Week 06 的订单状态和 Week 07 的退款/支付状态
3. 理解售后域包含哪些业务类型
4. 打开 `AfterSaleService.php`，先看 public 方法列表
5. 按“申请、审核、处理、完成、取消、查询、通知”给方法分类
6. 记录每个方法的入参、出参和调用对象
7. 标注用户权限、订单归属、退款金额、凭证图片等风险点
8. 思考为什么大 Service 需要策略模式拆分
9. 用 AI Review 检查复杂度理解是否完整

---

## 1. 学习内容

### 1.1 售后域为什么复杂？

售后可能包含：

- 仅退款
- 退货退款
- 换货
- 补发
- 维修
- 取消售后
- 客服审核
- 仓库收货
- 支付退款
- 用户通知

它连接多个域：

```text
用户域：谁申请售后？
订单域：申请的是哪笔订单？
支付域：是否需要退款？
物流域：是否需要退货/补发？
权限域：谁能审核？
MQ：是否异步通知和处理？
```

小白重点：售后是多个业务域交叉点，所以 Service 很容易变大。

---

### 1.2 AfterSaleService 常见职责

| 职责 | 示例 |
|---|---|
| 创建售后申请 | 用户发起退款/退货/补发 |
| 校验订单归属 | 当前用户是否拥有该订单 |
| 校验可售后状态 | 订单是否已支付/已发货/可退款 |
| 计算可退金额 | 商品金额、运费、优惠、已退金额 |
| 审核售后 | 客服同意/拒绝 |
| 处理退货 | 填写退货物流、仓库收货 |
| 触发退款 | 售后通过后调用支付退款 |
| 更新状态 | 申请中、审核中、退款中、完成 |
| 发送通知 | 通知用户审核结果/退款进度 |
| 查询列表/详情 | 用户端、后台端不同视角 |

---

### 1.3 售后申请基础链路

```text
用户提交售后申请
  ↓
AfterSaleController
  ↓
参数校验 Form
  ↓
AfterSaleService::apply()
  ↓
校验 user_id 与订单归属
  ↓
校验订单/商品是否可售后
  ↓
校验退款金额和凭证图片
  ↓
创建售后单
  ↓
进入待审核状态
  ↓
发送通知或 MQ
```

---

### 1.4 必须关注的风险

| 风险 | 说明 |
|---|---|
| 越权申请 | A 用户申请 B 用户订单售后 |
| 重复售后 | 同一订单商品重复申请 |
| 超额退款 | 退款金额超过可退金额 |
| 状态错误 | 已完成订单被错误回退 |
| 凭证图片风险 | 上传恶意文件或泄露隐私 |
| 审核权限 | 普通用户不能调用审核接口 |
| 退款幂等 | 同一售后不能重复退款 |

---

### 1.5 为什么需要拆分策略？

不同售后类型处理方式不同：

| 类型 | 特点 |
|---|---|
| 仅退款 | 不需要退货物流，重点是退款金额 |
| 退货退款 | 需要用户寄回、仓库收货、再退款 |
| 补发 | 不退款，创建补发/物流任务 |
| 换货 | 可能同时有退回和重新发货 |

如果全部写在 `AfterSaleService` 里，会出现大量 `if processingType == ...`。

策略模式可以把每种类型拆到独立类中。

---

### 1.6 阅读 public 方法的方法

请用表格记录：

| 方法 | 类型 | 入参 | 返回 | 职责 | 风险 |
|---|---|---|---|---|---|
|  | 申请 |  |  |  |  |
|  | 审核 |  |  |  |  |
|  | 查询 |  |  |  |  |

先建立地图，再深入每个方法。

---

## 2. 源码阅读

- `aftersale-service/common/services/AfterSaleService.php`

> 说明：路径均为公开代号 + 相对路径。本地参考源码见 [AfterSaleService.php](./AfterSaleService.php)、[AfterSaleStatusEnum.php](./AfterSaleStatusEnum.php)、[ReturnGoodsRefund.php](./ReturnGoodsRefund.php)、[OmsController.php](./OmsController.php)。以下行号均指本地参考源码。

阅读记录：

### 2.1 类概览：一个“编排型上帝 Service”

| 维度 | 结论 |
|---|---|
| 规模 | 3875 行；71 个方法 = 61 个 public + 9 个 private + 1 个 protected；104 个 `use` 导入 |
| 继承/返回 | `class AfterSaleService extends \common\BaseService`（L110）；统一用 `returnSuccess/returnError/returnFormat` 返回 `{code,data,info}` |
| 核心职责 | 售后申请、审核、状态流转、OMS/支付回调、物流退货、金额/积分计算、运输险、安装服务、会员权益校验、埋点上报 |
| 两条执行路径 | ① `Context + NodeExecutionEngine` 节点链（全文 11 处 `executeEngine`）；② `processing_type` 命中 `Yii::$app->params['processing_through_the_factory_class']` 时，走 `AfterSaleHandleService::getConcreteClass()` 的具体策略类 |
| 分层位置 | Controller/Console → **AfterSaleService（编排）** → Context + Node → Repository/Internal API → MQ/邮件/OMS/神策 |
| 关键外部依赖 | `OrderInternal`、`UserInternal`、`PayInternal`、`GoodsInternal`、`MarketInternal`、`OperateInternal`、`AmsApi`、`WeChatApi`、`ToOmsOrderService`、`MqService`、`RabbitMq`、`RefundService`、`CancelOrderService`、`AuditService` |
| 类内防护 | 全文只有 **1 处显式事务**（`designatedHandler` L706-731）；没有 `forUpdate`、锁或版本号；`user_id` 只在 L2500 出现一次（查用户资料），类内没有“当前用户 = 订单用户”的归属比对 |

一句话：**AfterSaleService 不是“售后业务本身”，而是售后域的编排层和回调落点**。校验、落库、状态写入大量下沉在 Node / Repository / 外部 Service 中；本文件能看到“谁在什么时候被调用”，但看不全“节点内部做了什么”。

> 阅读口径：下面按业务子域列出全部 61 个 public 方法（不止 Day 01 要求的 10 个），并单列 10 个 private/protected 辅助方法。`备注` 只写代码里能确认的事实；依赖缺失文件才能确认的，明确标“需结合 Node/配置确认”。

---

### 2.2 public 方法清单（61 个，按子域）

#### A. 申请提交（5 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [checkRepeat](./AfterSaleService.php#L123) | 申请前查重/互斥：同处理方案商品重叠、改单/改收货方式与仅退款冲突、五类退款方案互斥；换货累计退款数量达购买量则拒绝 | OrderInternal、AfterSaleRepository、AfterSaleGoodsRepository | 订单:是；支付:否；权限:否；MQ:否 | 纯读、无锁、无事务；本文件内无调用点；L158 读取 `goods_qty` 而回填字段是 `qty`，数量上限可能失效；错误码 42001 与翻译 key 43001 不一致 |
| [manageApply](./AfterSaleService.php#L268) | 后台申请入口：Premium 会员校验 → 地址校验 → 按配置走策略类 `apply()` 或 `ManageApplyContext` 节点链；成功发 MQ，节点链分支另发神策/邮件 | validateMemberAfterSale、AddressService、AfterSaleHandleService、NodeExecutionEngine、MqService、ReturnRefundEmail | 订单:是；支付:是（补差价链含 `CreatePaymentNode`）；权限:否；MQ:是 | L307 成功即提前 return，L311-321 金额计算日志在正常节点链下不执行；会员校验异常被吞后放行（L3869-3873）；本方法未调用 `checkRepeat` |
| [clientApply](./AfterSaleService.php#L449) | C 端申请：11 节点链（订单 → 预格式化 → 校验 → 售后原因 → 主表/商品组装 → 落库 → 返回 → 埋点参数 → OMS → 自动审核），成功后发 MQ | ClientApplyContext、OrderRelatedDataNode 等 11 个 Node、MqService | 订单:是；支付:否；权限:否（归属校验应在 Controller/CheckNode）；MQ:是 | 落库后发 MQ 无 try/catch；神策上报代码 L483-490 已注释；本方法内无 user_id/订单归属校验 |
| [selfExchangeApply](./AfterSaleService.php#L503) | 自主换货：强制 `processing_type=EXCHANGE_GOODS`，8 节点链（少售后原因/埋点/自动审核），成功后发 MQ | ClientApplyContext、SelfExchangeGoodsInsertDataFormatNode 等 8 个 Node、MqService | 订单:是；支付:否；权限:否；MQ:是 | 与 `clientApply` 对比是理解换货子流程的入口；同样无幂等/归属校验 |
| [convertData](./AfterSaleService.php#L2702) | 客户端参数转后端申请结构：拉订单商品、组装申请商品/地址、计算退款金额、token 换用户 | OrderInternal、AfterSaleQueryService、UserInternal、AfterSaleConsultLabelRepository | 订单:是；支付:否；权限:否；MQ:否 | qty 可负/超量；订单接口失败仍继续；`array_merge` 保留客户端任意键；processing_type 未校验合法性 |

#### B. 审核（6 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [submitAuditOrTemporaryData](./AfterSaleService.php#L569) | 提交审核/暂存统一入口：争议单拦截 → 策略类（temporaryData/submitConfirm）或 17 节点链（校验/更新/税费/仓库 SKU/取消拦截/补差/增值服务/改单/邮件）；条件重投自动审核 MQ | AfterSaleHandleService、SubmitAuditOrTemporaryDataContext、17 个 Node、RefundsService、AutoSubmitAuditNode | 订单:是；支付:是；权限:否；MQ:是（条件重投） | 含支付单/退款单副作用但无统一事务；L655 魔法错误码 79469812；L633-646 AMS SKU 日志未捕获异常 |
| [auditPass](./AfterSaleService.php#L1047) | 审核通过：仅退款 → `CancelOrderService::pass`；工厂类 → 策略 `auditPass()`；其余 → `AuditContext` + 4 节点链（含 PaymentGetNode） | CancelOrderService、AfterSaleHandleService、AuditContext、GetAuditDataNode、PaymentGetNode、AuditCheckNode、AuditHandleNode | 订单:节点内；支付:是；权限:否；MQ:节点内 | 未判空售后单；无事务/幂等；仅退款与工厂分支绕过通用校验；无 try/catch |
| [auditReject](./AfterSaleService.php#L1094) | 审核拒绝：仅退款 → `CancelOrderService::reject`；工厂类 → 策略；其余 → 3 节点链（刻意无 PaymentGetNode） | 同上（无 PaymentGetNode） | 订单:节点内；支付:否/节点内；权限:否；MQ:节点内 | 与 `auditPass` 不对称：未设置 `$context->status`；未判空；无 try/catch |
| [auditHighPass](./AfterSaleService.php#L1138) | 大额审核通过：3 节点链（GetAuditData → AuditCheck → AuditHandle），带 try/catch | AuditContext、GetAuditDataNode、AuditCheckNode、AuditHandleNode | 订单:节点内；支付:否（本方法链内无支付节点）；权限:否；MQ:节点内 | 没有 ONLY_REFUND/工厂类前置分流，可能绕过取消订单专用逻辑；异常堆栈回传调用方；只 catch `\Exception` |
| [auditHighReject](./AfterSaleService.php#L1173) | 大额审核拒绝：仅退款 → `CancelOrderService::largeAmountReject`；其余 → 3 节点链，带 try/catch | AfterSaleRepository、CancelOrderService、AuditContext、3 个 Node | 订单:节点内；支付:仅退款分支（外部服务）；权限:否；MQ:节点内 | 与 `auditHighPass` 不对称（只有 reject 有 ONLY_REFUND 分支）；catch 不写日志且回传堆栈；未判空 |
| [checkSubmitAudit](./AfterSaleService.php#L1803) | 自动审核前置校验：售后单存在、非争议单、处理方案白名单、非退货退款还需 OMS 拦截结果通过 | AfterSaleRepository、ToOmsOrderService | 订单:是；支付:否；权限:否；MQ:否 | `!$interceptResult` 把空/失败都当失败；异常 code=0 语义不明；查询在 try 之外 |

#### C. 状态流转（7 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [designatedHandler](./AfterSaleService.php#L683) | 批量指派客服：校验单号/操作人 → 查 status=40 → 事务内改为 41、写处理人、批量日志 | AfterSaleRepository（事务）、AfterSaleLogRepository | 订单:否；支付:否；权限:是（改归属，但不校验调用者）；MQ:否 | 全类唯一显式事务（L706-731）；先查后改且 update 不再带 status 条件，并发可重复指派/丢失更新；operator 完全信任入参 |
| [valueAddedServiceHandle](./AfterSaleService.php#L744) | 增值服务变更同步 OMS：解析 processing_content，取消/新增两次 `pushOmsOrder` | AfterSaleRepository、AfterSaleGoodsRepository、ToOmsOrderService | 订单:是；支付:否；权限:否；MQ:否 | L750 先解码后判空；cancel 成功 add 失败无补偿；无幂等；docblock 参数已过期 |
| [modifyOrderInfo](./AfterSaleService.php#L867) | 按 processing_content.code 推 OMS 改单指令：改配送/地址/发货时间/仓库/自提/白手套，含运费税费换算；改自提回写商城订单 | ToOmsOrderService、OrderInternal、ToOmsOrderRepository、getFormatInterceptOrderGoodsData | 订单:是；支付:否；权限:否；MQ:否 | 无幂等；OMS 成功与商城回写无事务且忽略回写结果；汇率缺失会算出 0；switch 无 default |
| [statusChangeHandle](./AfterSaleService.php#L2240) | MQ 状态变更后的副作用：DONE 触发税费 MQ/次日达邮件/优惠券回滚/Plus 会员与权益入队；CANCEL 触发好评返现通知；最后统一神策埋点 | getAfterSaleRelateData、EstimateTaxService、MarketInternal、MemberAfterSaleService、SensorsService | 订单:是；支付:是（读支付单/税费）；权限:否；MQ:是 | 无幂等、无库内状态校验，MQ 重投会重复执行税费/退券/会员入队；三个会员钩子吞异常；未判空售后单 |
| [updateAfterSale](./AfterSaleService.php#L3029) | 按售后单号局部更新 status/支付/退款/换货/补发字段，固定刷新 updated_at | AfterSaleRepository | 订单:否（按售后单号）；支付:是（可写支付退款字段）；权限:否；MQ:否 | 任意调用方可写任意 status，无状态机/乐观锁；`empty(updateResult)` 把 0 行当失败；10 个可选位置参数，调用点多 null 占位，易错位 |
| [checkRollbackCouponUser](./AfterSaleService.php#L2320) | DONE 时对改单/改收货方式且 `new_address_fee_info.rollback_coupon_user` 非空的售后回退优惠券 | MarketInternal | 订单:是；支付:否；权限:否；MQ:否 | 无幂等；异常未捕获会中断 `statusChangeHandle` 后续埋点；只覆盖两种处理方案 |
| [sendToOmsModifyOrderInfo](./AfterSaleService.php#L3122) | 调 `installServiceHandle` 推 OMS，随后无条件把状态改为 SYNC_OMS_ING 并写日志 | installServiceHandle、AfterSaleRepository、AfterSaleLogRepository | 订单:是；支付:否；权限:否；MQ:否 | 推送失败不阻止状态改 55；L3143 `$params` 未定义，日志操作人恒空；成功路径隐式返回 null |

#### D. 取消（1 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [cancelAfterSaleOrder](./AfterSaleService.php#L1211) | 关闭售后：已取消拒绝 → OMS 拦截未返回拒绝 → 仅退款/工厂/3 节点链分流取消 | AfterSaleRepository、AfterSaleAuditCheckService、CancelOrderService、AfterSaleHandleService、AuditContext | 订单:节点内；支付:仅退款分支；权限:否；MQ:节点内 | 先查后做无锁，存在 TOCTOU；只拦 CANCEL 状态；OMS 不回调会一直阻塞关闭 |

#### E. 通知回调（8 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [interceptNotice](./AfterSaleService.php#L1269) | OMS 修改订单/收货方式拦截结果回调：其他工单未完成则跳过；核对 SKU 数量；成功 → 70/7/保持 0，失败 → 35（终态保持）并发企微 | ToOmsOrderRepository、AuditService、WeChatApi、ModifyReceiptMethodService | 订单:否；支付:否；权限:否；MQ:否 | L1334 使用未定义 `$params['message']`，企微通知永远为空；只遍历回调中出现的 SKU；switch 只覆盖两种处理方案；成功码 2 与 `cancelInterceptNotice` 的 1 不一致 |
| [cancelInterceptNotice](./AfterSaleService.php#L1361) | OMS 取消拦截结果回调：成功按日志前态转 4/6，失败 → 35（终态保持），DONE 时通知订单/处理运输险/发邮件，并发 MQ | AfterSaleLogRepository、AuditService、MqService、OrderInternal、ConfigHelper、EmailService、WeChatApi、ModifyReceiptMethodService | 订单:是；支付:否；权限:否；MQ:是 | “最后一条日志”依赖 Repository 默认排序，日志缺失会访问 null；终态保护只覆盖 CANCEL/DONE；写库后发 MQ/邮件无事务；失败分支把 50 改成 4 |
| [modifyOrderNotice](./AfterSaleService.php#L1475) | OMS 改单工单回调：安装服务/运输险交给策略类；成功按 code 改地址/发邮件/发 RabbitMQ/建退款单（→31）或发起取消拦截（→55）；失败 → 35 | AfterSaleHandleService、OrderInternal、RefundService、OmsService、AuditService、EmailService、RabbitMq | 订单:是；支付:是；权限:否；MQ:是 | L1626 计算了 `$newStatus` 却硬编码 `CUSTOMER_SERVICE_REVIEW`，终态保护失效（确认 bug）；L1508 只按 command_code 查工单；退款/改单/状态无事务 |
| [comfirmCancelOrder](./AfterSaleService.php#L1645) | OMS 确认取消订单回调：仅退款 → CancelOrderService；成功 → DONE、通知订单、取消运输险、发 MQ；失败 → 35 | CancelOrderService、cancelInstallationServiceOrder、AuditService、OrderInternal、MqService、WeChatApi | 订单:是；支付:是（仅退款分支）；权限:否；MQ:是 | 方法名拼写为 `comfirm`；L1666-1713 的“1 小时整单取消”分支因 L1650 提前 return 而不可达；重复回调会重复通知订单/发 MQ；未判空 |
| [afterSaleNotifyOrderModifiyProtection](./AfterSaleService.php#L2180) | 保障计划取消回调：按订单聚合所有售后单的保障申请数量，调 OrderInternal 取消保障计划并写日志 | AfterSaleRepository、AfterSaleGoodsRepository、OrderInternal、AfterSaleLogRepository | 订单:是；支付:否；权限:否；MQ:否 | 聚合未过滤售后状态，已取消单也计入，可能多取消；无幂等；方法名拼写 `Modifiy` |
| [installServiceCancelNotify](./AfterSaleService.php#L3081) | 安装服务取消结果入口：组装 after_sale_data/success/failed_reason/operator，交给策略类 `installationServiceCancelNotify()` | AfterSaleHandleService、ReturnGoodsRefund（本地唯一实现） | 订单:否；支付:是（下游建退款单）；权限:否；MQ:否 | status 未校验；无幂等，重复成功回调可能重复建退款；operator 直接信任入参 |
| [transportInsuranceCancelNotify](./AfterSaleService.php#L3104) | 运输险取消回调：组装固定 operator，交给 `CancelTransportInsurance::serviceCancelNotify` | CancelTransportInsurance | 订单:是；支付:否；权限:否；MQ:否 | 无签名/来源校验；status 无枚举校验；无幂等 |
| [installationServiceCancelNotify](./AfterSaleService.php#L3251) | 安装服务取消结果落状态：成功 → 80 并建退款单 → 31；失败 → 35 并记原因 | updateAfterSale、AfterSaleLogService、AfterSaleGoodsService、AuditService | 订单:否；支付:是（建退款单）；权限:否；MQ:否 | 无幂等/无前置状态校验，重复成功会重复 `createdRefund`；状态更新与建退款单无事务；金额用入参快照 |

#### F. 物流退货（6 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [returnAddDelivery](./AfterSaleService.php#L1746) | 端侧补充退货物流：4 节点链（操作人 → 售后单 → 商品 → 写物流） | ReturnAddDeliveryContext、OperatorHandleNode、AfterSaleGetNode、AfterSaleGoodsGetNode、ReturnAddDeliveryNode | 订单:是；支付:否；权限:是（OperatorHandleNode，规则未见）；MQ:否 | 只 catch `\Exception`；异常堆栈拼进返回；节点内部事务/幂等不可见 |
| [returnReceivedReturn](./AfterSaleService.php#L1770) | 仓库确认收货：3 节点链（售后单 → 商品 → 确认收货） | ManageAuditContext、AfterSaleGetNode、AfterSaleGoodsGetNode、ReturnReceivedReturnNode | 订单:是；支付:否；权限:否（无 OperatorHandleNode）；MQ:否 | 与 `returnAddDelivery` 相比缺操作人节点；异常堆栈外泄 |
| [addDelivery](./AfterSaleService.php#L1838) | 直接插入退货物流 → 无条件把状态改为 2 → 写日志 → 写 Redis 7 天提醒 | AfterSaleDeliveryRepository、AfterSaleRepository、AfterSaleLogRepository、AfterSaleDeliveryRedis | 订单:是；支付:否；权限:否；MQ:否 | 无前置状态校验，可把 DONE/CANCEL 改回 2；重复调用插多条；四步无事务 |
| [saveDelivery](./AfterSaleService.php#L1878) | 校验状态 1/2 → AMS 查仓库 SKU 取交集 → DeliveryService 保存 → 改状态 2 → 日志 → AMS MQ → Redis 提醒 | AfterSaleRepository、AmsApi、AfterSaleGoodsWarehouseSkuRepository、DeliveryService、RabbitMq、AfterSaleDeliveryRedis | 订单:是；支付:否；权限:否；MQ:是 | 保存失败仍返回 success（L1952-1966）；重复提交重复发 MQ；L1941 硬编码内网 URL；连续两次查售后单 |
| [getDelivery](./AfterSaleService.php#L2831) | 组装退货物流页：退货记录/订单地址、退货方式、物流公司、仓库地址、预估运费、运单/凭证 | AfterSaleDeliveryRepository、AfterSaleRepository、OrderInternal、ReturnMethodEnum、TrackingCompany、ConfigService | 订单:是；支付:否；权限:否（依赖 Controller）；MQ:否 | 服务层无归属校验；id 为空时 `$afterSaleDelivery` 未定义（靠 `empty()` 兜底）；help article id 27 硬编码 |
| [getTrackInfo](./AfterSaleService.php#L2930) | 按 tracking_number 从 Redis 取轨迹，组装节点列表/官网链接/联系方式，返回商品首图与退货地址 | AfterSaleRepository、AfterSaleGoodsRepository、AfterSaleDeliveryRedis、TrackingCompany | 订单:否；支付:否；权限:否；MQ:否 | L2955 把 JSON 字符串当数组取 `logistics_company`，物流公司信息取不到；未校验运单号属于该售后单；商品为空会 notice |

#### G. 查询转换（8 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [getAfterSale](./AfterSaleService.php#L545) | 按 order_no + processing_type 查未取消售后单，按 after_sale_id 倒序，解码 processing_content | AfterSaleRepository | 订单:是；支付:否；权限:否；MQ:否 | 仅凭 order_no 查询，无归属校验；只排除 CANCEL；未显式分页 |
| [getCanApplyAfterSaleGoodsList](./AfterSaleService.php#L2572) | 计算每件商品当前可售后数量/金额：订单数量减已售后，金额 bcsub；带会员 allowed_solutions | getDoneRecordGroupByOrderGoodsId、MemberAfterSaleService、bcsub | 订单:是；支付:否；权限:否；MQ:否 | 已退金额可超支付金额变负；无锁，并发申请可同时拿到同一额度；L2591 `??` 优先级错误 |
| [getDoneRecordGroupByOrderGoodsId](./AfterSaleService.php#L2621) | 按 order_goods_id 汇总已售后数量和金额；整单未发货退款视为全退；换货按整件覆盖 | AfterSaleRepository、AfterSaleGoodsRepository、bcadd | 订单:是；支付:否；权限:否；MQ:否 | 换货是覆盖不是累加，多笔换货丢历史；未校验金额上限；双层遍历 |
| [getOneByAfterSaleNo](./AfterSaleService.php#L2988) | 透传 Repository 按售后单号取详情 | AfterSaleRepository | 订单:否；支付:否；权限:否；MQ:否 | 无参数/权限/脱敏；纯代理 |
| [getOneByOrderNo](./AfterSaleService.php#L2996) | 透传 Repository 按订单号取售后单，processingType 默认 0 | AfterSaleRepository | 订单:是；支付:否；权限:否；MQ:否 | processingType=0 语义隐式；多售后单返回哪条取决于仓储 |
| [isInAfterSaleByOrderGoodsId](./AfterSaleService.php#L3010) | 透传查询：某订单商品是否存在指定处理方案的进行中售后单 | AfterSaleRepository | 订单:是；支付:否；权限:否；MQ:否 | check-then-act 无锁；processingTypes 空集语义依赖仓储 |
| [getValidTransportInsurance](./AfterSaleService.php#L3540) | 透传取一条未取消的取消运输险售后单 | AfterSaleRepository | 订单:否；支付:否；权限:否；MQ:否 | “只返回一条”由仓储保证；纯代理 |
| [getCancelledTransportInsurance](./AfterSaleService.php#L3548) | 透传取已完成且处理方案为取消运输险的售后单 | AfterSaleRepository | 订单:否；支付:否；权限:否；MQ:否 | 本文件内无调用方；纯代理 |

#### H. 金额积分（7 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [calculateAfterSalePoints](./AfterSaleService.php#L2015) | 按订单商品计算可退件数/积分/扣减积分，按退款金额比例或件数比例分摊，扣历史已退 | AfterSaleGoodsRepository、AfterSalePointsCalculator、calculateCanDeductPoints、calculateCanPoints | 订单:是；支付:否；权限:否；MQ:否 | qty=-1 规范化后又被原始 -1 覆盖；clamp 结果不回写；历史扣减无 0 下限；纯积分行可能出负积分 |
| [calculateCanDeductPoints](./AfterSaleService.php#L2129) | 按退款金额/支付金额比例计算应扣奖励积分 | bcdiv/bcmul | 订单:否；支付:否；权限:否；MQ:否 | serviceAmount/taxAmount 未参与，与注释不符；payAmount=0 除零；直接截断 |
| [calculateCanPoints](./AfterSaleService.php#L2148) | 门面，委托 `AfterSalePointsCalculator::calculateCanPoints` | AfterSalePointsCalculator | 订单:否；支付:否；权限:否；MQ:否 | 逻辑在仓库外；无参数校验 |
| [calculateCanPointsByQty](./AfterSaleService.php#L2170) | 门面，按件数比例计算纯积分行可退积分 | AfterSalePointsCalculator | 订单:否；支付:否；权限:否；MQ:否 | 逻辑在仓库外；调用方可能传 -1 |
| [getRefundableInstallationAmount](./AfterSaleService.php#L3314) | 按剩余可取消件数 × 件均价，减去 24 小时内预约安装费，计算可退安装费 | bcmath、AfterSaleProcessTypeEnum | 订单:否；支付:否；权限:否；MQ:否 | qty=0 除零；bc 函数吃 float；可退件数与扣费件数口径不一致；白名单与 `isRequireCancelInstallationService` 不一致 |
| [getShippingFee](./AfterSaleService.php#L3351) | 组装商品 qty/delivery_channel，调 ExchangeCalculateService 算运费，只返回运费 | ExchangeCalculateService | 订单:否；支付:否；权限:否；MQ:否 | 商品键不存在会 notice；丢弃 skuDeliveryList |
| [getTransportInsuranceAmountDetail](./AfterSaleService.php#L3679) | 按 sku 汇总已退运输险，逐商品算最大可退；本次申请按金额占比分摊，汇总可退金额/税费并格式化 | AfterSaleGoodsService、MyFunction::amountSplit/formatPriceCurrencySymbol、bcmath | 订单:否；支付:否；权限:否；MQ:否 | 同 sku 多行会重复扣减；applyGoodsList 的 qty 未参与，部分退款也退整行；自定义金额未校验上限；税费只认 `us` |

#### I. 安装服务（4 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [installServiceHandle](./AfterSaleService.php#L805) | 安装服务变更同步 OMS：单商品校验 → 合并 old/new_service → 取消/新增两次 `pushOmsOrder` | AfterSaleRepository、AfterSaleGoodsRepository、ToOmsOrderService | 订单:是；支付:否；权限:否；MQ:否 | L811 先解码后判空；多商品拒绝但文案是 data null；old_service 合并了商品字段而 new_service 没有；两次外部调用无补偿 |
| [cancelInstallationServiceOrder](./AfterSaleService.php#L3155) | 判断是否需要取消安装服务；需要则调 GoodsInternal 取消，失败回写原状态并返回 4007015，成功等回调 | isRequireCancelInstallationService、GoodsInternal、updateAfterSale、AfterSaleLogService | 订单:是；支付:否；权限:否；MQ:否 | 成功不置 79，回调丢失会卡住；失败分支 update 只刷新时间且忽略返回值；未捕获异常 |
| [isRequireCancelInstallationService](./AfterSaleService.php#L3189) | 按处理方案白名单、订单安装服务行、售后商品匹配，组装 cancel_list 并返回 require_cancel | OrderInternal、AfterSaleGoodsService | 订单:是；支付:否；权限:否；MQ:否 | 取订单失败抛异常且调用方未 catch；qty 未校验 >0；未过滤安装服务单状态；白名单有重复项 |
| [getCancelInstallationCountGroup](./AfterSaleService.php#L3298) | 透传统计已取消/取消中安装服务分组数量 | AfterSaleRepository | 订单:否；支付:否；权限:否；MQ:否 | 纯透传；docblock 参数顺序与签名不一致 |

#### J. 运输险（5 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [cancelTransportInsuranceOmsHandle](./AfterSaleService.php#L784) | 组装取消运输险指令推 OMS，format 为空则静默成功 | AfterSaleRepository、AfterSaleGoodsRepository、ToOmsOrderService | 订单:是；支付:否；权限:否；MQ:否 | 无幂等；空结果返回成功掩盖数据缺失；异常未捕获 |
| [getCancelTransportInsuranceFlag](./AfterSaleService.php#L3375) | 按处理方案/改单 code/地址国家决定是否取消运输险；已取消数量达订单量则返回 0 | formatProcessingContent、ConfigHelper | 订单:否；支付:否；权限:否；MQ:否 | L3404 缺键会误判为需要取消；国家列表非严格 in_array；不校验本次申请数量；`isTruthyFlag/isFalsyFlag` 未被使用 |
| [isCanCancelTransportInsurance](./AfterSaleService.php#L3478) | 校验整单运输险能否取消：有险商品、发货状态、已有有效售后单、申请 SKU 数量是否恰好等于剩余 | getValidTransportInsurance、AfterSaleRepository、AfterSaleGoodsService | 订单:是；支付:否；权限:否；MQ:否 | 只遍历本次申请 SKU，未申请的有险 SKU 不校验；同 sku 多行覆盖；数量用 `!=` 宽松比较 |
| [cancelTransportInsuranceOrderGoods](./AfterSaleService.php#L3561) | DONE 售后单取消订单商品运输险；需要时另起取消运输险售后单 | getOneByAfterSaleNo、AfterSaleGoodsService、OrderInternal、CancelTransportInsurance | 订单:是；支付:否；权限:否；MQ:否 | 无幂等，重复调用重复取消/重复申请；catch Throwable 吞编程错误；二次申请失败仍返回 success |
| [modifyTransportInsuranceOrderAddress](./AfterSaleService.php#L3635) | 改订单运输险保单地址；无运输险则静默成功 | OrderInternal、AfterSaleLogService | 订单:是；支付:否；权限:否；MQ:否 | 日志状态硬编码 DONE；无法区分“已改/无需改”；无幂等 |

#### K. 会员校验与事件上报（2 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [checkMemberAfterSale](./AfterSaleService.php#L3768) | public 包装，委托 `validateMemberAfterSale` | validateMemberAfterSale | 订单:否；支付:否；权限:否；MQ:否 | 纯转发 |
| [pushAfterSaleEvent](./AfterSaleService.php#L2439) | 按事件名映射 action，查售后关联数据后上报神策；退款成功额外报 GA4 | SensorsEvent、getAfterSaleRelateData、pushSensorEvent、Ga4Service | 订单:是；支付:否；权限:否；MQ:否 | 售后单不存在会 notice；上报异常未捕获；无事件去重 |

#### L. 工具（2 个）

| public 方法 | 职责 | 调用对象 | 是否涉及订单/支付/权限 | 备注 |
|---|---|---|---|---|
| [getFormatInterceptOrderGoodsData](./AfterSaleService.php#L1027) | 把售后商品/工单映射为 OMS 三元组 sku_code/order_goods_id/qty；type=2 取 notice_qty | 无（纯数组转换） | 订单:是（只读）；支付:否；权限:否；MQ:否 | 缺键会 notice；type 只判断 `==2`；本质内部 helper 却是 public |
| [updateCustomerServiceRemark](./AfterSaleService.php#L1975) | 更新售后备注（receipt_remark 或 customer_service_remark），写旧值/新值日志 | AfterSaleRepository、AfterSaleLogRepository | 订单:是；支付:否；权限:否；MQ:否 | 不校验状态/操作人；updateField 任意值都落到 customer_service_remark；update+log 无事务 |

#### M. private / protected 辅助方法（10 个，不计入 61 个 public）

| 方法 | 可见性 | 职责 | 关键点 |
|---|---|---|---|
| [getManageApplyNodeChain](./AfterSaleService.php#L325) | private | 按 processingType 组装 5 条后台申请节点链 | default 兜底跑含 WarehouseSku + ToOmsOrder + AutoSubmitAudit 的全链；链手工复制，差异无声明 |
| [notifyTargetReviewCashbackIfNeeded](./AfterSaleService.php#L2343) | private | 好评返现结果通知运营 | 无幂等；catch 吞异常仅记日志 |
| [getExchangeOrderInfo](./AfterSaleService.php#L2374) | private | 换货订单/商品信息组装 | 同 sku 多记录会被 `array_column` 覆盖丢数量；接口空结果静默降级 |
| [getAfterSaleRelateData](./AfterSaleService.php#L2482) | private | 状态变更/埋点用的 15 元组关联数据总装 | 无判空；串行 4 次外部调用；位置元组契约脆弱 |
| [pushSensorEvent](./AfterSaleService.php#L2535) | private | 神策事件参数组装与上报 | `date_default_timezone_set('PRC')` 改进程时区；`@` 抑制解析错误；返回值不校验 |
| [getReturnAddress](./AfterSaleService.php#L2911) | private | 按 warehouse_type 取仓库退货地址 | 未知 warehouse_type 会 notice；每次全量查仓库列表 |
| [formatProcessingContent](./AfterSaleService.php#L3425) | private | processing_content JSON 字符串转数组 | 解析失败静默返回 `[]` |
| [isTruthyFlag](./AfterSaleService.php#L3434) | private | 多表示归一化真值 | 数字 2/-1 判 false；本文件无调用 |
| [isFalsyFlag](./AfterSaleService.php#L3449) | private | 多表示归一化假值 | 与 `isTruthyFlag` 非严格互补；本文件无调用 |
| [validateMemberAfterSale](./AfterSaleService.php#L3778) | protected | Premium 售后方案/免费权益校验 | catch 后 `returnSuccess()`，会员服务异常时 fail-open；只有 `manageApply` 调用，C 端入口不经过 |

---

### 2.3 核心执行链：Context + Node + 策略工厂

#### 2.3.1 用户端申请 `clientApply`（L449-494）

```text
OrderRelatedDataNode              // 拉订单、商品、地址
→ ClientPreDataFormatNode         // 入参预处理
→ ClientApplyCheckNode            // 申请资格/重复校验（防重逻辑在节点内）
→ ConsultLabelGetNode             // 三级售后原因
→ ClientInsertDataFormatNode      // 售后主表数据
→ ClientGoodsInsertDataFormatNode // 售后商品数据
→ SaveDataNode                    // 落库
→ ClientReturnNode                // 组装返回
→ AfterSaleSaParamGetNode         // 神策参数
→ ToOmsOrderNode                  // 向 OMS 发拦截/指令
→ AutoSubmitAuditNode             // 自动提交审核
→ MqService::pushAfterSaleStatusChange(...)   // 状态变更 MQ
```

学习点：`Context` 在节点间传递可变状态，节点只读写 context，不互相直接调用；这等价于 JS 里的 `pipeline`/中间件链，但节点有隐式顺序依赖。

#### 2.3.2 后台申请 `manageApply`（L268-323）

```text
validateMemberAfterSale → AddressService（仅改单/补发）
  ↓
processing_type ∈ processing_through_the_factory_class ?
  ├─ 是：AfterSaleHandleService::getConcreteClass(...)->apply()
  └─ 否：ManageApplyContext + getManageApplyNodeChain() 的节点链
```

`getManageApplyNodeChain` 的 5 条链（L325-440）：

| processingType | 节点链（按执行顺序） | 特点 |
|---|---|---|
| COMPENSATION（留单补偿） | OrderRelatedData → ManageApplyCheck → PreDataFormat → GoodsInsertDataFormat → InsertDataFormat → SaveData | 最短，不碰 OMS/审核 |
| OFFSET_ORDER（补差价） | 同上 + UpdateTaxDetail → CreatePayment | 会建支付单 |
| MODIFY_ORDER_INFORMATION | 同上 + UpdateTaxDetail → ToOmsOrder → AutoSubmitAudit | 改单指令 + 自动审核 |
| EXCHANGE_GOODS | OrderRelatedData → AfterSaleData → 校验/组装/SaveData → ToOmsOrder → AutoSubmitAudit | 多拉售后数据 |
| default | OrderRelatedData → AfterSaleData → 校验/组装/SaveData → UpdateTaxDetail → WarehouseSku → ToOmsOrder → AutoSubmitAudit | 兜底全链 |

风险：`default` 会把任何未显式匹配的 `processing_type` 都送进含 OMS 指令和自动审核的全链，新增枚举或配置漏配时容易越权下发。

#### 2.3.3 提交审核/暂存 `submitAuditOrTemporaryData`（L569-672）

```text
GetAfterSaleDataNode → OrderRelatedDataNode → AfterSaleDataNode → ToOmsOrderGetDataNode
→ DataCheckNode → PreDataFormatNode(子命名空间) → GoodsInsertDataFormatNode
→ InsertDataFormatNode → UpdateDataNode → UpdateTaxDetailNode → WarehouseSkuNode
→ CancelInterceptNode → OffSetHandleNode → ValueAddedServiceHandleNode
→ ModifyOrderHandleNode → TemporaryInterceptHandleNode → EmailSendNode
```

这条链同时覆盖“暂存数据”和“提交审核”两种 `handle_action`，并可能创建补差支付单、增值服务退款单、取消拦截工单、发邮件——副作用跨度最大。

#### 2.3.4 审核与物流链

```text
auditPass:   GetAuditData → PaymentGet → AuditCheck → AuditHandle
auditReject: GetAuditData → AuditCheck → AuditHandle
auditHigh*:  GetAuditData → AuditCheck → AuditHandle

returnAddDelivery: OperatorHandle → AfterSaleGet → AfterSaleGoodsGet → ReturnAddDelivery
returnReceivedReturn: AfterSaleGet → AfterSaleGoodsGet → ReturnReceivedReturn
```

#### 2.3.5 策略模式现状：工厂路径与节点链并存

`AfterSaleService` 在 5 个入口用同一个开关分流：

```php
if (in_array($processing_type, \Yii::$app->params['processing_through_the_factory_class'])) {
    $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($processing_type);
    $result = $handleClass->{apply|temporaryData|submitConfirm|auditPass|auditReject|cancel}();
} else {
    // Context + NodeExecutionEngine
}
```

本地参考的 [ReturnGoodsRefund.php](./ReturnGoodsRefund.php) 是具体策略类，但它 10 个钩子里 **9 个都是 `returnError(404, 'no action')`**，只有 `installationServiceCancelNotify()` 真正委托 `AfterSaleService::installationServiceCancelNotify()`。这说明：

- 策略类在此快照里更像“生命周期钩子壳”，真实退货退款逻辑仍在节点链/缺失的 Node 中；
- `RETURN_GOODS_REFUND` 是否在 `processing_through_the_factory_class` 配置里、`getConcreteClass` 如何映射，本地文件无法确认，**不能仅凭这个类判断线上走哪条路径**；
- 学习策略模式时应重点看“工厂 + 统一钩子契约”，而不是假设 `ReturnGoodsRefund` 承载全部退货业务。

`OmsController`（Console）与 `AfterSaleService` 的关系也容易误解：**OmsController 不直接调用 AfterSaleService**，它主要读写 `ToOmsOrder` 工单、调 `ToOmsOrderService` / `OmsService` 推 OMS（并读取售后单/售后商品作为入参）；OMS 执行后异步回调 `interceptNotice / cancelInterceptNotice / modifyOrderNotice / comfirmCancelOrder`，才由 Service 落售后状态。Console 是同步/人工补偿入口，回调才是状态机入口。

---

### 2.4 状态机：枚举只是词汇表，真实写入在 Service

#### 2.4.1 状态分组（`AfterSaleStatusEnum`，40 个常量）

| 分组 | 状态码 |
|---|---|
| 受理/审核 | 40 待分配、41 待处理、7 待确认售后内容、0 待审核、26 审核通过、24 审核拒绝、30 大额退款待审核、35 待客服介入 |
| 退款 | 31 退款中、32 退款失败、33 退款成功、34 待退款（枚举注释与 `$list` 文案冲突）、22 待退件审核、25 待退款审核、28 退款拒绝待修改 |
| 退货 | 1 待用户填写退货物流、2 待仓库收货、3 仓库已确认收货 |
| 换货/补差 | 8 待用户录入换货商品、9 待客服审核换货、10 待支付、16 支付成功、11 待用户确认换货、18 换货已确认、19 自主换货待确认 |
| OMS/终结 | 50 取消拦截中、55 同步 OMS 中、4 已取消（终结）、6 已完成（终结） |
| 改单 | 70 内容已确认、75 内容同步 OMS 中、73 OMS 已修改 |
| 补发/保养/安装 | 91 待生成补发订单、76 已创建保养订单、72 取消保养订单中、77 服务下单中、78 安装服务下单完成、79 待确认取消、80 服务已取消、81 取消安装服务失败 |

枚举自身的三处矛盾（读源码时要留意）：

1. `AUDIT_PASS = 26`，但常量注释写“21-审核通过”（L34-35）；
2. 34 在类注释/常量注释里是“退款成功”，在 `$list` 里是“待退款”（L14/L46/L148）；
3. 类注释写“1-退款审核通过”，而常量 1 实际是“退货退款_待用户发货”（L13/L50-51）。

#### 2.4.2 代码中可确认的真实写入

| 迁移 | 触发方法 | 证据 |
|---|---|---|
| 40 → 41 | `designatedHandler` | 先查 status=40（L697-701），事务内写 41（L709-715） |
| 任意状态 → 2 | `addDelivery` | 无前置状态校验，直接 `updateData` 为 2（L1856） |
| 1/2 → 2 | `saveDelivery` | 先校验状态白名单（L1894），成功后写 2（L1925-1927） |
| → 70 / 7 / 保持 0 | `interceptNotice` 成功 | 按 code/auto_complete 分支（L1311-1318），经 `changeAfterSataus` 写入（L1337） |
| → 35 | `interceptNotice` 失败 | L1324-1332 计算，L1337 写入 |
| → 4 / 6 | `cancelInterceptNotice` 成功 | 默认 4（L1394）；前态 ∈ {32,31,33,55,16,18} 时 6（L1396-1402）；L1419 写入 |
| 50 → 4；其他 → 35 | `cancelInterceptNotice` 失败 | L1452-1465 |
| → 31 / → 55 | `modifyOrderNotice` 成功 | 需退差则建退款单写 31（L1591-1600）；否则取消拦截写 55（L1603-1606） |
| → 35 | `modifyOrderNotice` 失败 | 计算 `$newStatus`（L1616-1624）但 L1626 硬编码 35（终态保护失效） |
| → 6 / 35 | `comfirmCancelOrder` | 成功写 6（L1716）；失败写 `$newStatus`（L1725-1735） |
| → 55 | `sendToOmsModifyOrderInfo` | L3130-3133 |
| → 80 / 31 / 35 | `installationServiceCancelNotify` | 成功 80（L3254/L3260），建退款单后 31（L3276），失败 35（L3257） |
| 任意 → 任意 | `updateAfterSale` | 只要 `$status !== null` 就写（L3029-3036, L3064） |

#### 2.4.3 只能“推测”、不能当事实的流转

- `0 → 26/24/30`：`auditPass/auditReject` 只设置 `context->handleAction`，真正写库在缺失的 `AuditHandleNode`；
- `2 → 3`：在缺失的 `ReturnReceivedReturnNode`；
- `22 → 25 → 28`、换货 `8 → 9 → 11 → 18`、补差 `10 → 16`：本文件没有写入点；
- `50`：本文件只读不写；
- `73/75/72/76/77/78/81`：本文件零引用；
- `79`：只出现在注释，没有真实写入。

结论：**不要把 `AfterSaleStatusEnum::getAllTypeStatusMapping()` 当成迁移表**。它是“某售后类型允许出现的状态集合”，不是顺序，也不保证真实写入；状态机必须结合 Node/Repository 才能补全。

---

### 2.5 风险清单（按严重程度）

> 口径：以下“确认”指在本地参考源码中直接可见；“条件风险”指 Service 层未见校验，但是否成立取决于 Controller/Node/配置。

| 级别 | 风险 | 证据与说明 |
|---|---|---|
| Critical | 越权/归属校验缺失 | `clientApply` 直接使用入参（L449-452）；`getAfterSale` 仅按 order_no 查询（L545-552）；`getDelivery` 按 after_sale_no/id 取地址与物流（L2831-2846）；`getTrackInfo` 按 after_sale_no 取轨迹（L2930-2937）；`addDelivery` 按入参写入并改状态（L1838-1856）。全文件 `user_id` 仅 L2500 用于查资料，token 仅 L2748 用于填 operator，**类内没有“token 用户 = 订单用户”的比对**。若上游 Controller/CheckNode 也没做，就是 IDOR。 |
| High | 重复售后/重复提交 | `checkRepeat` 定义在 L123，**本文件内无调用点**；`isInAfterSaleByOrderGoodsId` 仅透传（L3010-3012）；申请链路无幂等键；`addDelivery` 无条件 insert（L1853）并覆盖 status（L1856）；`statusChangeHandle` 无去重（L2240-2312）；`comfirmCancelOrder` 无 refund_no/status 幂等判断（L1714-1722）。 |
| High | 状态机绕过 | `updateAfterSale` 可写任意 status（L3029-3036）；`addDelivery` 可从任意状态改回 2（L1856）；`auditHighPass` 没有 ONLY_REFUND/工厂分流（L1138-1164），与 `auditPass` 不一致（L1051-1061）。 |
| High | 事务边界缺失 | 全文唯一显式事务在 `designatedHandler`（L706-731）。申请、审核、回调、物流、状态更新均为多表/多系统顺序写：`comfirmCancelOrder` 建退款单 → updateData → 写日志，异常仅记录（L1673-1713）；`saveDelivery` 改状态 → 发 MQ → 写 Redis 无事务（L1925-1950）；`addDelivery` 插物流 → 改状态 → 写 Redis 无事务（L1853-1874）。 |
| High | MQ 最终一致性无保障 | `pushAfterSaleStatusChange` 共 6 处（L291、L301、L481、L530、L1423、L1722），均未检查返回值；无 outbox/重试/补偿；DB 成功而 MQ 失败会导致下游状态永久缺失；MQ 重投又会重复执行 `statusChangeHandle` 的 DONE 副作用（税费 L2249、退券 L2255、会员入队 L2257-2297）。 |
| High | 超额退款/权益 | `RefundService::createdRefundByAfterSale` 直接使用库内 `refund_amount`（L1589）；申请入口不复算可退上限；`afterSaleNotifyOrderModifiyProtection` 聚合整单所有售后商品（含已取消单）的申请数量（L2189-2209），可能过量取消保障计划；`isCanCancelTransportInsurance` 只遍历本次申请 SKU（L3525-3533），未申请的带险 SKU 不会被发现。 |
| Medium | 巨型 Service / 双轨制 | 3875 行、71 个方法、104 个 `use`；同时存在工厂策略与节点链两套实现，`manageApply`、`submitAuditOrTemporaryData`、`auditPass`、`auditReject`、`cancelAfterSaleOrder` 都有两条路径，副作用集合不一致。 |
| Medium | 已确认的代码缺陷 | ① `manageApply` L307 提前 return，L311-321 金额日志在正常节点链下不执行；② `modifyOrderNotice` L1626 硬编码 35，`$newStatus` 终态保护失效；③ `comfirmCancelOrder` L1666-1713 分支不可达；④ `interceptNotice` L1334 用未定义 `$params['message']`；⑤ `saveDelivery` 保存失败仍返回 success（L1952-1966）；⑥ `getTrackInfo` L2955 把 JSON 字符串当数组；⑦ `sendToOmsModifyOrderInfo` L3143 用未定义 `$params`；⑧ `checkRepeat` L158 `goods_qty` 与 `qty` 字段不一致。 |
| Medium | 安全与信息泄露 | OMS 回调无签名/来源校验（L1269、L1361、L1475、L1645）；`modifyOrderInfo`、`saveDelivery` 硬编码内网 URL（L1941）；`auditHighPass/auditHighReject/returnAddDelivery` 把异常堆栈返回调用方（L1162、L1200、L1766）；`g_log_info` 打印完整 params/result。 |
| Medium | 可观测性/静默失败 | 多个 catch 只记日志或吞异常：`validateMemberAfterSale`（L3869-3873）、`statusChangeHandle` 会员钩子（L2257-2297）、`comfirmCancelOrder` 退款异常（L1701-1713）；`saveDelivery` 失败仍成功；`cancelTransportInsuranceOmsHandle` format 为空返回成功。 |
| Low | 魔法数字/拼写/文档漂移 | 日志码 7000 重复 6 次；自动审核重试码 79469812（L655）；错误码 4073925/4073926 在不同方法含义不同；AMS URL 硬编码；方法名 `comfirmCancelOrder`、`afterSaleNotifyOrderModifiyProtection` 拼写错误；`installServiceHandle` docblock 的 `$operator` 与签名不符；`getCancelInstallationCountGroup` docblock 参数顺序与签名相反。 |

---

### 2.6 10 个重点方法深读（已拆分）

> Day 01 练习要求“至少 10 个 public 方法，写清方法名、业务场景、输入、输出、风险”。完整深读已拆分为独立产出：[day01-10个方法深读.md](./day01-10个方法深读.md)。

| # | 方法 | 子域 | 一句话关注点 |
|---|---|---|---|
| 1 | `checkRepeat`（L123） | 申请提交 | 类内无调用点；字段名不一致可能让换货上限失效 |
| 2 | `manageApply`（L268） | 申请提交 | 成功提前 return，金额日志不执行；会员校验 fail-open |
| 3 | `clientApply`（L449） | 申请提交 | 无归属校验；落库后发 MQ 无异常保护 |
| 4 | `submitAuditOrTemporaryData`（L569） | 审核 | 17 节点含支付/退款副作用但无事务 |
| 5 | `auditPass`（L1047） | 审核 | 分支绕过通用校验；无 try/catch |
| 6 | `designatedHandler`（L683） | 状态流转 | 唯一事务；先查后改可重复指派 |
| 7 | `statusChangeHandle`（L2240） | 状态流转 | 无幂等，MQ 重投会重复税费/退券/会员 |
| 8 | `saveDelivery`（L1878） | 物流退货 | 失败仍返回 success；多步无事务 |
| 9 | `interceptNotice`（L1269） | 通知回调 | 未定义 `$params['message']`；无幂等 |
| 10 | `updateAfterSale`（L3029） | 状态流转 | 任意 status；无状态机/锁 |

---

### 2.7 JS/Node 类比与阅读结论

| PHP 侧 | JS/Node 等价物 | 差异 |
|---|---|---|
| `ClientApplyContext` + `NodeExecutionEngine` | pipeline / 中间件链（Koa、NestJS Interceptor） | Context 是共享可变对象，节点顺序有隐式依赖 |
| `AfterSaleHandleService::getConcreteClass()` | 策略 Map / 工厂函数 | 由 `processing_type` 配置决定走工厂还是节点链，双轨并存 |
| `AfterSaleRepository` / `*Internal` | DAO / RPC client | 返回值多为数组，字段契约靠约定 |
| `MqService::pushAfterSaleStatusChange` | EventEmitter / message queue | fire-and-forget，无 outbox/重试 |
| `interceptNotice` 等 | Webhook handler | 无签名/来源校验，幂等靠业务字段 |
| `AfterSaleService` 本身 | 一个 61 方法的 NestJS Service | 应按申请、审核、物流、退款、运输险拆子域 |

**阅读结论**：

1. 售后域复杂度的根源不是“退款”本身，而是**多域状态协同**：订单、支付、退款、物流、OMS、会员权益、优惠券、税费、埋点都要在同一条链上对齐。
2. 这个 Service 的核心价值是“编排 + 回调落点”，不是“业务规则全集”。读它要先画入口和节点链，再顺着 Node 往下看，否则会被 61 个方法淹没。
3. 最值得警惕的三件事：**归属校验在上层**、**状态写入没有统一状态机**、**跨系统副作用没有事务/幂等**。
4. 策略模式在这个项目里是“双轨制”：工厂路径与节点链并存，`ReturnGoodsRefund` 在当前快照里 9/10 钩子为空实现。新增售后类型时，先确认配置走哪条路径，再决定改策略类还是改节点链——这是 Week 10 Day 02 继续阅读的关键问题。

---

## 3. 练习任务

### 练习 1：浏览 AfterSaleService

记录类依赖、public 方法数量、核心方法。

### 练习 2：列 10 个 public 方法职责

至少写清：方法名、业务场景、输入、输出、风险。

参考产出：[day01-10个方法深读.md](./day01-10个方法深读.md)。

### 练习 3：画售后域依赖图

```text
AfterSaleService
  ├─ User / user_id
  ├─ Order
  ├─ Payment / Refund
  ├─ Logistics
  └─ MQ / Notification
```

---

## 4. JS/Node.js 类比

- 大 Service ≈ 需要拆分 use case / strategy
- AfterSaleService ≈ after-sales domain service
- user_id 校验 ≈ ownership guard
- 退款衔接 ≈ payment refund use case
- MQ 通知 ≈ event-driven notification

---

## 5. AI Review 提问

```text
我正在阅读 AfterSaleService。
我已经列出 10 个 public 方法职责，并整理了售后与用户、订单、支付、退款、物流、MQ 的关系。
请你检查：
1. 我对售后域复杂度的理解是否完整？
2. 哪些方法职责可能混在一起？
3. 哪些地方必须做 user_id 与订单归属校验？
4. 哪些逻辑适合拆成策略类？
5. 真实售后系统最容易遗漏哪些风险？
```

---

## 6. 今日产出

- [ ] `AfterSaleService` 方法清单
- [ ] 10 个 public 方法职责表（[参考产出](./day01-10个方法深读.md)）
- [ ] 售后域依赖图
- [ ] 售后风险清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能列 10 个方法
- [ ] 能说明售后域为什么复杂
- [ ] 能说明售后如何连接用户、订单、支付
- [ ] 能列出 5 个售后风险
- [ ] 能说明为什么需要策略模式拆分

---

## 8. 今日自测题

### 8.1 售后域为什么比普通接口复杂？

参考答案：

> ✅ 因为售后不是单一“退款接口”，而是连接用户、订单、支付、退款、物流、权限审核和 MQ 通知的多域交叉链路，一个申请可能涉及归属校验、状态流转、退款和通知等多个环节。

---

### 8.2 AfterSaleService 常见职责有哪些？

参考答案：

> ✅ 常见职责包括：创建售后申请、校验订单归属、校验可售后状态、计算可退金额、审核售后、处理退货、触发退款、更新状态、发送通知、查询列表和详情。

---

### 8.3 为什么售后要拆分成策略模式？

参考答案：

> ✅ 因为仅退款、退货退款、补发、换货等类型处理方式差别很大，如果全写在一个 Service 里会出现大量 `if processingType == ...`，用策略模式可以把每种类型拆到独立类中，更清晰也更好扩展。

---

### 8.4 售后申请时必须做哪个归属校验？

参考答案：

> ✅ 必须校验当前登录用户的 `user_id` 是否等于订单的 `user_id`，防止 A 用户越权申请 B 用户订单的售后。

---

### 8.5 列出 3 个售后域必须关注的风险。

参考答案：

> ✅ 例如：越权申请（申请他人订单）、重复售后（同一商品重复申请）、超额退款（退款金额超过可退金额）；此外还有状态错误回退、凭证图片风险和退款幂等等。

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
我正在进行 Week 10 Day 01：AfterSaleService 概览 的学习。
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
