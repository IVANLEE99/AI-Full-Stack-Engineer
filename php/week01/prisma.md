# Prisma 是什么？

`Prisma` 是 Node.js 生态里常用的数据库 ORM 工具。

ORM 的全称是：

```text
Object Relational Mapping
对象关系映射
```

小白可以先这样理解：

```text
Prisma 是一个帮 Node.js 程序操作数据库的工具。
它可以让你少写 SQL，用更像 JavaScript / TypeScript 的方式查询数据库。
```

---

## 1. SQL 和 Prisma 写法对比

比如原来写 SQL 可能是：

```sql
SELECT * FROM users WHERE id = 1;
```

使用 Prisma 之后，可能写成：

```ts
const user = await prisma.user.findUnique({
  where: {
    id: 1,
  },
});
```

它们表达的意思差不多，都是查询 `id = 1` 的用户。

---

## 2. Prisma 通常由哪些部分组成？

| 部分 | 作用 |
|---|---|
| `schema.prisma` | 定义数据库表结构和模型关系 |
| `Prisma Client` | 自动生成的数据库操作代码 |
| `prisma migrate` | 管理数据库迁移，例如建表、改字段 |
| `prisma studio` | 可视化查看和编辑数据库数据 |

---

## 3. Prisma 模型示例

假设数据库里有一张 `users` 表。

在 Prisma 里可能这样定义模型：

```prisma
model User {
  id    Int    @id @default(autoincrement())
  name  String
  email String @unique
}
```

这个 `User` 模型大概对应数据库里的 `users` 表。

---

## 4. Prisma 查询数据

查询所有用户：

```ts
const users = await prisma.user.findMany();
```

意思是：查询所有用户。

查询单个用户：

```ts
const user = await prisma.user.findUnique({
  where: {
    id: 1,
  },
});
```

意思是：查询 `id = 1` 的用户。

---

## 5. Prisma 新增数据

```ts
const user = await prisma.user.create({
  data: {
    name: 'Tom',
    email: 'tom@example.com',
  },
});
```

意思是：往用户表里新增一条用户数据。

---

## 6. Prisma 更新数据

```ts
await prisma.user.update({
  where: {
    id: 1,
  },
  data: {
    name: 'Jerry',
  },
});
```

意思是：把 `id = 1` 的用户名字改成 `Jerry`。

---

## 7. Prisma 删除数据

```ts
await prisma.user.delete({
  where: {
    id: 1,
  },
});
```

意思是：删除 `id = 1` 的用户。

---

## 8. Prisma 和 Repository 的关系

在 Node.js 项目里，Repository 经常负责调用 Prisma 查询数据库。

例如：

```ts
class UserRepository {
  async findById(id: number) {
    return prisma.user.findUnique({
      where: { id },
    });
  }
}
```

这里的分工是：

```text
Service：决定业务规则
Repository：负责查数据库
Prisma：真正执行数据库操作
Database：真实存储数据的地方
```

可以理解成：

```text
Controller -> Service -> Repository -> Prisma -> Database
```

---

## 9. 和 PHP 项目类比

| Node / Prisma | PHP 项目类比 |
|---|---|
| Prisma | ORM / 数据库操作工具 |
| Prisma model | Model / 数据表映射 |
| Prisma Client | 自动生成的数据库访问对象 |
| Repository 调用 Prisma | Repository 调用 Model 或 SQL |
| `findMany()` | 查询多条数据 |
| `findUnique()` | 查询单条唯一数据 |
| `create()` | 新增数据 |
| `update()` | 更新数据 |
| `delete()` | 删除数据 |

---

## 10. 小白记法

```text
Prisma 不是业务层。
Prisma 是数据库操作工具。
Repository 可以封装 Prisma。
Service 再调用 Repository 完成业务。
```

更简单地记：

```text
Service 管业务。
Repository 管数据访问。
Prisma 帮 Node.js 操作数据库。
Database 负责真正存数据。
```
