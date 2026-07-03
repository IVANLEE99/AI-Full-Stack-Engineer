# 第十六周：CI/CD 与压测

## 本周学习目标

- 掌握 CI/CD 基本概念和流程
- 学会使用 GitHub Actions 或 GitLab CI
- 能够编写自动化测试、构建、部署流水线
- 掌握接口压测工具和方法

---

## 一、学习内容

### 1. CI/CD 基础（星期一）

**核心概念**
- **CI（持续集成）**：频繁地将代码集成到主干，自动运行测试
- **CD（持续交付/部署）**：自动将代码部署到测试/生产环境
- **流水线（Pipeline）**：自动化执行的一系列任务

**CI/CD 流程**
```
代码提交 → 触发 CI → 
  1. 拉取代码
  2. 安装依赖
  3. 运行测试
  4. 构建镜像
  5. 推送镜像
  6. 部署到环境
```

**常用工具对比**
```markdown
| 工具            | 适用场景          | 配置文件              |
|-----------------|-------------------|-----------------------|
| GitHub Actions  | GitHub 项目       | .github/workflows/*.yml |
| GitLab CI       | GitLab 项目       | .gitlab-ci.yml        |
| Jenkins         | 企业级、自建      | Jenkinsfile           |
| CircleCI        | 多平台            | .circleci/config.yml  |
```

**实践任务**
- [ ] 理解 CI/CD 的价值和流程
- [ ] 选择适合的 CI/CD 工具
- [ ] 了解 YAML 配置文件语法

---

### 2. GitHub Actions 实战（星期一-星期三）

#### 2.1 基础配置

**创建第一个 Workflow**
```yaml
# .github/workflows/ci.yml
name: Go CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Set up Go
      uses: actions/setup-go@v4
      with:
        go-version: '1.21'
    
    - name: Cache dependencies
      uses: actions/cache@v3
      with:
        path: ~/go/pkg/mod
        key: ${{ runner.os }}-go-${{ hashFiles('**/go.sum') }}
        restore-keys: |
          ${{ runner.os }}-go-
    
    - name: Install dependencies
      run: go mod download
    
    - name: Run tests
      run: go test -v -race -coverprofile=coverage.txt ./...
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.txt
```

**触发条件**
```yaml
# 推送到特定分支
on:
  push:
    branches: [ main, develop ]

# Pull Request
on:
  pull_request:
    branches: [ main ]

# 定时执行（每天凌晨 2 点）
on:
  schedule:
    - cron: '0 2 * * *'

# 手动触发
on:
  workflow_dispatch:

# 组合触发
on: [push, pull_request, workflow_dispatch]
```

---

#### 2.2 构建 Docker 镜像

```yaml
# .github/workflows/build.yml
name: Build and Push Docker Image

on:
  push:
    branches: [ main ]
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Set up Docker Buildx
      uses: docker/setup-buildx-action@v2
    
    - name: Login to Docker Hub
      uses: docker/login-action@v2
      with:
        username: ${{ secrets.DOCKER_USERNAME }}
        password: ${{ secrets.DOCKER_PASSWORD }}
    
    - name: Extract metadata
      id: meta
      uses: docker/metadata-action@v4
      with:
        images: myapp/backend
        tags: |
          type=ref,event=branch
          type=semver,pattern={{version}}
          type=sha
    
    - name: Build and push
      uses: docker/build-push-action@v4
      with:
        context: .
        push: true
        tags: ${{ steps.meta.outputs.tags }}
        cache-from: type=gha
        cache-to: type=gha,mode=max
```

**Dockerfile 优化**
```dockerfile
# 多阶段构建，减小镜像体积
FROM golang:1.21-alpine AS builder

WORKDIR /app

# 缓存依赖层
COPY go.mod go.sum ./
RUN go mod download

# 构建
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o main ./cmd/server

# 最终镜像
FROM alpine:latest

RUN apk --no-cache add ca-certificates

WORKDIR /root/

COPY --from=builder /app/main .
COPY --from=builder /app/configs ./configs

EXPOSE 8080

CMD ["./main"]
```

---

#### 2.3 多环境部署

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches:
      - develop
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Determine environment
      id: env
      run: |
        if [[ $GITHUB_REF == 'refs/heads/main' ]]; then
          echo "environment=production" >> $GITHUB_OUTPUT
        else
          echo "environment=staging" >> $GITHUB_OUTPUT
        fi
    
    - name: Deploy to ${{ steps.env.outputs.environment }}
      run: |
        echo "Deploying to ${{ steps.env.outputs.environment }}"
        # 实际部署命令
        # ssh user@server "docker pull myapp:${{ github.sha }} && docker-compose up -d"
