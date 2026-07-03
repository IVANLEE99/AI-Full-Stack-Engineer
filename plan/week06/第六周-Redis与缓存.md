# 第六周：Redis 与缓存

## 本周学习目标

- 掌握 Redis 基础数据结构和常用命令
- 理解缓存的读写策略和失效机制
- 学会在 Go 项目中集成 Redis
- 初步认识缓存穿透、缓存雪崩、缓存击穿问题

---

## 一、学习内容

### 1. Redis 基础概念（星期一）

**核心概念**
- Redis 是什么：内存数据库、KV 存储、支持多种数据结构
- Redis 的应用场景：缓存、会话存储、排行榜、计数器、消息队列
- Redis 的持久化：RDB 和 AOF
- Redis 的过期策略：定期删除 + 惰性删除

**安装与启动**
```bash
# Docker 方式（推荐）
docker run -d --name redis -p 6379:6379 redis:7-alpine

# 或使用 docker-compose
# docker-compose.yml
version: '3'
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data
volumes:
  redis-data:

# 启动
docker-compose up -d

# 连接 Redis
docker exec -it redis redis-cli
```

**基础命令**
```bash
# 连接测试
127.0.0.1:6379> PING
PONG

# 查看所有 key
127.0.0.1:6379> KEYS *

# 查看 Redis 信息
127.0.0.1:6379> INFO

# 清空当前数据库
127.0.0.1:6379> FLUSHDB
```

**实践任务**
- [ ] 本地安装并启动 Redis
- [ ] 熟悉 redis-cli 基础命令
- [ ] 理解 Redis 的单线程模型

---

### 2. Redis 五大数据结构（星期一-星期二）

#### 2.1 String（字符串）

**使用场景**：缓存对象、计数器、分布式锁

```bash
# 基础操作
SET key value
GET key
DEL key

# 带过期时间
SETEX key 3600 value      # 设置并指定过期时间（秒）
TTL key                   # 查看剩余过期时间
EXPIRE key 3600           # 设置过期时间

# 计数器
SET counter 0
INCR counter              # 自增 1
INCRBY counter 10         # 增加 10
DECR counter              # 自减 1

# 批量操作
MSET key1 val1 key2 val2
MGET key1 key2
```

#### 2.2 Hash（哈希）

**使用场景**：存储对象、购物车

```bash
# 基础操作
HSET user:1 name "Alice"
HSET user:1 age 25
HGET user:1 name
HGETALL user:1

# 批量设置
HMSET user:2 name "Bob" age 30 email "bob@example.com"
HMGET user:2 name age

# 字段自增
HINCRBY user:1 age 1

# 判断字段是否存在
HEXISTS user:1 name

# 删除字段
HDEL user:1 email
```

#### 2.3 List（列表）

**使用场景**：消息队列、最新列表、评论列表

```bash
# 左侧插入（头部）
LPUSH mylist item1 item2

# 右侧插入（尾部）
RPUSH mylist item3 item4

# 左侧弹出
LPOP mylist

# 右侧弹出
RPOP mylist

# 获取范围
LRANGE mylist 0 -1        # 获取所有元素
LRANGE mylist 0 9         # 获取前 10 个

# 获取长度
LLEN mylist

# 阻塞式弹出（用于消息队列）
BLPOP mylist 30           # 阻塞等待 30 秒
```

#### 2.4 Set（集合）

**使用场景**：标签系统、共同好友、唯一计数

```bash
# 添加元素
SADD myset member1 member2 member3

# 查看所有元素
SMEMBERS myset

# 判断是否存在
SISMEMBER myset member1

# 删除元素
SREM myset member1

# 集合运算
SINTER set1 set2          # 交集
SUNION set1 set2          # 并集
SDIFF set1 set2           # 差集

# 随机获取元素
SRANDMEMBER myset 2

# 元素个数
SCARD myset
```

#### 2.5 Sorted Set（有序集合）

**使用场景**：排行榜、带权重的任务队列

```bash
# 添加元素（带分数）
ZADD leaderboard 100 "player1"
ZADD leaderboard 200 "player2"
ZADD leaderboard 150 "player3"

# 按分数从小到大
ZRANGE leaderboard 0 -1 WITHSCORES

# 按分数从大到小（排行榜）
ZREVRANGE leaderboard 0 9 WITHSCORES    # Top 10

# 获取排名（从 0 开始）
ZREVRANK leaderboard "player2"

# 获取分数
ZSCORE leaderboard "player1"

# 增加分数
ZINCRBY leaderboard 50 "player1"

# 按分数范围查询
ZRANGEBYSCORE leaderboard 100 200

# 删除元素
ZREM leaderboard "player3"
```

