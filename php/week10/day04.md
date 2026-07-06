# Week 10 Day 04：Console 与 API 分工

> 所属周：Week 10：售后服务 + Console 任务  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`aftersale-service`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解售后服务中 Console 命令与 API 接口的分工，知道哪些场景适合同步 HTTP API 处理，哪些场景适合用 `php yii controller/action` 这类 Console 批处理任务执行。

今天你要真正掌握这一句话：

> API 面向用户或系统的实时请求，Console 面向定时任务、批处理、补偿和后台维护；售后域中超时关闭、批量同步、重试补偿、状态修复等任务通常更适合 Console。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 API 和 Console 的入口差异
2. 学习 Yii Console 命令格式
3. 打开 `OmsController.php`，看有哪些 action
4. 判断每个 action 是批处理、同步、补偿还是维护任务
5. 对比售后 API 入口和 Console 入口
6. 理解 Console 任务如何配合 MQ、Webhook、退款状态
7. 标注 Console 任务的幂等、日志、重试风险
8. 用 Node.js scripts/cron job 做类比
9. 用 AI Review 检查哪些场景应该用 Console

---

## 1. 学习内容

### 1.1 API 和 Console 有什么区别？

| 对比项 | API | Console |
|---|---|---|
| 入口 | HTTP 请求 | 命令行 |
| 调用方 | 前端、BFF、内网服务 | 定时任务、运维、CI、任务调度 |
| 响应要求 | 要快速返回给调用方 | 可长时间执行 |
| 常见场景 | 用户申请售后、客服审核 | 批量同步、超时处理、补偿重试 |
| 鉴权方式 | token、session、服务间鉴权 | 机器权限、执行环境、参数控制 |

API 例子：

```text
POST /after-sale/apply
```

Console 例子：

```bash
php yii oms/sync-after-sale-status
```

---

### 1.2 Yii Console 命令格式

Yii Console 通常格式：

```bash
php yii controller/action param1 param2
```

例如：

```bash
php yii oms/handle-timeout
```

可能对应：

```php
<?php

final class OmsController extends Controller
{
    public function actionHandleTimeout(): void
    {
        // 批量处理超时售后单
    }
}
```

小白重点：Console Controller 不是 HTTP Controller，它运行在命令行环境。

---

### 1.3 售后域哪些场景适合 Console？

| 场景 | 为什么适合 Console |
|---|---|
| 超时未退货自动关闭 | 定时扫描，不需要用户实时请求 |
| 退款状态批量同步 | 可能调用第三方或内部接口，耗时 |
| MQ 失败补偿 | 定期重试失败任务 |
| 售后数据修复 | 运维/开发手动执行 |
| 批量通知 | 不应阻塞用户 API |
| OMS 状态同步 | 与外部系统定时对账 |

---

### 1.4 Console 任务必须注意什么？

Console 批处理很强大，但风险也大。

| 风险 | 说明 | 应对 |
|---|---|---|
| 重复执行 | 同一个任务跑多次 | 幂等设计、锁 |
| 批量误操作 | 一次改错大量数据 | dry-run、limit、日志 |
| 执行时间长 | 任务超时或占用资源 | 分页、分批处理 |
| 无鉴权保护 | 命令被误执行 | 限制环境和权限 |
| 日志不足 | 出错难排查 | 记录任务 ID、入参、结果 |

---

### 1.5 Console 与 MQ 的关系

Console 可以做 MQ 的补偿或消费者入口：

```text
MQ 消费失败
  ↓
记录失败任务
  ↓
Console 定时扫描失败任务
  ↓
重试处理
```

也可以由 Console 定时投递消息：

```text
Console 扫描超时售后单
  ↓
投递 aftersale.timeout 事件
  ↓
消费者处理关闭和通知
```

---

### 1.6 API 与 Console 分工判断法

问自己：

| 问题 | 更适合 |
|---|---|
| 是否需要用户立即看到结果？ | API |
| 是否是定时批量处理？ | Console |
| 是否可能处理很多记录？ | Console |
| 是否是前端点击触发？ | API |
| 是否是失败补偿或数据修复？ | Console |
| 是否要快速响应第三方 Webhook？ | API + MQ，后续 Console/Consumer |

---

### 1.7 Node.js 类比

Node 项目里 Console 类似：

```bash
node scripts/sync-aftersale.js
node scripts/retry-failed-refunds.js
```

或定时任务：

```js
cron.schedule('*/5 * * * *', async () => {
  await afterSaleService.closeTimeoutCases();
});
```

PHP Console 和 Node scripts 本质都属于后台任务入口。

---

## 2. 源码阅读

- `aftersale-service/console/controllers/OmsController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| action | 命令 | 职责 | 是否批处理 | 风险 |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 OmsController

记录 action 列表和每个 action 的用途。

### 练习 2：理解命令格式

写出 3 个可能的 Console 命令，并说明用途。

### 练习 3：对比 API 入口

完成 API vs Console 分工表。

---

## 4. JS/Node.js 类比

- Console ≈ `node scripts/*.js`
- Yii Console action ≈ CLI command handler
- 定时任务 ≈ cron job
- 批量补偿 ≈ retry script
- API vs Console ≈ route handler vs background script

---

## 5. AI Review 提问

```text
我正在学习售后服务中的 Console 与 API 分工。
我已经阅读 OmsController，整理了 Console action、命令格式和适用场景。
请你检查：
1. 哪些场景适合 Console，哪些应保留在 API？
2. Console 批处理需要哪些幂等和锁设计？
3. 批量任务如何避免误操作？
4. Console 与 MQ/Consumer 如何配合？
5. 与 Node scripts/cron job 的类比是否准确？
```

---

## 6. 今日产出

- [ ] `OmsController` 阅读笔记
- [ ] API vs Console 分工表
- [ ] Console 命令示例
- [ ] 批处理风险清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能区分 API 与 Console 分工
- [ ] 能解释 `php yii controller/action`
- [ ] 能列出售后域 5 个 Console 场景
- [ ] 能说明 Console 任务的幂等和日志风险
- [ ] 能用 Node scripts 类比 Console

---

## 8. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 9. AI Review 提示词

```text
我正在进行 Week 10 Day 04：Console 与 API 分工 的学习。
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
