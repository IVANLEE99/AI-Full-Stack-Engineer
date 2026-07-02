# 第十三周详细学习内容:单元测试与 Mock

> 主题:单元测试与 Mock
> 目标:为核心业务逻辑补上测试,能模拟外部依赖,核心服务覆盖率达到 70%-80%。
> 原则:测试不是负担,是让你敢改代码的底气。**先测核心业务逻辑,别追求 100% 覆盖率。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 会写测试 | 掌握 Go 原生 `testing` 和表驱动测试写法 |
| 会用断言 | 用 testify 让断言更简洁可读 |
| 会 Mock | 用 gomock 隔离数据库/外部依赖 |
| 有覆盖率 | 核心 Service 层覆盖率达到 70%-80% |

---

## 二、本周关键认知

前端你可能写过 Jest/Vitest,Go 的测试思路一致,但更内置、更朴素:

| 前端(Jest/Vitest) | Go 做法 | 关键差异 |
|---|---|---|
| `*.test.ts` 文件 | `*_test.go` 文件 | 命名固定,和被测文件同目录 |
| `describe/it` | `func TestXxx(t *testing.T)` | 函数名必须以 Test 开头 |
| `expect().toBe()` | `t.Error` / testify 的 `assert` | 原生朴素,testify 更像 Jest |
| `test.each` 参数化 | 表驱动测试(table-driven) | Go 社区最主流的写法 |
| `jest.mock()` | gomock 生成 mock | 基于接口生成,编译期安全 |
| `--coverage` | `go test -cover` | 内置,不用装插件 |

三句话记住本周:

- **测试文件放同目录**:`user.go` 的测试是同目录的 `user_test.go`。
- **表驱动是主流**:一个测试函数,一张用例表,覆盖多种输入。
- **Mock 靠接口**:要 mock 谁,谁就得先定义成接口。

---

## 三、每天学习安排(7天)

### Day 1:Go 原生 testing 入门

**被测代码** `calc.go`
```go
package calc

func Add(a, b int) int {
    return a + b
}

func Divide(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("除数不能为0")
    }
    return a / b, nil
}
```

**测试代码** `calc_test.go`(同目录、同 package)
```go
package calc

import "testing"

func TestAdd(t *testing.T) {
    got := Add(2, 3)
    want := 5
    if got != want {
        t.Errorf("Add(2,3) = %d; 期望 %d", got, want)
    }
}

func TestDivide(t *testing.T) {
    _, err := Divide(10, 0)
    if err == nil {
        t.Error("除以0应该返回错误,但没有")
    }
}
```

**运行**
```bash
go test ./...            # 跑所有测试
go test -v ./...         # 显示每个测试详情
go test -run TestAdd     # 只跑指定测试
```

**Day 1 理解要点**
- 测试文件名必须以 `_test.go` 结尾,函数必须 `TestXxx(t *testing.T)`
- `t.Error` 记录失败但继续,`t.Fatal` 记录失败并立即停止该测试
- 测试和被测代码同 package,可以访问未导出的函数

---

### Day 2:表驱动测试(table-driven)

Go 社区最主流的写法:把多个用例放进一张表,循环执行。

```go
func TestDivide(t *testing.T) {
    // 用例表
    tests := []struct {
        name    string // 用例名
        a, b    int
        want    int
        wantErr bool
    }{
        {name: "正常整除", a: 10, b: 2, want: 5, wantErr: false},
        {name: "除不尽取整", a: 7, b: 2, want: 3, wantErr: false},
        {name: "除以0报错", a: 1, b: 0, want: 0, wantErr: true},
    }

    for _, tt := range tests {
        // t.Run 让每个用例成为独立子测试,失败时能定位
        t.Run(tt.name, func(t *testing.T) {
            got, err := Divide(tt.a, tt.b)

            if (err != nil) != tt.wantErr {
                t.Fatalf("wantErr=%v, 实际 err=%v", tt.wantErr, err)
            }
            if got != tt.want {
                t.Errorf("got=%d, want=%d", got, tt.want)
            }
        })
    }
}
```

运行 `go test -v` 会看到每个子用例:
```
--- PASS: TestDivide/正常整除
--- PASS: TestDivide/除不尽取整
--- PASS: TestDivide/除以0报错
```

**Day 2 理解要点**
- 加新用例只需往表里加一行,不用复制粘贴整个测试
- `t.Run(name, ...)` 让每个用例独立,报错时能精确定位是哪个用例
- 这就是 Go 版的 `test.each`,一定要养成习惯