**实践任务**
- [ ] 用 String 实现一个简单计数器
- [ ] 用 Hash 存储一个用户对象
- [ ] 用 List 实现一个简单队列
- [ ] 用 Sorted Set 实现一个积分排行榜

---

### 3. Go 集成 Redis（星期二-星期三）

**安装 go-redis**
```bash
go get github.com/redis/go-redis/v9
```

**基础使用**
```go
package main

import (
    "context"
    "fmt"
    "time"

    "github.com/redis/go-redis/v9"
)

var ctx = context.Background()

func main() {
    // 创建客户端
    rdb := redis.NewClient(&redis.Options{
        Addr:     "localhost:6379",
        Password: "",
        DB:       0,
    })

    // 测试连接
    pong, err := rdb.Ping(ctx).Result()
    if err != nil {
        panic(err)
    }
    fmt.Println("Connected to Redis:", pong)

    // String 操作
    err = rdb.Set(ctx, "name", "Alice", 0).Err()
    if err != nil {
        panic(err)
    }

    val, err := rdb.Get(ctx, "name").Result()
    if err != nil {
        panic(err)
    }
    fmt.Println("name:", val)

    // 带过期时间
    rdb.Set(ctx, "session:123", "token_value", 30*time.Minute)

    // Hash 操作
    rdb.HSet(ctx, "user:1", "name", "Bob", "age", 25)
    user := rdb.HGetAll(ctx, "user:1").Val()
    fmt.Println("user:", user)

    // List 操作
    rdb.RPush(ctx, "queue", "task1", "task2", "task3")
    task, _ := rdb.LPop(ctx, "queue").Result()
    fmt.Println("task:", task)

    // Sorted Set 操作
    rdb.ZAdd(ctx, "leaderboard", redis.Z{Score: 100, Member: "player1"})
    rdb.ZAdd(ctx, "leaderboard", redis.Z{Score: 200, Member: "player2"})
    
    top := rdb.ZRevRangeWithScores(ctx, "leaderboard", 0, 9).Val()
    for _, item := range top {
        fmt.Printf("Player: %s, Score: %.0f\n", item.Member, item.Score)
    }
}
```

**封装 Redis 客户端**
```go
// internal/cache/redis.go
package cache

import (
    "context"
    "time"

    "github.com/redis/go-redis/v9"
)

type RedisClient struct {
    client *redis.Client
}

func NewRedisClient(addr string) *RedisClient {
    rdb := redis.NewClient(&redis.Options{
        Addr:         addr,
        Password:     "",
        DB:           0,
        DialTimeout:  5 * time.Second,
        ReadTimeout:  3 * time.Second,
        WriteTimeout: 3 * time.Second,
        PoolSize:     10,
    })

    return &RedisClient{client: rdb}
}

func (r *RedisClient) Set(ctx context.Context, key string, value interface{}, expiration time.Duration) error {
    return r.client.Set(ctx, key, value, expiration).Err()
}

func (r *RedisClient) Get(ctx context.Context, key string) (string, error) {
    return r.client.Get(ctx, key).Result()
}

func (r *RedisClient) Delete(ctx context.Context, key string) error {
    return r.client.Del(ctx, key).Err()
}

func (r *RedisClient) Exists(ctx context.Context, key string) (bool, error) {
    n, err := r.client.Exists(ctx, key).Result()
    return n > 0, err
}
```

**实践任务**
- [ ] 在项目中集成 go-redis
- [ ] 封装一个 RedisClient 工具类
- [ ] 实现 Set/Get/Delete/Exists 基础方法

---

### 4. 缓存设计模式（星期三-星期四）

#### 4.1 Cache-Aside（旁路缓存）

**最常用的缓存模式**

**读流程**
```go
func GetUser(ctx context.Context, userID int) (*User, error) {
    // 1. 先查缓存
    cacheKey := fmt.Sprintf("user:%d", userID)
    cached, err := redisClient.Get(ctx, cacheKey)
    if err == nil {
        var user User
        json.Unmarshal([]byte(cached), &user)
        return &user, nil
    }

    // 2. 缓存未命中，查数据库
    user, err := userRepo.GetByID(ctx, userID)
    if err != nil {
        return nil, err
    }

    // 3. 写入缓存
    data, _ := json.Marshal(user)
    redisClient.Set(ctx, cacheKey, data, 30*time.Minute)

    return user, nil
}
```

