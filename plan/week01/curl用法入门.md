# curl 用法入门

> 主题:用 `curl` 测试 HTTP 接口
> 目标:学会用命令行快速测试 Go 写的 API,尤其是本周的 Todo API
> 原则:先会用,再慢慢记参数。你不用一次把所有选项背下来。

---

## 一、curl 是什么

`curl` 是一个命令行工具,用来发 HTTP 请求。

你可以把它理解成:
- 不开浏览器,直接在终端里访问接口
- 不写前端页面,先验证后端接口是否正常
- 类似 Postman,但更轻量、更适合快速调试

在学 Go Web 开发时,`curl` 非常常用,因为你写完接口后,第一时间就可以用它测试:
- 路由通不通
- 返回的数据对不对
- POST/PUT/DELETE 是否生效
- JSON 参数有没有传成功

---

## 二、最基础的用法

### 1. 发一个 GET 请求

```bash
curl http://localhost:8080/todos
```

这表示:
- 用 `curl` 请求这个地址
- 默认方法是 `GET`
- 适合拿数据、查列表、查详情

如果你的 Todo API 已经启动,它可能返回:

```json
[]
```

或者:

```json
[{"id":1,"title":"学Go","done":false}]
```

---

## 三、POST 请求:提交数据

### 1. 发送最常见的 JSON 请求

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

这几个参数分别是什么意思:

- `-X POST`
  - 指定请求方法是 `POST`
- `-H "Content-Type: application/json"`
  - 设置请求头,告诉服务端:我发的是 JSON
- `-d '...'`
  - 请求体数据(data),也就是你要提交的内容

如果接口成功,可能返回:

```json
{"id":1,"title":"学Go","done":false}
```

---

## 四、GET / POST / PUT / DELETE 分别用来做什么

| 方法 | 常见用途 | 示例 |
|---|---|---|
| GET | 查询数据 | 查 Todo 列表 |
| POST | 创建数据 | 新建一个 Todo |
| PUT | 更新数据 | 修改某个 Todo |
| DELETE | 删除数据 | 删除某个 Todo |

### 1. GET
```bash
curl http://localhost:8080/todos
```

### 2. POST
```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

### 3. PUT
如果你的接口支持更新,可以这样写:

```bash
curl -X PUT http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1,"title":"学Go语法","done":true}'
```

### 4. DELETE
如果你的接口支持删除,可以这样写:

```bash
curl -X DELETE http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

---

## 五、结合本周 Todo API 来理解

假设你已经运行了:

```bash
go run day6.go
```

然后你可以这样测试。

### 1. 先查列表
```bash
curl http://localhost:8080/todos
```

如果还没数据,通常返回:

```json
[]
```

### 2. 创建一个 Todo
```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

### 3. 再查一次列表
```bash
curl http://localhost:8080/todos
```

这时候应该能看到刚创建的内容。

### 4. 更新 Todo
```bash
curl -X PUT http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1,"title":"学Go语法","done":true}'
```

### 5. 删除 Todo
```bash
curl -X DELETE http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

---

## 六、常用参数速查表

| 参数 | 作用 | 例子 |
|---|---|---|
| `-X` | 指定请求方法 | `-X POST` |
| `-H` | 设置请求头 | `-H "Content-Type: application/json"` |
| `-d` | 发送请求体数据 | `-d '{"title":"学Go"}'` |
| `-i` | 显示响应头 + 响应体 | `curl -i http://localhost:8080/todos` |
| `-v` | 显示更详细的调试信息 | `curl -v http://localhost:8080/todos` |

---

## 七、如何看响应状态和响应头

### 1. 看响应头
```bash
curl -i http://localhost:8080/todos
```

你可能会看到:

```http
HTTP/1.1 200 OK
Content-Type: application/json
Date: Fri, 04 Jul 2026 10:00:00 GMT

[]
```

这里最值得关注的是:
- `200 OK` -> 请求成功
- `Content-Type: application/json` -> 返回的是 JSON

### 2. 看详细调试信息
```bash
curl -v http://localhost:8080/todos
```

这个适合排查问题时用,会显示:
- 请求发给了谁
- 请求头是什么
- 响应头是什么
- 连接有没有成功

平时开发里 `-i` 和 `-v` 都很常用。

---

## 八、常见错误和排查方式

### 1. 连接不上

```bash
curl: (7) Failed to connect to localhost port 8080
```

通常说明:
- Go 服务还没启动
- 端口不是 8080
- 程序已经报错退出了

排查顺序:
1. 先看 `go run day6.go` 还在不在运行
2. 确认代码监听的是不是 `:8080`
3. 确认你请求的是不是 `http://localhost:8080`

### 2. 路径错了

比如你写成:

```bash
curl http://localhost:8080/todo
```

但真实路由是 `/todos`,那就可能返回 404。