---

### Day 3:testify 让断言更好写

原生 `if got != want { t.Error }` 写多了很啰嗦,testify 提供更简洁的断言。

```bash
go get github.com/stretchr/testify
```

```go
import (
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestUser(t *testing.T) {
    u := NewUser("Alice", 20)

    // assert:失败后继续执行
    assert.Equal(t, "Alice", u.Name)
    assert.Equal(t, 20, u.Age)
    assert.True(t, u.IsAdult())
    assert.NotNil(t, u)

    // require:失败后立即停止(适合前置条件)
    require.NoError(t, u.Validate())
}
```

常用断言速查:

| 方法 | 含义 |
|---|---|
| `assert.Equal(t, want, got)` | 相等 |
| `assert.NoError(t, err)` | err 为 nil |
| `assert.Error(t, err)` | err 不为 nil |
| `assert.True/False(t, v)` | 布尔判断 |
| `assert.Nil/NotNil(t, v)` | nil 判断 |
| `assert.Len(t, list, 3)` | 长度判断 |

**Day 3 理解要点**
- `assert` 失败后继续,能一次看到多个失败点
- `require` 失败后立即停止,适合"这步不过后面没意义"的前置检查
- testify 的写法最接近你熟悉的 Jest `expect`

---

### Day 4:为什么需要 Mock + 定义接口

**痛点**:Service 层通常依赖数据库、Redis、第三方 API。测试时不能真连数据库,慢且不稳定。

**解决**:把依赖抽象成接口,测试时用假的实现(mock)替换。

**先把依赖定义成接口**
```go
package user

// UserRepo 是数据访问接口,Service 依赖它而不是具体实现
type UserRepo interface {
    GetByID(ctx context.Context, id int) (*User, error)
    Save(ctx context.Context, u *User) error
}

// Service 依赖接口(依赖倒置)
type Service struct {
    repo UserRepo
}

func NewService(repo UserRepo) *Service {
    return &Service{repo: repo}
}

// 被测的业务逻辑
func (s *Service) Register(ctx context.Context, name string) (*User, error) {
    if name == "" {
        return nil, errors.New("用户名不能为空")
    }
    u := &User{Name: name}
    if err := s.repo.Save(ctx, u); err != nil {
        return nil, fmt.Errorf("保存失败: %w", err)
    }
    return u, nil
}
```

**手写一个简单 mock(理解原理)**
```go
type fakeRepo struct {
    saveErr error // 控制 Save 返回什么
}

func (f *fakeRepo) GetByID(ctx context.Context, id int) (*User, error) {
    return &User{ID: id, Name: "test"}, nil
}
func (f *fakeRepo) Save(ctx context.Context, u *User) error {
    return f.saveErr
}
```

**Day 4 理解要点**
- 要 mock 谁,谁就得先是接口。Service 依赖接口而非具体实现
- 这就是"依赖倒置",让业务逻辑和数据库解耦
- 手写 mock 能理解原理,但用例多了就该用工具生成(Day 5)

---

### Day 5:gomock 自动生成 mock

手写 mock 用例一多就烦,gomock 能根据接口自动生成。

**安装**
```bash
go install go.uber.org/mock/mockgen@latest
go get go.uber.org/mock/gomock
```

**生成 mock**(在接口所在文件加注释,或直接命令行)
```bash
mockgen -source=user/repo.go -destination=user/mock_repo.go -package=user
```