**写流程**
```go
func UpdateUser(ctx context.Context, user *User) error {
    // 1. 先更新数据库
    err := userRepo.Update(ctx, user)
    if err != nil {
        return err
    }

    // 2. 删除缓存（而不是更新缓存）
    cacheKey := fmt.Sprintf("user:%d", user.ID)
    redisClient.Delete(ctx, cacheKey)

    return nil
}
```

**为什么删除而不是更新？**
- 避免并发更新导致的数据不一致
- 更新缓存可能浪费（可能不会被读取）
- 删除更简单、更安全

#### 4.2 设置合理的过期时间

```go
const (
    CacheExpireShort  = 5 * time.Minute   // 热点数据
    CacheExpireNormal = 30 * time.Minute  // 普通数据
    CacheExpireLong   = 2 * time.Hour     // 冷数据
)

// 添加随机过期时间，避免缓存雪崩
func RandomExpire(base time.Duration) time.Duration {
    rand.Seed(time.Now().UnixNano())
    jitter := time.Duration(rand.Intn(300)) * time.Second
    return base + jitter
}
```

#### 4.3 缓存 JSON vs 结构化存储

```go
// 方案1：缓存整个对象为 JSON（简单，适合小对象）
func CacheUserAsJSON(ctx context.Context, user *User) error {
    data, _ := json.Marshal(user)
    key := fmt.Sprintf("user:%d", user.ID)
    return redisClient.Set(ctx, key, data, 30*time.Minute)
}

// 方案2：用 Hash 存储（适合大对象，支持部分更新）
func CacheUserAsHash(ctx context.Context, user *User) error {
    key := fmt.Sprintf("user:%d", user.ID)
    return redisClient.HSet(ctx, key,
        "name", user.Name,
        "email", user.Email,
        "age", user.Age,
    ).Err()
}
```

**实践任务**
- [ ] 给用户查询接口加缓存（Cache-Aside）
- [ ] 给商品列表加缓存
- [ ] 实现缓存失效时的数据库回源

---

### 5. 缓存三大问题（星期四-星期五）

#### 5.1 缓存穿透（Cache Penetration）

**问题**：查询一个不存在的数据，缓存和数据库都没有，每次都打到数据库

**解决方案1：缓存空值**
```go
func GetUser(ctx context.Context, userID int) (*User, error) {
    cacheKey := fmt.Sprintf("user:%d", userID)
    
    // 查缓存
    cached, err := redisClient.Get(ctx, cacheKey)
    if err == nil {
        if cached == "null" {  // 空值标记
            return nil, ErrUserNotFound
        }
        var user User
        json.Unmarshal([]byte(cached), &user)
        return &user, nil
    }

    // 查数据库
    user, err := userRepo.GetByID(ctx, userID)
    if err != nil {
        // 缓存空值，短过期时间
        redisClient.Set(ctx, cacheKey, "null", 5*time.Minute)
        return nil, err
    }

    // 缓存正常值
    data, _ := json.Marshal(user)
    redisClient.Set(ctx, cacheKey, data, 30*time.Minute)
    return user, nil
}
```

**解决方案2：布隆过滤器**
```go
// 使用布隆过滤器快速判断 key 是否可能存在
// 实际项目中可用 github.com/bits-and-blooms/bloom
func mightExist(key string) bool {
    // 布隆过滤器检查
    // 如果返回 false，则一定不存在
    // 如果返回 true，可能存在（需要进一步查询）
    return bloomFilter.Test([]byte(key))
}
```

#### 5.2 缓存雪崩（Cache Avalanche）

**问题**：大量缓存同时过期，请求全部打到数据库

**解决方案1：随机过期时间**
```go
func SetWithRandomExpire(ctx context.Context, key string, value interface{}, base time.Duration) error {
    expiration := RandomExpire(base)  // 加随机抖动
    return redisClient.Set(ctx, key, value, expiration)
}

func RandomExpire(base time.Duration) time.Duration {
    rand.Seed(time.Now().UnixNano())
    jitter := time.Duration(rand.Intn(300)) * time.Second
    return base + jitter
}
```

**解决方案2：热点数据永不过期**
```go
// 缓存中存储过期时间
type CachedData struct {
    Data       interface{} `json:"data"`
    ExpireAt   int64       `json:"expire_at"`
}

func GetWithLogicalExpire(ctx context.Context, key string) (interface{}, error) {
    cached, err := redisClient.Get(ctx, key)
    if err != nil {
        return nil, err
    }

    var data CachedData
    json.Unmarshal([]byte(cached), &data)

    // 检查逻辑过期
    if time.Now().Unix() > data.ExpireAt {
        // 异步刷新缓存
        go refreshCache(key)
    }

    return data.Data, nil
}
```