所以要确认:
- 代码里注册的路径是什么
- `curl` 请求的路径是否完全一致

### 3. JSON 格式错误

例如少了引号、少了括号:

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{title:"学Go"}'
```

这通常会导致服务端解析失败,返回 400。

JSON 里要特别注意:
- 字段名要用双引号
- 字符串值要用双引号
- 花括号、逗号要完整

正确写法:

```bash
-d '{"title":"学Go","done":false}'
```

### 4. 忘了带 `Content-Type`

有些接口即使你传了 JSON,如果没带:

```bash
-H "Content-Type: application/json"
```

服务端也可能无法按 JSON 正确处理。

所以发 JSON 时,尽量养成一起写 `-H` 的习惯。

---

## 九、给前端同学的理解方式

如果你熟悉前端,可以这样类比:

### fetch
```js
fetch("http://localhost:8080/todos")
```

约等于:

```bash
curl http://localhost:8080/todos
```

### fetch POST JSON
```js
fetch("http://localhost:8080/todos", {
  method: "POST",
  headers: {
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    title: "学Go",
    done: false
  })
})
```

约等于:

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

也就是说:
- `method` -> `-X`
- `headers` -> `-H`
- `body` -> `-d`

这样你会更容易把 `curl` 和前端请求对应起来。

---

## 十、你现在最需要记住的 3 个命令

先别贪多,把下面 3 个记住就够了:

### 查列表
```bash
curl http://localhost:8080/todos
```

### 发 POST JSON
```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

### 看响应头
```bash
curl -i http://localhost:8080/todos
```

只要这 3 个会了,你就已经能完成本周 Todo API 的基础测试。

---

## 十一、Postman 和 curl 对照表

如果你熟悉 Postman,用这个对照表能把 Postman 里的操作用 curl 写出来。

### 常用操作对照

| Postman 操作 | curl 等效命令 | 说明 |
|---|---|---|
| **新建 GET 请求** | `curl URL` | 默认就是 GET |
| **新建 POST 请求** | `curl -X POST URL` | `-X` 指定方法 |
| **Headers 输入框** | `-H "Key: Value"` | 一个 `-H` 加一个请求头 |
| **Body > raw > JSON** | `-d '{"key":"value"}'` | 数据要写在单引号里 |
| **Authorization > Bearer Token** | `-H "Authorization: Bearer <token>"` | 放到请求头里 |
| **Params 输入框** | `URL?key=value` | 直接拼到 URL 后 |
| **右下角 Code 按钮** | Postman 自动生成 curl 命令 | 如图位置:Send 按钮右边 `</>` |

### 具体例子对照

**GET 请求**

Postman:
- 方法选 GET
- URL 填 `http://localhost:8080/todos`
- 点 Send

curl:
```bash
curl http://localhost:8080/todos
```

**POST JSON**

Postman:
- 方法选 POST
- URL 填 `http://localhost:8080/todos`
- Headers: `Content-Type: application/json`
- Body: raw + JSON `{"title":"学Go","done":false}`

curl:
```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

**带 Token**

Postman:
- Authorization: Bearer Token + 填入 token

curl:
```bash
curl http://localhost:8080/api/me \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1..."
```

**带路径参数**

Postman:
- URL 填 `http://localhost:8080/todos/1`

curl:
```bash
curl http://localhost:8080/todos/1
```

**带查询参数**

Postman:
- URL 填 `http://localhost:8080/todos?page=2&size=10`

curl:
```bash
curl "http://localhost:8080/todos?page=2&size=10"
```

### 在 Postman 里一键转 curl

Postman 的 Send 按钮右边有一个 `</>` Code 按钮:

```
Send ▸ Code ▸ cURL
```

点击后选中 `cURL`,Postman 会把当前请求自动转成 curl 命令,可以直接复制到终端里执行。

这个功能在你学 API 调试时非常实用:
- 先在 Postman 里调试到请求正常
- 再点 Code 生成 curl 命令
- 然后到终端 / 文档 / 脚本里复用

---

## 十二、学习建议

入门阶段不要追求把 `curl` 所有参数都背下来。

你只需要先掌握:
- GET 怎么发
- POST JSON 怎么发
- 怎么看响应头
- 出错时怎么排查

后面碰到文件上传、认证、Cookie、表单这些场景时,再逐步扩展就够了。

---

## 十三、本周配套练习

你可以按这个顺序自己练一遍:

1. 启动 Todo API
```bash
go run day6.go
```

2. 查空列表
```bash
curl http://localhost:8080/todos
```

3. 创建一个 Todo
```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学Go","done":false}'
```

4. 再查列表
```bash
curl http://localhost:8080/todos
```

5. 更新 Todo
```bash
curl -X PUT http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1,"title":"学Go语法","done":true}'
```

6. 删除 Todo
```bash
curl -X DELETE http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

练完这一轮,你对接口测试就会有很直观的感觉。