**用生成的 mock 写测试**
```go
import (
    "testing"

    "go.uber.org/mock/gomock"
    "github.com/stretchr/testify/assert"
)

func TestService_Register(t *testing.T) {
    ctrl := gomock.NewController(t)
    defer ctrl.Finish()

    mockRepo := NewMockUserRepo(ctrl) // gomock 生成的

    tests := []struct {
        name    string
        input   string
        setup   func()
        wantErr bool
    }{
        {
            name:  "注册成功",
            input: "Alice",
            setup: func() {
                // 期望 Save 被调用一次,返回 nil
                mockRepo.EXPECT().Save(gomock.Any(), gomock.Any()).Return(nil)
            },
            wantErr: false,
        },
        {
            name:    "空用户名直接报错",
            input:   "",
            setup:   func() {}, // 不会调到 Save
            wantErr: true,
        },
        {
            name:  "数据库保存失败",
            input: "Bob",
            setup: func() {
                mockRepo.EXPECT().Save(gomock.Any(), gomock.Any()).
                    Return(errors.New("db error"))
            },
            wantErr: true,
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            tt.setup()
            svc := NewService(mockRepo)
            _, err := svc.Register(context.Background(), tt.input)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

**Day 5 理解要点**
- `EXPECT().Save(...).Return(...)` 设定"期望这个方法被调用并返回什么"
- `gomock.Any()` 表示不关心具体参数值
- gomock 能验证方法有没有被调用、调用几次,比手写 mock 更严格

---

### Day 6:实战 —— 为核心业务补测试 + 覆盖率

选你项目里最核心的 1-2 个 Service,补齐测试。重点测:

- **正常路径**:输入合法,返回预期结果
- **边界场景**:空值、0、负数、超长字符串
- **错误路径**:依赖返回错误时,业务能正确处理

**边界场景示例**
```go
func TestValidateAge(t *testing.T) {
    tests := []struct {
        name    string
        age     int
        wantErr bool
    }{
        {"正常年龄", 25, false},
        {"最小边界0", 0, true},
        {"负数", -1, true},
        {"上限150", 150, false},
        {"超过上限", 151, true},
    }
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := ValidateAge(tt.age)
            assert.Equal(t, tt.wantErr, err != nil)
        })
    }
}
```

**查看覆盖率**
```bash
# 命令行看总覆盖率
go test -cover ./...

# 生成详细报告,浏览器打开看哪些行没覆盖
go test -coverprofile=coverage.out ./...
go tool cover -html=coverage.out
```

浏览器里绿色是覆盖到的行,红色是没覆盖的。重点补红色的错误分支。

**Day 6 理解要点**
- 覆盖率是参考,不是目标。核心业务 70%-80% 足够,别为凑数写无意义测试
- 边界和错误分支往往藏 bug,比正常路径更值得测
- `go tool cover -html` 能直观看到哪里没测到

---

### Day 7:复盘 + 集成进 CI 准备

- 跑 `go test ./...` 确保全绿
- 跑 `go test -cover ./...` 记录核心包覆盖率
- 把 mock 文件(`mock_*.go`)提交进 git,方便团队复用
- 思考:哪些测试值得写,哪些是浪费(getter/setter 不用测)

---

## 四、本周验收清单

- [ ] 能写出一个 `TestXxx(t *testing.T)` 并跑通
- [ ] 能用表驱动方式覆盖多个用例
- [ ] 会用 `t.Run` 让每个用例成为独立子测试
- [ ] 会用 testify 的 `assert` / `require` 断言
- [ ] 能说清 `assert` 和 `require` 的区别
- [ ] 理解为什么 mock 需要先把依赖定义成接口
- [ ] 能用 gomock 生成 mock 并设定 `EXPECT`
- [ ] 能模拟"数据库返回错误"的场景并验证业务处理
- [ ] 补测了正常、边界、错误三类场景
- [ ] 核心 Service 层覆盖率达到 70%-80%

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 测试函数名写错 | 必须 `TestXxx`,首字母大写,否则不执行 |
| 直接连真数据库 | 单测要 mock 依赖,连真库慢且不稳定 |
| 只测正常路径 | bug 多在边界和错误分支,别只测 happy path |
| 追求 100% 覆盖 | getter/setter、简单转发不值得测,浪费时间 |
| 忘了 `ctrl.Finish()` | gomock 靠它验证期望是否满足 |
| 用例之间共享状态 | 每个用例要独立,别依赖前一个用例的结果 |

---

## 六、推荐资料

- **Go testing 官方文档**: pkg.go.dev/testing — 原生测试基础
- **testify**: github.com/stretchr/testify — 断言和 mock
- **gomock (uber 维护版)**: github.com/uber-go/mock — mock 生成
- **表驱动测试博客**: go.dev/blog/subtests — 理解子测试和表驱动

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | testing 入门 | 写出第一个能跑的测试 |
| Day 2 | 表驱动测试 | 用一张表覆盖多个用例 |
| Day 3 | testify 断言 | 让断言更简洁可读 |
| Day 4 | 接口与 mock 原理 | 把依赖抽象成接口 |
| Day 5 | gomock | 自动生成 mock 并设期望 |
| Day 6 | 实战补测 | 覆盖正常/边界/错误,查覆盖率 |
| Day 7 | 复盘 | 全绿,记录覆盖率,提交 mock |