**解决方案3：多级缓存**
```go
// L1: 本地缓存（内存）
// L2: Redis 缓存
// L3: 数据库

func GetWithMultiLevelCache(ctx context.Context, key string) (*User, error) {
    // L1: 本地缓存
    if val, ok := localCache.Get(key); ok {
        return val.(*User), nil
    }

    // L2: Redis
    cached, err := redisClient.Get(ctx, key)
    if err == nil {
        var user User
        json.Unmarshal([]byte(cached), &user)
        localCache.Set(key, &user, 1*time.Minute)
        return &user, nil
    }

    // L3: 数据库
    user, err := userRepo.GetByID(ctx, key)
    if err != nil {
        return nil, err
    }

    // 回填缓存
    data, _ := json.Marshal(user)
    redisClient.Set(ctx, key, data, 30*time.Minute)
    localCache.Set(key, user, 1*time.Minute)

    return user, nil
}
```

#### 5.3 缓存击穿（Hotkey Expiration）

**问题**：热点 key 过期瞬间，大量请求同时打到数据库

**解决方案：互斥锁（Singleflight）**
```go
import "golang.org/x/sync/singleflight"

var group singleflight.Group

func GetUser(ctx context.Context, userID int) (*User, error) {
    cacheKey := fmt.Sprintf("user:%d", userID)

    // 使用 singleflight 确保同时只有一个请求查数据库
    val, err, _ := group.Do(cacheKey, func() (interface{}, error) {
        // 先查缓存
        cached, err := redisClient.Get(ctx, cacheKey)
        if err == nil {
            var user User
            json.Unmarshal([]byte(cached), &user)
            return &user, nil
        }

        // 查数据库
        user, err := userRepo.GetByID(ctx, userID)
        if err != nil {
            return nil, err
        }

        // 写缓存
        data, _ := json.Marshal(user)
        redisClient.Set(ctx, cacheKey, data, 30*time.Minute)

        return user, nil
    })

    if err != nil {
        return nil, err
    }

    return val.(*User), nil
}
```

**实践任务**
- [ ] 实现缓存空值防止穿透
- [ ] 给所有缓存加随机过期时间
- [ ] 用 singleflight 防止缓存击穿

---

## 二、本周实战任务

### 任务 1：给小项目核心接口加缓存（星期五）

**需求**
- 给用户详情接口加缓存
- 给商品列表接口加缓存
- 给热门商品排行榜加缓存
- 支持缓存失效和更新

**实现示例**
```go
// service/user_service.go
package service

import (
    "context"
    "encoding/json"
    "fmt"
    "time"
)

type UserService struct {
    repo  UserRepository
    cache *cache.RedisClient
}

func (s *UserService) GetByID(ctx context.Context, userID int) (*User, error) {
    cacheKey := fmt.Sprintf("user:%d", userID)

    // 1. 查缓存
    cached, err := s.cache.Get(ctx, cacheKey)
    if err == nil {
        var user User
        if err := json.Unmarshal([]byte(cached), &user); err == nil {
            return &user, nil
        }
    }

    // 2. 查数据库
    user, err := s.repo.GetByID(ctx, userID)
    if err != nil {
        return nil, err
    }

    // 3. 写缓存
    data, _ := json.Marshal(user)
    s.cache.Set(ctx, cacheKey, data, 30*time.Minute)

    return user, nil
}

func (s *UserService) Update(ctx context.Context, user *User) error {
    // 1. 更新数据库
    err := s.repo.Update(ctx, user)
    if err != nil {
        return err
    }

    // 2. 删除缓存
    cacheKey := fmt.Sprintf("user:%d", user.ID)
    s.cache.Delete(ctx, cacheKey)

    return nil
}
```

**验收标准**
- [ ] 缓存命中时响应时间 < 50ms
- [ ] 缓存未命中时正确回源数据库
- [ ] 更新操作能正确失效缓存
- [ ] 缓存有合理的过期时间

---

### 任务 2：实现商品排行榜（星期五-星期六）

**需求**
- 用 Sorted Set 实现实时销量排行榜
- 支持商品销量增加
- 支持查询 Top 10
- 支持查询商品排名

