# AI 全栈工程师学习仓库

> 从 PHP 后端工程师走向 Go 方向 AI 全栈的学习工作区。
>
> 内容分四类：主学习路线（`plan/`、`php/`）、后端通识速成（`mysql/`、`redis/`、`mq/`）、以及各主题的源码阅读笔记。

## 目录导航

| 目录 | 入口 | 内容 | 适合谁 |
|------|------|------|--------|
| [plan/](plan/) | [24 周学习计划表格版](plan/24周学习计划表格版.md) | 24 周 Go 方向学习路线，每周一个 `weekNN/` 目录 | 想看长期节奏 |
| [php/](php/) | [七仓库新手上手指南](php/README.md) | PHP 侧 24 周学习、七个真实服务仓库解读、每周 day01–day07 笔记与源码快照 | PHP 后端主线 |
| [mysql/](mysql/) | [7 天掌握 MySQL](mysql/README.md) | 7 天从建表到索引、事务、锁与生产边界的通识教程 | 补齐数据库基础 |
| [redis/](redis/) | [一天学完 Redis](redis/redis入门一天学完.md) | 一天通识：五大数据结构、缓存三大问题、分布式锁 | 补齐缓存基础 |
| [mq/](mq/) | [10 分钟入门 RabbitMQ](mq/RabbitMQ入门10分钟学完.md) | 10 分钟通识：五个核心角色、投递流程、丢失/重复/失败三大风险 | 补齐异步与解耦基础 |

## 后端通识三件套怎么排

三者定位互补，不是替代关系：

| 组件 | 一句话定位 | 真相源？ |
|------|------------|----------|
| MySQL | 事务、强约束、主数据 | 是 |
| Redis | 内存缓存、计数、锁、排行榜 | 否，可丢可重建 |
| RabbitMQ | 异步事件总线，服务间解耦 | 否，事件要可重放 |

建议顺序：先 MySQL（知道什么必须持久），再 Redis（知道什么可以缓存），最后 MQ（知道怎么异步）。

## 源码阅读锚点

教程里的概念都指向仓库中的真实代码，可以顺着链接回去对照：

| 主题 | 锚点 |
|------|------|
| Redis 封装 | [php/week03/common/redis/order/OrderRedis.php](php/week03/common/redis/order/OrderRedis.php) |
| MQ 发送封装 | [php/week08/RabbitMq.php](php/week08/RabbitMq.php) |
| MQ 真实调用 | [php/week06/OriginCodes/OrderService.php](php/week06/OriginCodes/OrderService.php) |
| 支付与节点链 | [php/week07/ProcessPaymentNode.php](php/week07/ProcessPaymentNode.php) |
