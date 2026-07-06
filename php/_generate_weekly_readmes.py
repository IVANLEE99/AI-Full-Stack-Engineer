#!/usr/bin/env python3
"""Generate week01-week24 README.md with detailed 7-day learning plans."""
from pathlib import Path

BASE = Path(__file__).parent
HEADER = """> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。
"""
DAY_META = [
    ("Day 1（周一）", "概念入门"),
    ("Day 2（周二）", "源码阅读"),
    ("Day 3（周三）", "编码练习"),
    ("Day 4（周四）", "架构理解"),
    ("Day 5（周五）", "类比日"),
    ("Day 6（周六）", "项目实战"),
    ("Day 7（周日）", "复盘预习"),
]


def day_block(label, dtype, d):
    lines = [
        f"### {label}：{d['title']}",
        "",
        f"**类型**：{dtype}  ",
        f"**今日目标**：{d['goal']}",
        "",
        "**学习内容**：",
    ]
    for x in d["learn"]:
        lines.append(f"- {x}")
    lines.append("")
    if d.get("source"):
        lines.append("**源码阅读**：")
        for x in d["source"]:
            lines.append(f"- `{x}`")
        lines.append("")
    lines.append("**练习任务**：")
    for x in d["practice"]:
        lines.append(f"- {x}")
    lines.append("")
    lines.append("**JS/Node 类比**：")
    for x in d["analogy"]:
        lines.append(f"- {x}")
    lines.append("")
    lines.append("**AI Review 提问**：")
    for x in d["ai"]:
        lines.append(f"- {x}")
    lines.append("")
    lines.append("**今日产出**：")
    for x in d["output"]:
        lines.append(f"- {x}")
    lines.append("")
    lines.append("**今日完成标准**：")
    for x in d["check"]:
        lines.append(f"- [ ] {x}")
    lines.append("")
    return "\n".join(lines)


