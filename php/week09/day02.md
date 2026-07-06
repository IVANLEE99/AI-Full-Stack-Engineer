# Week 09 Day 02：UserService 与 Redis 缓存

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解用户缓存读写与失效。

---

## 1. 学习内容

- Redis 缓存策略
- 读 UserService getUserInfoByRedis

---

## 2. 源码阅读

- `user-service/common/redis/user/UserDetailRedis.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

- 读 UserDetailRedis
- 列缓存 key 规则
- 说明何时写入/失效

---

## 4. JS/Node.js 类比

- UserDetailRedis≈ioredis 用户缓存封装

---

## 5. AI Review 提问

- 缓存失效策略合理吗？

---

## 6. 今日产出

- 缓存策略笔记

---

## 7. 今日完成标准

- [ ] 能说明失效策略

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
我正在进行 Week 09 Day 02：UserService 与 Redis 缓存 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 09 README](./README.md)
