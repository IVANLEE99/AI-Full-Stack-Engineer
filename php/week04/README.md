# Week 04：配置中心 + 站点 API

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第一阶段：PHP + Yii2/TP 基础
- 主仓库/项目：`mall-core`
- 本周目标：理解动态配置、g_config、配置中心与硬编码边界。

### 为什么本周要学这些

- 大量业务行为由配置控制。
- 不懂配置就很难理解线上差异。

---

## 2. 本周需要掌握的知识点

1. g_config
2. ConfigHelper
3. 配置中心概念
4. 配置 API
5. Laravel config 对比

### php-pro 能力对齐

- 动态配置有默认值
- 禁止硬编码模块字符串
- 记录配置影响范围

---

## 3. 必读代码/文件路径

- `mall-core/common/libraries/App/fun_helpers.php`
- `mall-core/common/libraries/App/Utils/ConfigHelper.php`
- `site-api/controllers/ConfigController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | g_config 函数 |
| Day 2（周二） | 源码阅读 | 配置 API 全链路 |
| Day 3（周三） | 编码练习 | 配置中心概念 |
| Day 4（周四） | 架构理解 | Laravel 对比 |
| Day 5（周五） | 类比日 | 阶段总结与类比日 |
| Day 6（周六） | 项目实战 | 配置项清单项目 |
| Day 7（周日） | 复盘预习 | 阶段①验收 |

### Day 1（周一）：g_config 函数

**类型**：概念入门  
**今日目标**：理解配置读取函数。

**学习内容**：
- 读 fun_helpers 中 g_config
- 读 ConfigHelper 模块常量

**源码阅读**：
- `mall-core/common/libraries/App/fun_helpers.php`

**练习任务**：
- 列 3 个 module/key/default
- 对比 process.env

**JS/Node 类比**：
- g_config≈process.env+热更新

**AI Review 提问**：
- 与 dotenv 差异？

**今日产出**：
- 配置函数笔记

**今日完成标准**：
- [ ] 能解释 g_config 参数

---

### Day 2（周二）：配置 API 全链路

**类型**：源码阅读  
**今日目标**：追踪 ConfigController 调用链。

**学习内容**：
- 站点配置业务

**源码阅读**：
- `site-api/controllers/ConfigController.php`

**练习任务**：
- 追踪 ConfigController→Service
- 记录配置如何影响前端

**JS/Node 类比**：
- 配置 API≈前端 settings 接口

**AI Review 提问**：
- 链路完整吗？

**今日产出**：
- 配置链路图

**今日完成标准**：
- [ ] 能追踪全链路

---

### Day 3（周三）：配置中心概念

**类型**：编码练习  
**今日目标**：理解远程配置与本地缓存。

**学习内容**：
- Nacos 配置管理文档

**练习任务**：
- 画 远程配置→本地→g_config 流程
- 列动态 vs 静态配置场景

**JS/Node 类比**：
- 配置中心≈Consul/etcd

**AI Review 提问**：
- 何时用动态配置？

**今日产出**：
- 配置流程图

**今日完成标准**：
- [ ] 能区分配置类型

---

### Day 4（周四）：Laravel 对比

**类型**：架构理解  
**今日目标**：写 config() vs g_config() 对照。

**学习内容**：
- Laravel Configuration

**练习任务**：
- 写对照笔记
- 找 3 个配置影响业务的例子

**JS/Node 类比**：
- config()≈g_config()

**AI Review 提问**：
- 对照准确吗？

**今日产出**：
- Laravel 对照笔记

**今日完成标准**：
- [ ] 完成对照

---

### Day 5（周五）：阶段总结与类比日

**类型**：类比日  
**今日目标**：整理 W1-W4 笔记，读通 CSR 链路。

**学习内容**：
- 回顾前4周

**练习任务**：
- 独立读通一条 CSR 链路
- 完成类比打卡
- 列配置项清单

**JS/Node 类比**：
- CSR≈Controller→Service→Repository→Model

**AI Review 提问**：
- CSR 理解对吗？

**今日产出**：
- CSR 笔记
- 配置清单
- 阶段总结

**今日完成标准**：
- [ ] 能读通 CSR

---

### Day 6（周六）：配置项清单项目

**类型**：项目实战  
**今日目标**：输出完整配置项清单表格。

**学习内容**：
- 配置管理最佳实践

**练习任务**：
- 完成 module/key/default/影响/风险 表格

**JS/Node 类比**：
- 配置清单≈feature flags 文档

**AI Review 提问**：
- 清单完整吗？

**今日产出**：
- 配置项清单

**今日完成标准**：
- [ ] 清单完成

---

### Day 7（周日）：阶段①验收

**类型**：复盘预习  
**今日目标**：完成阶段自评与预习网关。

**学习内容**：
- 阶段自评表

**练习任务**：
- 填自评
- 写阶段总结
- 预习 BFF

**JS/Node 类比**：
- 阶段复盘≈里程碑回顾

**AI Review 提问**：
- 能进入微服务学习吗？

**今日产出**：
- 阶段总结
- 自评表

**今日完成标准**：
- [ ] 完成阶段①验收

---

## 5. JS/Node.js 类比学习（本周总览）

g_config≈process.env+远程热更新；禁止硬编码≈禁止写死 API_KEY。

### 本周类比打卡模板

```text
本周概念：
Node 等价：
差异：
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 6. 本周产出物

- [ ] 配置项清单
- [ ] CSR 链路笔记
- [ ] 阶段①总结

---

## 7. 推荐学习资料

- Nacos 文档
- Laravel Configuration

---

## 8. 本周验收标准

- [ ] 能区分动态/静态配置
- [ ] 能读通 CSR
- [ ] 完成阶段自评

---

## 9. AI Review 提示词

```text
我正在进行 Week 04：配置中心 + 站点 API 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：理解是否正确、JS 类比是否准确、是否遗漏风险、真实项目需注意什么。
请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 10. 周日复盘与下周预习

| 复盘项 | 记录 |
|--------|------|
| 本周最清楚的概念 |  |
| 本周最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 本周产出是否完成 |  |
| 自评分（1-5） |  |

**下周预习**：预习 BFF 模式、mall-gateway 目录结构。