def render_day(w, day_index, d):
    label, dtype = DAY_META[day_index - 1]
    lines = [
        f"# Week {w['n']:02d} Day {day_index:02d}：{d['title']}",
        "",
        f"> 所属周：Week {w['n']:02d}：{w['title']}  ",
        f"> 阶段：{w['phase']}  ",
        f"> 主仓库/项目：`{w['repo']}`  ",
        f"> 类型：{dtype}  ",
        "> 建议时长：约 3h  ",
        "> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review",
        "",
        "---",
        "",
        "## 今日目标",
        "",
        d["goal"],
        "",
        "---",
        "",
        "## 1. 学习内容",
        "",
    ]
    for x in d["learn"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 2. 源码阅读", ""])
    if d.get("source"):
        for x in d["source"]:
            lines.append(f"- `{x}`")
        lines.extend([
            "",
            "> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。",
        ])
    else:
        lines.append("本日无指定源码阅读，重点完成练习与复盘。")
    lines.extend(["", "---", "", "## 3. 练习任务", ""])
    for x in d["practice"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 4. JS/Node.js 类比", ""])
    for x in d["analogy"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 5. AI Review 提问", ""])
    for x in d["ai"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 6. 今日产出", ""])
    for x in d["output"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 7. 今日完成标准", ""])
    for x in d["check"]:
        lines.append(f"- [ ] {x}")
    lines.extend([
        "",
        "---",
        "",
        "## 8. 学习记录",
        "",
        "| 记录项 | 内容 |",
        "|--------|------|",
        "| 今日最清楚的概念 |  |",
        "| 今日最卡的概念 |  |",
        "| JS/Node 类比是否帮助理解 |  |",
        "| 实际耗时 |  |",
        "| 明日要补的问题 |  |",
        "",
        "---",
        "",
        "## 9. AI Review 提示词",
        "",
        "```text",
        f"我正在进行 Week {w['n']:02d} Day {day_index:02d}：{d['title']} 的学习。",
        "请你扮演资深 PHP 后端工程师，帮我检查：",
        "1. 今日理解是否正确",
        "2. JS/Node 类比是否准确",
        "3. 练习任务是否遗漏关键风险",
        "4. 真实企业项目中还需要注意什么",
        "",
        "请用中文输出：问题清单、修正建议、下一步练习。",
        "```",
        "",
        "---",
        "",
        "## 返回本周",
        "",
        f"- [返回 Week {w['n']:02d} README](./README.md)",
        "",
    ])
    return "\n".join(lines)


def validate_weeks(weeks):
    required_week_keys = {"n", "title", "phase", "repo", "goal", "days"}
    required_day_keys = {"title", "goal", "learn", "practice", "analogy", "ai", "output", "check"}
    if len(weeks) != 24:
        raise ValueError(f"Expected 24 weeks, got {len(weeks)}")
    week_numbers = [w.get("n") for w in weeks]
    if week_numbers != list(range(1, 25)):
        raise ValueError(f"Expected week numbers 1-24, got {week_numbers}")
    for w in weeks:
        missing_week_keys = required_week_keys - set(w)
        if missing_week_keys:
            raise ValueError(f"Week {w.get('n')} missing keys: {sorted(missing_week_keys)}")
        if len(w["days"]) != 7:
            raise ValueError(f"Week {w['n']:02d} expected 7 days, got {len(w['days'])}")
        for i, d in enumerate(w["days"], start=1):
            missing_day_keys = required_day_keys - set(d)
            if missing_day_keys:
                raise ValueError(f"Week {w['n']:02d} day {i:02d} missing keys: {sorted(missing_day_keys)}")


def render(w):
    lines = [
        f"# Week {w['n']:02d}：{w['title']}",
        "",
        HEADER,
        "---",
        "",
        "## 1. 本周定位",
        "",
        f"- 阶段：{w['phase']}",
        f"- 主仓库/项目：`{w['repo']}`",
        f"- 本周目标：{w['goal']}",
        "",
        "### 为什么本周要学这些",
        "",
    ]
    for x in w["why"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 2. 本周需要掌握的知识点", ""])
    for i, x in enumerate(w["skills"], 1):
        lines.append(f"{i}. {x}")
    lines.extend(["", "### php-pro 能力对齐", ""])
    for x in w["quality"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 3. 必读代码/文件路径", ""])
    for p in w["paths"]:
        lines.append(f"- `{p}`")
    lines.extend([
        "",
        "> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。",
        "",
        "---",
        "",
        "## 4. 七天详细学习安排",
        "",
        "| 天 | 类型 | 主题 |",
        "|----|------|------|",
    ])
    for i, d in enumerate(w["days"]):
        day_link = f"./day{i + 1:02d}.md"
        lines.append(f"| [{DAY_META[i][0]}]({day_link}) | {DAY_META[i][1]} | {d['title']} |")
    lines.append("")
    for i, d in enumerate(w["days"]):
        lines.append(day_block(DAY_META[i][0], DAY_META[i][1], d))
        lines.append("---")
        lines.append("")
    lines.extend([
        "## 5. JS/Node.js 类比学习（本周总览）",
        "",
        w["compare"],
        "",
        "### 本周类比打卡模板",
        "",
        "```text",
        "本周概念：",
        "Node 等价：",
        "差异：",
        "我能用自己的话解释吗：是 / 否",
        "理解自评：1 / 2 / 3 / 4 / 5",
        "```",
        "",
        "---",
        "",
        "## 6. 本周产出物",
        "",
    ])
    for x in w["deliverables"]:
        lines.append(f"- [ ] {x}")
    lines.extend(["", "---", "", "## 7. 推荐学习资料", ""])
    for x in w["resources"]:
        lines.append(f"- {x}")
    lines.extend(["", "---", "", "## 8. 本周验收标准", ""])
    for x in w["acceptance"]:
        lines.append(f"- [ ] {x}")
    lines.extend([
        "",
        "---",
        "",
        "## 9. AI Review 提示词",
        "",
        "```text",
        f"我正在进行 Week {w['n']:02d}：{w['title']} 的学习。",
        "请你扮演资深 PHP 后端工程师，帮我检查：理解是否正确、JS 类比是否准确、是否遗漏风险、真实项目需注意什么。",
        "请用中文输出：问题清单、修正建议、下一步练习。",
        "```",
        "",
        "---",
        "",
        "## 10. 周日复盘与下周预习",
        "",
        "| 复盘项 | 记录 |",
        "|--------|------|",
        "| 本周最清楚的概念 |  |",
        "| 本周最卡的概念 |  |",
        "| JS/Node 类比是否帮助理解 |  |",
        "| 本周产出是否完成 |  |",
        "| 自评分（1-5） |  |",
        "",
        f"**下周预习**：{w['next']}",
        "",
    ])
    return "\n".join(lines)


def D(title, goal, learn, practice, analogy, ai, output, check, source=None):
    return {
        "title": title, "goal": goal, "learn": learn, "source": source or [],
        "practice": practice, "analogy": analogy, "ai": ai, "output": output, "check": check,
    }


WEEKS = [
{
"n":1,"title":"PHP 8 + Composer + OOP","phase":"第一阶段：PHP + Yii2/TP 基础","repo":"mall-core",
"goal":"建立 PHP 语法、OOP 与工程化基础，能读懂 composer.json、namespace、autoload 和基础类。",
"why":["第一周先建立 PHP 语言手感，不急着啃业务。","重点是把 PHP 工程结构与 Node 工程结构对应起来。","读 BaseService/BaseRepository 是理解后续所有业务代码的骨架。"],
"skills":["PHP 8 语法与类型","OOP：class/interface/abstract","Composer 与 PSR-4","PSR-12 编码规范","Trait 与 Exception"],
"quality":["按 PSR-12 书写","方法补 return type","异常后统一错误结构"],
"paths":["mall-core/composer.json","mall-core/common/BaseService.php","mall-core/common/BaseRepository.php"],
"days":[
 D("PHP 8 类型系统与工程入口","理解 PHP 类型、strict types、Composer autoload。",
   ["PHP Manual：Types/Variables/Functions","Composer：PSR-4 autoload"],
   ["确认 php -v、composer -V","写 namespace→文件路径映射示例","列 PHP 与 JS 类型差异 10 条"],
   ["Composer≈npm","vendor/≈node_modules","PSR-4≈exports+import"],
   ["autoload 与 Node import 类比准确吗？","strict_types 作用是什么？"],
   ["类型差异笔记","autoload 示例"],["能解释 vendor/autoload.php","能解释 PSR-4","能说出 3 个 PHP8 特性"],
   ["mall-core/composer.json"]),
 D("OOP 与 ES6 Class 对比","掌握继承、多态、interface、abstract。",
   ["PHP Classes/Interfaces","ES6 Class 对照"],
   ["写 Animal/Dog/Cat 示例","写 interface+两实现","读 BaseService 标单例代码"],
   ["PHP class≈ES6 class","interface≈TS interface"],
   ["PHP interface 与 TS 差异？","单例在 Node 如何实现？"],
   ["OOP 练习代码","BaseService 笔记"],["能写 interface 层次","能解释多态","能说明单例用途"],
   ["mall-core/common/BaseService.php"]),
 D("namespace 与 Composer 依赖","理解 namespace、use、PSR-4 映射。",
   ["PSR-4 规范","composer autoload 配置"],
   ["追踪类 namespace 到文件","画目录与 namespace 图"],
   ["namespace≈ES Module","use≈import","autoload≈自动 require"],
   ["namespace 映射图正确吗？","Repository 命名合理吗？"],
   ["映射图","Repository 笔记"],["能推导类文件路径","能解释 PSR-4","能读懂 BaseRepository"],
   ["mall-core/composer.json","mall-core/common/BaseRepository.php"]),
 D("Trait、Exception 与企业基类","掌握 Trait、异常、基类职责。",
   ["PHP Traits/Exceptions","PSR-12 摘要"],
   ["写 Trait 示例","写 try/catch 统一返回","对比 Service vs Repository"],
   ["Trait≈Mixin","Exception≈throw Error","统一错误≈res.json({code,msg})"],
   ["Trait 与 Mixin 差异？","为何统一异常处理？"],
   ["Trait 示例","异常示例","基类对比表"],["能解释 Trait","能写 return type","能区分 Service/Repository"],
   ["mall-core/common/BaseService.php","mall-core/common/BaseRepository.php"]),
 D("PSR-12 与类比日","速读 PSR-12，启动 Todo API，完成类比打卡。",
   ["PSR-12","REST 基础"],
   ["建 Todo 项目骨架","统一 JSON 响应","完成类比打卡"],
   ["REST≈Express router","统一响应≈res.json"],
   ["目录结构符合 PSR-12 吗？","类比准确吗？"],
   ["Todo 骨架","类比打卡"],["目录清晰","有统一响应","完成打卡"],[]),
 D("Todo REST API 实战","完成 CRUD + 测试 + README。",
   ["REST 方法/状态码","请求体读取"],
   ["实现 5 个 CRUD","curl/Postman 全测","写 README"],
   ["CRUD≈Express REST","curl≈Postman 调试"],
   ["代码质量如何？","缺哪些企业级改进？"],
   ["可运行 Todo API","测试记录","README"],["5 接口可用","错误有状态码","README 可运行"],[]),
 D("验收与预习","验收、自评、预习 Yii2。",
   ["回顾笔记","预习 Yii2 结构概述"],
   ["逐项验收","写周总结","列下周 3 个问题"],
   ["周总结≈Sprint Review"],
   ["准备好学 Yii2 了吗？","PHP 短板？"],
   ["周总结","疑难点清单"],["完成验收","完成自评","明确下周目标"],[]),
],
"compare":"Composer≈npm；namespace≈ES Module；Trait≈Mixin。",
"deliverables":["Todo API","PSR-4 笔记","PHP↔JS 对照","周总结"],
"resources":["PHP 8.x 手册","Composer 文档","PSR-12","《Modern PHP》"],
"acceptance":["能解释 Composer vs npm","能解释 PSR-4","能说出 3 个 PHP8 特性","Todo API 可用"],
"next":"预习 Yii2 生命周期、Module、Filter；确认 mall-gateway 可访问。",
},
{
"n":2,"title":"Yii2 生命周期与 Filter","phase":"第一阶段：PHP + Yii2/TP 基础","repo":"mall-gateway",
"goal":"理解 Yii2 请求从入口到 action 的完整生命周期与 Filter 链。",
"why":["后续接口多是 Yii2，先懂生命周期再读 Controller。","Filter 最接近 Express middleware。"],
"skills":["Application 生命周期","Module/Controller 路由","behaviors/Filter","BaseForm 校验","Laravel Middleware 对比"],
"quality":["鉴权不可随意改","区分公开/登录/内网接口","记录免登录理由"],
"paths":["mall-gateway/frontapi/web/index.php","mall-gateway/frontapi/config/modules/Modules.php","mall-gateway/frontapi/modules/AuthApiController.php","mall-gateway/frontapi/modules/Pay/controllers/PayController.php"],
"days":[
 D("Yii2 入口与启动","理解 index.php 与配置合并。",
   ["Yii2 结构概述","runtime-overview"],
   ["追踪 config 加载链","画启动流程图","对比 Express 启动"],
   ["index.php≈server.js","config merge≈多配置合并"],
   ["启动流程图准确吗？"],["启动流程图","config 笔记"],["能说出入口做了什么","能解释配置合并"],["mall-gateway/frontapi/web/index.php"]),
 D("Module 与路由","掌握模块化路由与 action 命名。",
   ["Controllers/Modules","URL 规则"],
   ["列已注册 Module","解释 pay/pay/methods 映射","写 3 个路由表"],
   ["Module≈router 前缀","actionXxx≈handler"],
   ["路由映射正确吗？"],["路由表","Module 笔记"],["能解释 Module 路由","能推导 3 个 URL"],["mall-gateway/frontapi/config/modules/Modules.php"]),
 D("behaviors 与 Filter","理解 Filter 链与 beforeAction。",
   ["Filters/Behaviors"],
   ["列 behaviors","画 Filter 顺序","读一个 Filter 源码"],
   ["behaviors≈app.use(middleware)","beforeAction≈前置钩子"],
   ["Filter 顺序对吗？"],["Filter 链图"],["能画 Filter 链","能解释 beforeAction"],["mall-gateway/frontapi/modules/AuthApiController.php"]),
 D("BaseForm 校验","掌握 rules/scenarios 与错误返回。",
   ["Yii2 验证","Zod/Joi 对照"],
   ["找 Form 列 rules","写校验对照表","手写 Form 示例"],
   ["BaseForm≈Zod","scenarios≈不同 schema 子集"],
   ["与 Zod 差异？"],["Form 对照表","Form 示例"],["能解释 rules/scenarios"],[]),
 D("Laravel 对比与类比日","写 Middleware vs behaviors 对照。",
   ["Laravel Middleware 文档"],
   ["写 1 页对照笔记","完成类比打卡","整理白名单初稿"],
   ["Laravel middleware≈Yii2 Filter"],
   ["对照抓住关键差异吗？"],["对照笔记","类比打卡"],["完成对照","完成打卡"],[]),
 D("鉴权白名单与路径图","整理 5 个免登录接口与完整路径图。",
   ["鉴权基类","白名单配置"],
   ["列 5 接口及原因","画 index→action 路径","标 Filter"],
   ["白名单≈public routes"],
   ["免登录接口安全吗？"],["白名单文档","路径图"],["5 接口清晰","路径图完整"],["mall-gateway/frontapi/modules/AuthApiController.php"]),
 D("验收与预习","验收并预习 MySQL/AR。",
   ["回顾 Filter/Form","预习 ActiveRecord"],
   ["勾选验收","写总结","列 OrderRepository 计划"],
   ["预习≈读下一章"],["准备好学数据库吗？"],["周总结","预习清单"],["完成验收","完成自评"],[]),
],
"compare":"behaviors()≈Express middleware；BaseForm≈Zod；actionXxx≈router handler。",
"deliverables":["生命周期图","Filter 链图","Laravel 对照","白名单文档"],
"resources":["Yii2 权威指南","Laravel Middleware"],
"acceptance":["能画 Filter 链","能解释 Module 路由","完成 Laravel 对照"],
"next":"预习 MySQL 索引、JOIN；准备读 OrderRepository。",
},
]

# Weeks 3-24: append via builder
def add_week(n, title, phase, repo, goal, why, skills, quality, paths, mon, tue, wed, thu, fri, sat, sun, compare, deliverables, resources, acceptance, next_preview):
    def mk(title, goal, learn, practice, analogy, ai, output, check, source=None):
        return D(title, goal, learn, practice, analogy, ai, output, check, source)
    WEEKS.append({
        "n": n, "title": title, "phase": phase, "repo": repo, "goal": goal,
        "why": why, "skills": skills, "quality": quality, "paths": paths,
        "days": [mk(*mon), mk(*tue), mk(*wed), mk(*thu), mk(*fri), mk(*sat), mk(*sun)],
        "compare": compare, "deliverables": deliverables, "resources": resources,
        "acceptance": acceptance, "next": next_preview,
    })

S = lambda t,g,l,p,a,ai,o,c,src=None: (t,g,l,p,a,ai,o,c,src)

add_week(3,"MySQL + Redis + ORM","第一阶段：PHP + Yii2/TP 基础","mall-core",
"掌握 MySQL、ActiveRecord、Repository、Redis 与 N+1 问题。",
["业务后端核心是数据读写。","Repository 是后续读代码主线。"],
["MySQL JOIN/索引","ActiveRecord","Repository","Redis","N+1/with()"],
["查询注意索引","Repository 不做业务","缓存考虑失效"],
["mall-core/common/repositorys/order/OrderRepository.php","mall-core/common/models/order/Order.php","mall-core/common/redis/order/OrderRedis.php"],
S("MySQL 与索引基础","理解 SELECT/JOIN/索引。",
  ["高性能 MySQL 索引章","Yii2 DB 基础"],
  ["读 OrderRepository 前100行","写1个 JOIN 练习题","解释第一个复杂查询"],
  ["SQL≈任何后端都需","索引≈查询性能关键"],
  ["复杂查询理解对吗？"],["SQL 练习","查询笔记"],["能解释 JOIN","能写基础查询"],["mall-core/common/repositorys/order/OrderRepository.php"]),
S("ActiveRecord 模型","掌握 AR 链式查询。",
  ["Yii2 ActiveRecord"],
  ["读 Order Model","对比 Sequelize findOne","列 5 个常用查询方法"],
  ["AR≈Sequelize Model"],
  ["AR 与 Sequelize 差异？"],["Model 笔记"],["能读 AR 查询"],["mall-core/common/models/order/Order.php"]),
S("Repository 模式","理解 Repository 职责与命名。",
  ["Repository 模式"],
  ["找 getOrderObjByNo 等方法","解释为何 Service 不直接 SQL","写 Repository 职责表"],
  ["Repository≈DAO 层"],
  ["命名规范合理吗？"],["Repository 清单"],["能解释 Repository 边界"],["mall-core/common/repositorys/order/OrderRepository.php"]),
S("Redis 缓存","理解 Redis 封装与使用场景。",
  ["Redis 五大数据类型"],
  ["读 OrderRedis","列缓存读写场景","画缓存流程"],
  ["Redis≈ioredis","缓存≈减少 DB 压力"],
  ["何时该缓存？"],["缓存流程图"],["能说明缓存场景"],["mall-core/common/redis/order/OrderRedis.php"]),
S("N+1 与类比日","理解 N+1 与 with() 预加载。",
  ["N+1 问题","eager loading"],
  ["对照订单列表前端字段","找 Repository 数据来源","完成类比打卡"],
  ["N+1≈循环里 await 查库","with()≈include/join 预加载"],
  ["字段对照准确吗？"],["字段对照表","类比打卡"],["能解释 N+1"],[]),
S("订单 ER 图实战","画 ER 图并验证 SQL。",
  ["ER 建模"],
  ["画 order/order_goods/order_address ER","执行 SQL 验证"],
  ["ER≈数据模型设计图"],
  ["ER 合理吗？"],["ER 图","SQL 验证记录"],["ER 图完成"],[]),
S("验收与预习","验收并预习配置中心。",
  ["回顾 DB 笔记","预习 g_config"],
  ["勾选验收","写总结"],["预习配置中心"],["准备好学配置吗？"],["周总结"],["完成验收"],[]),
"AR≈Sequelize；Repository≈DAO；Redis≈ioredis。",
["ER 图","Repository 清单","字段对照表","周总结"],
["Yii2 ActiveRecord","Redis 命令参考","《高性能 MySQL》"],
["能解释 N+1","能对照前端字段","完成 ER 图"],
"预习 g_config、ConfigHelper、站点配置 API。")

add_week(4,"配置中心 + 站点 API","第一阶段：PHP + Yii2/TP 基础","mall-core",
"理解动态配置、g_config、配置中心与硬编码边界。",
["大量业务行为由配置控制。","不懂配置就很难理解线上差异。"],
["g_config","ConfigHelper","配置中心概念","配置 API","Laravel config 对比"],
["动态配置有默认值","禁止硬编码模块字符串","记录配置影响范围"],
["mall-core/common/libraries/App/fun_helpers.php","mall-core/common/libraries/App/Utils/ConfigHelper.php","site-api/controllers/ConfigController.php"],
S("g_config 函数","理解配置读取函数。",
  ["读 fun_helpers 中 g_config","读 ConfigHelper 模块常量"],
  ["列 3 个 module/key/default","对比 process.env"],
  ["g_config≈process.env+热更新"],
  ["与 dotenv 差异？"],["配置函数笔记"],["能解释 g_config 参数"],["mall-core/common/libraries/App/fun_helpers.php"]),
S("配置 API 全链路","追踪 ConfigController 调用链。",
  ["站点配置业务"],
  ["追踪 ConfigController→Service","记录配置如何影响前端"],
  ["配置 API≈前端 settings 接口"],
  ["链路完整吗？"],["配置链路图"],["能追踪全链路"],["site-api/controllers/ConfigController.php"]),
S("配置中心概念","理解远程配置与本地缓存。",
  ["Nacos 配置管理文档"],
  ["画 远程配置→本地→g_config 流程","列动态 vs 静态配置场景"],
  ["配置中心≈Consul/etcd"],
  ["何时用动态配置？"],["配置流程图"],["能区分配置类型"],[]),
S("Laravel 对比","写 config() vs g_config() 对照。",
  ["Laravel Configuration"],
  ["写对照笔记","找 3 个配置影响业务的例子"],
  ["config()≈g_config()"],
  ["对照准确吗？"],["Laravel 对照笔记"],["完成对照"],[]),
S("阶段总结与类比日","整理 W1-W4 笔记，读通 CSR 链路。",
  ["回顾前4周"],
  ["独立读通一条 CSR 链路","完成类比打卡","列配置项清单"],
  ["CSR≈Controller→Service→Repository→Model"],
  ["CSR 理解对吗？"],["CSR 笔记","配置清单","阶段总结"],["能读通 CSR"],[]),
S("配置项清单项目","输出完整配置项清单表格。",
  ["配置管理最佳实践"],
  ["完成 module/key/default/影响/风险 表格"],
  ["配置清单≈feature flags 文档"],
  ["清单完整吗？"],["配置项清单"],["清单完成"],[]),
S("阶段①验收","完成阶段自评与预习网关。",
  ["阶段自评表"],
  ["填自评","写阶段总结","预习 BFF"],
  ["阶段复盘≈里程碑回顾"],["能进入微服务学习吗？"],["阶段总结","自评表"],["完成阶段①验收"],[]),
"g_config≈process.env+远程热更新；禁止硬编码≈禁止写死 API_KEY。",
["配置项清单","CSR 链路笔记","阶段①总结"],
["Nacos 文档","Laravel Configuration"],
["能区分动态/静态配置","能读通 CSR","完成阶段自评"],
"预习 BFF 模式、mall-gateway 目录结构。")

# Weeks 5-24 with detailed daily plans
from _weeks_5_24_detailed import DETAILED

for spec in [
(5,"BFF 网关架构","第二阶段：网关 + 微服务","mall-gateway","理解 BFF 鉴权、转发、薄 Controller。",
["前端请求先进网关。","网关不做核心业务。"],
["BFF","HTTP Client","薄 Controller","鉴权公参","Laravel HTTP Client"],
["网关不写业务","跨服务走封装","白名单谨慎"],
["mall-gateway/common/BaseApi.php","mall-gateway/services/http/PayRequest.php","mall-gateway/services/http/OrderRequest.php","mall-gateway/frontapi/modules/Pay/controllers/PayController.php"],
"薄 Controller≈Express proxy；*Request≈axios.create。",
["API 路由表","BFF 边界笔记","Laravel HTTP 对照"],
["Building Microservices","Laravel HTTP Client"],
["能说明网关边界","完成路由表"],
"预习订单域 OrderController。"),
(6,"订单域","第二阶段：网关 + 微服务","mall-core","深入订单下单、校验、锁、状态机。",
["订单是电商核心。","能串起商品/支付/用户。"],
["OrderController","OrderService","Form 校验","分布式锁","状态机"],
["下单关注幂等","金额字段谨慎","错误码可理解"],
["order-api/controllers/OrderController.php","mall-core/common/services/order/OrderService.php","order-api/forms/OrderConfirmForm.php"],
"Form≈Joi；Lock≈Redis SET NX；statusMap≈前端 badge。",
["下单时序图","Form 对照表","状态机图"],
["订单源码","Yii2 Form"],
["能对照前端字段","完成时序图"],
"预习支付 PayService。"),
(7,"支付域 + Node 流水线","第二阶段：网关 + 微服务","pay-service","理解支付 Node 链、工厂、状态机。",
["支付是高风险域。","要懂状态、幂等、渠道。"],
["PayController","PayService","PaymentFactory","Node 链","渠道 SDK"],
["支付关注幂等","金额精度","日志带 payment_no"],
["pay-service/pay-api/controllers/PayController.php","pay-service/common/services/pay/PayService.php","pay-service/common/factory/payment/PaymentFactory.php"],
"Node 链≈middleware pipeline；Factory≈handler map。",
["processPayment 图","支付状态机","渠道映射表"],
["Stripe/Braintree 文档","责任链模式"],
["能口述状态机","能画 4 Node"],
"预习 RabbitMQ/Webhook。"),
(8,"MQ + Webhook + Docker","第二阶段：网关 + 微服务","pay-service + mall-gateway","掌握 MQ、Webhook、幂等、Docker。",
["真实业务大量异步。","要懂回调与补偿。"],
["RabbitMQ","Webhook","幂等","Docker","Laravel Queue"],
["Webhook 验签","幂等 key 稳定","日志含业务 ID"],
["pay-service/pay-api/controllers/outer/StripeController.php","mall-core/common/libraries/App/Utils/RabbitMq.php"],
"MQ≈BullMQ；Webhook≈Stripe 回调；幂等≈SET NX。",
["结账全链路图","退款幂等分析","阶段②总结"],
["RabbitMQ 教程","Stripe Webhooks","Docker 入门"],
["能解释双层 Webhook","能解释幂等"],
"预习用户服务。"),
(9,"用户服务 + 注册 Node 链","第三阶段：业务域深入","user-service","理解登录、注册链、OAuth、缓存。",
["用户域连接鉴权与订单归属。"],
["UserController","RegisterService","OAuth","JWT","Redis 缓存"],
["登录关注安全","缓存不失效权限"],
["user-service/user-api/controllers/UserController.php","user-service/common/services/user/RegisterService.php"],
"注册链≈多步 middleware；缓存≈ioredis。",
["注册 Node 图","缓存策略笔记"],
["JWT 介绍","用户服务源码"],
["能说明缓存失效","完成 Node 图"],
"预习售后策略模式。"),
(10,"售后服务 + Console 任务","第三阶段：业务域深入","aftersale-service","理解售后策略、状态机、Console。",
["售后是练复杂业务的好材料。"],
["AfterSaleService","processingType","状态机","Console","支付回调"],
["策略替代 switch","API vs Console 分工"],
["aftersale-service/common/services/processingType/concrete/ReturnGoodsRefund.php","aftersale-service/console/controllers/OmsController.php"],
"策略≈switch 拆 class；Console≈CLI 脚本。",
["售后流程图","策略对比表"],
["Strategy Pattern","售后源码"],
["能区分 API/Console","完成流程图"],
"预习 ThinkPHP 8。"),
(11,"ThinkPHP 8 门店 API","第三阶段：业务域深入","store-api","理解 TP8 SMVC、Validate、ThinkORM。",
["掌握第二套 PHP 框架便于比较。"],
["TP8 架构","Validate scene","ThinkORM","ModelJoin","跨服务 Helper"],
["Validate 不写业务","复杂查询隔离"],
["store-api/app/admin/controller/store/StoreController.php","store-api/app/common/service/store/StoreService.php"],
"TP Validate≈class-validator；SMVC≈NestJS 分层。",
["CRUD 链路图","Yii2 vs TP8 对比表"],
["ThinkPHP 8 手册","ThinkORM"],
["能追踪 TP8 链路","完成对比表"],
"预习跨服务协作。"),
(12,"跨服务协作总复盘","第三阶段：业务域深入","全部后端","串联全链路，形成架构观。",
["从看单文件升级为看系统。"],
["*Internal.php","服务编排","全链路图","Laravel DI"],
["禁止 Service 直接 HTTP","架构图脱敏"],
["mall-core/common/api/PayInternal.php","store-api/app/common/library/helper/InternalServiceHelper.php"],
"*Internal≈axios 封装；整体≈BFF+微服务+MQ。",
["全链路架构文档","阶段③总结"],
["Building Microservices","Laravel Container"],
["能标注每跳","完成架构文档"],
"预习 FastAPI。"),
(13,"FastAPI + LLM Gateway","第四阶段：AI Backend","ai-lab/llm-gateway","搭建多模型 LLM Gateway。",
["AI 需要稳定 HTTP 入口。"],
["FastAPI","Pydantic","多模型","API Key","错误处理"],
["API Key 不写死","超时统一处理"],
["ai-lab/llm-gateway/app/main.py"],
"FastAPI≈Express；Pydantic≈Zod。",
["Gateway 源码","curl 测试记录"],
["FastAPI 教程","OpenAI/Anthropic API"],
["能切换 2 模型","接口可测"],
"预习 MCP 协议。"),
(14,"MCP Protocol + MCP Server","第四阶段：AI Backend","mcp-server","开发 dev 只读 MCP Tool。",
["MCP 是 Agent 调用系统能力的入口。"],
["MCP 协议","Tool 注册","stdio","Cursor 集成","安全边界"],
["禁止生产库","Tool 参数 schema 化"],
["mcp-server/*/src/index.ts","ai-workspace/mcp.config.example.json"],
"MCP Tool≈OpenAI tools[]；stdio≈child_process。",
["MCP Tool","配置说明","安全规范"],
["MCP 规范","MCP TS SDK"],
["Cursor 能调用","未连生产"],
"预习 Agent Tool Calling。"),
(15,"Agent + Tool Calling","第四阶段：AI Backend","ai-lab/customer-agent","构建客服 Agent 调 MCP。",
["从调 LLM 升级为 LLM 调工具。"],
["Agent 架构","Tool Calling","Prompt","多场景","Fallback"],
["System Prompt 约束边界","记录 tool_call"],
["ai-lab/customer-agent/src/agent.ts"],
"Agent 循环≈while+tool_call+feed back。",
["Agent Demo","Prompt 文档","测试场景"],
["OpenAI Function Calling","Anthropic Tool Use"],
["3 意图正确响应","有 fallback"],
"预习 LangGraph。"),
(16,"编排模式对比","第四阶段：AI Backend","pay-service + ai-lab","对比 LangGraph 与 NodeExecutionEngine。",
["支付/售后/Agent 都是流程编排。"],
["LangGraph","NodeEngine","Context","条件分支"],
["节点单一职责","Context 清晰"],
["pay-service/common/services/pay/PayService.php","aftersale-service/common/services/nodes/"],
"LangGraph State≈Redux；PHP Node≈middleware pipeline。",
["LangGraph Demo","编排对比文档","阶段④总结"],
["LangGraph 文档"],
["能说出 3 处异同","完成对比文档"],
"预习 RAG Embedding。"),
(17,"Embedding + Chunk","第五阶段：RAG + 企业知识库","ai-lab/rag","构建向量化与召回流水线。",
["RAG 基础是文档变可搜索知识。"],
["Embedding","Chunk","向量库","metadata","Top-K"],
["文档脱敏","metadata 保留来源"],
["ai-lab/rag/indexer.py","ai-lab/rag/chunking.py"],
"Chunk≈split()；Embedding≈文本转向量。",
["indexer","召回测试报告"],
["OpenAI Embeddings","ChromaDB"],
["能召回相关段落","10+ 片段索引"],
"预习 Hybrid Search。"),
(18,"Hybrid Search + Rerank","第五阶段：RAG + 企业知识库","ai-lab/rag","提升 FAQ 检索准确率。",
["企业问答需关键词+语义+重排。"],
["BM25","Hybrid Search","Rerank","FAQ Agent","评估"],
["回答附引用","不确定时说不知道"],
["ai-lab/rag/search.py","ai-lab/faq-agent/"],
"Hybrid≈两路合并；Rerank≈二次排序。",
["FAQ Agent","准确率评估表"],
["Cohere Rerank","LangChain Hybrid Search"],
["准确率>70%","回答有引用"],
"预习 Session/Memory。"),
(19,"Memory + Session","第五阶段：RAG + 企业知识库","ai-lab/customer-agent","实现多轮对话上下文。",
["真实助手必须记住上下文。"],
["SessionManager","上下文窗口","摘要压缩","多轮测试"],
["考虑 token 限制","Session 过期"],
["ai-lab/customer-agent/session.ts"],
"Session≈Map<id,messages[]>；摘要≈压缩历史。",
["SessionManager","Memory 设计文档","5轮测试"],
["OpenAI Conversation State","LangChain Memory"],
["5轮不丢上下文"],
"预习 Multi-Agent。"),
(20,"Multi-Agent 工作流","第五阶段：RAG + 企业知识库","ai-lab/multi-agent","三 Agent 流水线协作。",
["复杂任务需角色分工。"],
["需求 Agent","架构 Agent","Review Agent","Pipeline"],
["每步输出结构化","记录中间结果"],
["ai-lab/multi-agent/"],
"Multi-Agent≈微服务串行调用。",
["三 Agent Demo","Review 报告样例","阶段⑤总结"],
["LangGraph Multi-Agent"],
["输入需求输出 Review"],
"预习毕业项目 PRD。"),
(21,"毕业项目：需求分析 + PRD","第六阶段：毕业项目","graduation-project","完成运营知识助手 PRD 与 API 契约。",
["先写清边界再开发。"],
["用户故事","验收标准","API 契约","架构图","Agent 边界"],
["公开文档脱敏","需求可验收"],
["graduation-project/docs/PRD.md","graduation-project/docs/API_CONTRACT.md"],
"PRD≈RFC+OpenAPI+Agent 能力说明。",
["PRD v1.0","API 契约","架构图"],
["PRD 模板","Skill 格式参考"],
["PRD 含接口清单","明确 Agent 边界"],
"预习全栈实现。"),
(22,"毕业项目：全栈实现","第六阶段：毕业项目","graduation-project","完成 Vue3+PHP+MCP+RAG MVP。",
["把学习成果合成可演示产品。"],
["Vue3 UI","PHP API","MCP","RAG","联调"],
["统一响应格式","Tool 失败 fallback"],
["graduation-project/frontend/","graduation-project/api/"],
"全栈≈Vue 调自己的 API，加 MCP/RAG。",
["MVP Demo","联调记录"],
["Vue3 文档","Yii2 REST"],
["核心对话流程可跑通"],
"预习 Agent 平台化。"),
(23,"毕业项目：Agent 平台化","第六阶段：毕业项目","graduation-project + skill-library","整理 Prompt/Skill/Workflow。",
["可复用 Skill 才是 AI Native 工程。"],
["Prompt 库","Skill 文件","Workflow","集成测试"],
["Skill 可执行","Prompt 不泄露内部信息"],
["graduation-project/prompts/","graduation-project/skills/"],
"Skill≈npm 包+README 给 Agent 读。",
["3 Prompt","3 Skill","Workflow 图"],
["skill-library SKILL.md 格式"],
["3 Skill 完整","能按意图路由"],
"预习 Docker 部署。"),
(24,"毕业项目：部署 + 复盘","第六阶段：毕业项目","graduation-project","Docker 部署、文档、Demo、24周复盘。",
["交付=代码+环境+文档+演示。"],
["Docker Compose","README","Demo","能力自评","进阶规划"],
["密钥不入库","步骤可复现"],
["graduation-project/docker-compose.yml","graduation-project/README.md"],
"Docker≈lockfile+可复制环境。",
["docker-compose","部署文档","Demo","24周复盘"],
["Docker Compose 文档","GitHub Actions"],
["他人可按 README 启动","完成最终自评"],
"规划 30 周进阶：性能、安全、架构、AI 工程化。"),
]:
    n, title, phase, repo, goal, why, skills, quality, paths, compare, deliverables, resources, acceptance, next_preview = spec
    days = DETAILED[n]["days"]
    WEEKS.append({
        "n": n, "title": title, "phase": phase, "repo": repo, "goal": goal,
        "why": why if isinstance(why, list) else [why], "skills": skills, "quality": quality, "paths": paths,
        "days": days, "compare": compare, "deliverables": deliverables,
        "resources": resources, "acceptance": acceptance, "next": next_preview,
    })

# Generate files
validate_weeks(WEEKS)

index_lines = [
    "# 24 周分周学习目录",
    "",
    "每个目录包含：",
    "- `README.md`：本周总览与完整 7 天安排",
    "- `day01.md` ~ `day07.md`：每日独立学习任务",
    "",
    "**建议使用方式**：",
    "1. 每周日阅读下一周 README",
    "2. 周一至周五按 Day 1-5 执行对应 `dayXX.md`",
    "3. 周六完成项目实战",
    "4. 周日复盘并预习",
    "",
    "| 周次 | 主题 | 目录 |",
    "|------|------|------|",
]
for w in WEEKS:
    w = dict(w)
    n = w["n"]
    week_dir = BASE / f"week{n:02d}"
    week_dir.mkdir(exist_ok=True)
    (week_dir / "README.md").write_text(render(w), encoding="utf-8")
    for i, d in enumerate(w["days"], start=1):
        (week_dir / f"day{i:02d}.md").write_text(render_day(w, i, d), encoding="utf-8")
    index_lines.append(f"| Week {n:02d} | {w['title']} | [week{n:02d}](./week{n:02d}/README.md) |")

(BASE / "24周分周学习目录.md").write_text("\n".join(index_lines) + "\n", encoding="utf-8")
print(f"Generated {len(WEEKS)} weeks and {len(WEEKS) * 7} day files")