```

**使用 Secrets**
```yaml
# Settings → Secrets → Actions → New repository secret
# 配置敏感信息：
# - DOCKER_USERNAME
# - DOCKER_PASSWORD
# - SSH_PRIVATE_KEY
# - DATABASE_URL

# 使用 Secrets
steps:
  - name: Deploy
    env:
      DB_URL: ${{ secrets.DATABASE_URL }}
      SSH_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
    run: |
      echo "$SSH_KEY" > key.pem
      chmod 600 key.pem
      ssh -i key.pem user@server "docker-compose up -d"
```

---

#### 2.4 矩阵构建（多版本测试）

```yaml
name: Matrix Build

on: [push]

jobs:
  test:
    runs-on: ${{ matrix.os }}
    
    strategy:
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        go-version: ['1.20', '1.21', '1.22']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Set up Go ${{ matrix.go-version }}
      uses: actions/setup-go@v4
      with:
        go-version: ${{ matrix.go-version }}
    
    - name: Run tests
      run: go test -v ./...
```

**实践任务**
- [ ] 创建基础 CI workflow（测试 + 构建）
- [ ] 配置 Docker 镜像自动构建
- [ ] 设置分支保护规则（PR 必须通过 CI）

---

### 3. 自动化测试集成（星期三-星期四）

#### 3.1 单元测试覆盖率

```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    - uses: actions/setup-go@v4
      with:
        go-version: '1.21'
    
    - name: Run tests with coverage
      run: |
        go test -v -race -coverprofile=coverage.out -covermode=atomic ./...
        go tool cover -func=coverage.out
    
    - name: Check coverage threshold
      run: |
        coverage=$(go tool cover -func=coverage.out | grep total | awk '{print $3}' | sed 's/%//')
        echo "Total coverage: $coverage%"
        if (( $(echo "$coverage < 70" | bc -l) )); then
          echo "Coverage is below 70%"
          exit 1
        fi
    
    - name: Upload to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.out
```

#### 3.2 代码质量检查

```yaml
jobs:
  lint:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    - uses: actions/setup-go@v4
      with:
        go-version: '1.21'
    
    - name: golangci-lint
      uses: golangci/golangci-lint-action@v3
      with:
        version: latest
        args: --timeout=5m
    
    - name: Go vet
      run: go vet ./...
    
    - name: Go fmt check
      run: |
        if [ -n "$(gofmt -l .)" ]; then
          echo "Go code is not formatted:"
          gofmt -d .
          exit 1
        fi
```

#### 3.3 集成测试

```yaml
jobs:
  integration-test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: testdb
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    
    steps:
    - uses: actions/checkout@v3
    - uses: actions/setup-go@v4
      with:
        go-version: '1.21'
    
    - name: Run integration tests
      env:
        DATABASE_URL: postgres://postgres:postgres@localhost:5432/testdb
        REDIS_URL: redis://localhost:6379
      run: go test -v -tags=integration ./...
```

---

### 4. 接口压测（星期四-星期五）

#### 4.1 wrk 压测工具

**安装**
```bash
# macOS
brew install wrk

# Ubuntu
sudo apt-get install wrk
```

**基础使用**
```bash
# 基础压测：10 并发，持续 30 秒
wrk -t10 -c100 -d30s http://localhost:8080/api/users

# 输出示例
Running 30s test @ http://localhost:8080/api/users
  10 threads and 100 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    45.32ms   12.15ms 189.18ms   68.25%
    Req/Sec   221.45     42.18   363.00     71.23%
  66435 requests in 30.03s, 15.23MB read
Requests/sec:   2211.77
Transfer/sec:    519.42KB
```

**自定义 Lua 脚本**
```lua
-- post.lua
wrk.method = "POST"
wrk.body   = '{"username":"test","password":"123456"}'
wrk.headers["Content-Type"] = "application/json"
```

```bash
wrk -t10 -c100 -d30s -s post.lua http://localhost:8080/api/login
```

#### 4.2 hey 压测工具

**安装**
```bash
go install github.com/rakyll/hey@latest
```

**使用**
```bash
# 发送 1000 个请求，50 并发
hey -n 1000 -c 50 http://localhost:8080/api/users

# POST 请求
hey -n 1000 -c 50 -m POST \
  -H "Content-Type: application/json" \
  -d '{"username":"test"}' \
  http://localhost:8080/api/login

# 持续 30 秒压测
hey -z 30s -c 100 http://localhost:8080/api/products
```

#### 4.3 vegeta 压测工具

**安装**
```bash
go install github.com/tsenart/vegeta@latest
```

**使用**
```bash
# 定义目标
echo "GET http://localhost:8080/api/users" | \
  vegeta attack -duration=30s -rate=100 | \
  vegeta report

