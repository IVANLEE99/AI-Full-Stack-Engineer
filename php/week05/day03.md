# Week 05 Day 03：薄 Controller 实践

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解网关 Controller 为何保持精简。

---

## 1. 学习内容

- 复习 Module 路由
- 找一个 PayController action 逐行分析

---

## 2. 源码阅读

- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

- 统计 PayController 各 action 行数
- 手写一个「纯转发」action 伪代码
- 说明哪些逻辑不应出现在网关

---

## 4. JS/Node.js 类比

- 薄 Controller≈只做鉴权+取参+转发
- 业务逻辑≈下游微服务

---

## 5. AI Review 提问

- 我设计的伪代码是否足够薄？

---

## 6. 今日产出

- 行数统计表
- 转发 action 伪代码

---

## 7. 今日完成标准

- [ ] 能解释「薄」的含义
- [ ] 能举反例

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
我正在进行 Week 05 Day 03：薄 Controller 实践 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 05 README](./README.md)