**实现示例**
```go
package service

type RankService struct {
    cache *cache.RedisClient
}

const rankKey = "product:sales:rank"

// 增加销量
func (s *RankService) IncrSales(ctx context.Context, productID int, amount int) error {
    return s.cache.client.ZIncrBy(ctx, rankKey, float64(amount), fmt.Sprintf("%d", productID)).Err()
}

// 获取 Top N
func (s *RankService) GetTopN(ctx context.Context, n int) ([]Product, error) {
    results := s.cache.client.ZRevRangeWithScores(ctx, rankKey, 0, int64(n-1)).Val()

    var products []Product
    for _, item := range results {
        productID, _ := strconv.Atoi(item.Member.(string))
        products = append(products, Product{
            ID:    productID,
            Sales: int(item.Score),
        })
    }

    return products, nil
}

// 获取商品排名（从 1 开始）
func (s *RankService) GetRank(ctx context.Context, productID int) (int64, error) {
    rank, err := s.cache.client.ZRevRank(ctx, rankKey, fmt.Sprintf("%d", productID)).Result()
    if err != nil {
        return 0, err
    }
    return rank + 1, nil  // 排名从 1 开始
}
```

**验收标准**
- [ ] 能实时更新销量
- [ ] Top 10 查询响应 < 10ms
- [ ] 排名查询准确

---

## 三、本周验收标准

### 知识验收
- [ ] 理解 Redis 五大数据结构及使用场景
- [ ] 能说清 Cache-Aside 模式的读写流程
- [ ] 理解缓存穿透、雪崩、击穿的区别和解决方案
- [ ] 知道何时该加缓存、何时不该加

### 代码验收
- [ ] 项目成功集成 Redis
- [ ] 至少 3 个接口接入缓存
- [ ] 缓存有合理的过期时间和失效机制
- [ ] 实现了一个排行榜或计数器功能

### 性能验收
- [ ] 缓存命中时接口响应时间显著降低
- [ ] 用 Redis 命令查看缓存数据正确
- [ ] 更新操作能正确失效缓存

---

## 四、推荐资源

### 必读文档
- [Redis 官方文档](https://redis.io/docs/)
- [go-redis 文档](https://redis.uptrace.dev/)
- [Redis 命令参考](https://redis.io/commands/)

### 推荐阅读
- 《Redis 设计与实现》
- [缓存更新的套路](https://coolshell.cn/articles/17416.html)
- [缓存穿透、雪崩、击穿详解](https://xiaolincoding.com/redis/cluster/cache_problem.html)

---

## 五、常见问题与坑

### 1. 缓存与数据库不一致
```go
// ❌ 先删缓存再更新数据库
redisClient.Delete(ctx, key)
db.Update(user)  // 如果失败，缓存已删除

// ✅ 先更新数据库再删缓存
err := db.Update(user)
if err != nil {
    return err
}
redisClient.Delete(ctx, key)
```

### 2. 缓存键命名混乱
```go
// ❌ 没有规范
redisClient.Set(ctx, "123", user)
redisClient.Set(ctx, "user_123", user)

// ✅ 统一命名规范
const (
    KeyPrefixUser    = "user:"
    KeyPrefixProduct = "product:"
)

key := fmt.Sprintf("%s%d", KeyPrefixUser, userID)
```

### 3. 忘记设置过期时间
```go
// ❌ 永不过期，内存泄露
redisClient.Set(ctx, key, value, 0)

// ✅ 总是设置过期时间
redisClient.Set(ctx, key, value, 30*time.Minute)
```

### 4. 序列化/反序列化错误
```go
// ❌ 直接存储结构体指针
redisClient.Set(ctx, key, user)  // 存的是指针地址

// ✅ 序列化为 JSON
data, _ := json.Marshal(user)
redisClient.Set(ctx, key, data, expiration)
```

---

## 六、本周复盘模板

```markdown
### 第 6 周复盘

**这周学了什么**
- Redis 五大数据结构
- Cache-Aside 缓存模式
- 缓存三大问题及解决方案

**这周做了什么**
- 给 3 个核心接口加了缓存
- 实现了商品销量排行榜
- 优化了接口响应时间

**卡在哪里**
- 缓存更新时机不好把握
- 排行榜的排名计算有点绕

**AI 帮了什么**
- 生成了 Cache-Aside 的模板代码
- 解释了 ZRevRank 的用法

**真正掌握了什么**
- 能独立给接口加缓存
- 理解了为什么先更新数据库再删缓存
- 知道如何防止缓存穿透

**下周怎么调整**
- 开始综合运用前 6 周知识做小项目
- 加强事务和并发场景的处理
```

---

## 七、下周预告

**第七周：小项目冲刺**
- 综合运用 Gin + GORM + 分层 + 并发 + Redis
- 选定一个真实业务场景（订单/博客/门店后台）
- 实现核心业务链路
- 用事务保证数据一致性

**准备工作**
- [ ] 确定小项目选题
- [ ] 梳理核心业务流程
- [ ] 设计数据库表结构