# 多个目标
cat targets.txt | vegeta attack -duration=30s | vegeta report

# targets.txt 内容：
GET http://localhost:8080/api/users
GET http://localhost:8080/api/products
POST http://localhost:8080/api/orders
Content-Type: application/json
@order.json

# 生成报告
vegeta attack -duration=30s -rate=100 -targets=targets.txt | \
  vegeta report -type=text

# 生成 HTML 报告
vegeta attack -duration=30s -rate=100 -targets=targets.txt | \
  vegeta plot > report.html
```

#### 4.4 压测最佳实践

**压测前准备**
```markdown
1. 确定压测目标
   - QPS 目标
   - 响应时间要求（P50, P95, P99）
   - 最大并发数

2. 准备测试环境
   - 独立的测试环境
   - 与生产配置接近
   - 清理测试数据

3. 监控指标
   - CPU 使用率
   - 内存使用率
   - 数据库连接数
   - 接口响应时间
```

**压测步骤**
```bash
# 1. 预热
hey -n 100 -c 10 http://localhost:8080/api/users

# 2. 基准测试（单接口）
wrk -t4 -c100 -d30s http://localhost:8080/api/users

# 3. 逐步加压
hey -z 10s -c 50 http://localhost:8080/api/users
hey -z 10s -c 100 http://localhost:8080/api/users
hey -z 10s -c 200 http://localhost:8080/api/users
hey -z 10s -c 500 http://localhost:8080/api/users  # 找到拐点

# 4. 混合场景
vegeta attack -duration=60s -rate=200 -targets=mix.txt | vegeta report
```

**压测报告模板**
```markdown
## 压测报告

### 测试环境
- 服务器：4核8G
- 数据库：PostgreSQL 15
- 缓存：Redis 7

### 测试场景
- 接口：GET /api/products
- 并发：100
- 持续时间：60s

### 测试结果
| 指标 | 值 |
|------|-----|
| 总请求数 | 120,000 |
| 成功数 | 119,850 |
| 失败数 | 150 (0.13%) |
| QPS | 2,000 |
| P50 延迟 | 45ms |
| P95 延迟 | 120ms |
| P99 延迟 | 250ms |

### 瓶颈分析
- 数据库连接池达到上限
- Redis 缓存命中率 85%

### 优化建议
1. 增加数据库连接池大小
2. 优化慢 SQL
3. 提高缓存命中率
```

---

## 二、本周实战任务

### 任务 1：配置 CI/CD 流水线（星期五）

**目标**
- 配置自动化测试
- 配置自动构建 Docker 镜像
- 实现自动部署到测试环境

**验收标准**
- [ ] Push 代码后自动运行测试
- [ ] 测试失败时阻止合并
- [ ] main 分支自动构建并推送镜像
- [ ] 测试覆盖率 > 70%

---

### 任务 2：接口压测（星期六-星期日）

**目标**
- 对 3-5 个核心接口进行压测
- 找出性能瓶颈
- 输出压测报告

**验收标准**
- [ ] 完成至少 3 个接口的压测
- [ ] 找出瓶颈并优化
- [ ] 输出压测报告（包含前后对比）
- [ ] QPS 提升 50% 以上

---

## 三、推荐资源

### 必读文档
- [GitHub Actions 文档](https://docs.github.com/en/actions)
- [Docker 最佳实践](https://docs.docker.com/develop/dev-best-practices/)
- [wrk 使用指南](https://github.com/wg/wrk)

### 推荐工具
- [act](https://github.com/nektos/act) - 本地运行 GitHub Actions
- [k6](https://k6.io/) - 现代化压测工具
- [Grafana k6](https://grafana.com/docs/k6/) - 压测可视化

---

## 四、本周复盘模板

```markdown
### 第 16 周复盘

**这周学了什么**
- CI/CD 流程和 GitHub Actions
- Docker 镜像构建和优化
- 接口压测工具和方法

**这周做了什么**
- 配置了自动化测试和构建流水线
- 对 5 个核心接口进行了压测
- 优化后 QPS 提升了 80%

**卡在哪里**
- GitHub Actions 的 Secrets 配置
- 压测结果分析不太熟练

**真正掌握了什么**
- 能独立配置 CI/CD 流水线
- 会用 wrk 和 hey 进行压测
- 理解了性能瓶颈的排查方法

**下周怎么调整**
- 进入 AI 服务开发阶段
- 学习 SSE 流式接口
```

---

## 五、下周预告

**第十七周：SSE 与流式接口**
- SSE（Server-Sent Events）协议
- 流式接口实现
- 前端打字机效果
- 大模型流式输出对接
