# Week 22 Day 01：Vue3 对话 UI

> 所属周：Week 22：毕业项目：全栈实现  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

用 Vue3 搭出一个能对话的界面：消息列表、输入框、发送按钮，并能在 AI 回答下方展示"引用来源"（来自后端 RAG 的政策原文片段）。

今天你要真正掌握这一句话：

> Vue3 的对话 UI 本质上是"一个消息数组 + 一个渲染循环 + 一个发送函数"；用户输入追加一条 `user` 消息，后端返回后再追加一条 `assistant` 消息，界面通过响应式自动刷新；这和你用 React 的 `useState` 存 `messages` 再 `map` 出来是同一个套路。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚"对话 UI"到底由哪几块组成
2. 理解 Vue3 的 `ref`/`reactive` 响应式（对标 React `useState`）
3. 理解 `v-for` 渲染消息列表（对标 React `map`）
4. 理解 `v-model` 双向绑定输入框（对标 React 受控组件）
5. 定义一条消息的数据结构（role/content/sources）
6. 写一个最小可运行的 `ChatView.vue`
7. 加上"加载中"状态和消息气泡样式
8. 预留"引用来源"展示区（后面 Day04 接 RAG 时填数据）
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 一个对话 UI 到底由什么组成

打开任何一个类 ChatGPT 界面，拆开看只有三块：

```text
+--------------------------------------+
|  消息列表区（可滚动）                  |
|   - 用户消息（右侧气泡）               |
|   - AI 消息（左侧气泡）                |
|     └ 引用来源（政策片段）             |
+--------------------------------------+
|  输入区： [ 输入框........ ] [发送]     |
+--------------------------------------+
```

对应到代码就是三个东西：

| UI 区域 | 数据 | 行为 |
|---|---|---|
| 消息列表 | `messages` 数组 | 用 `v-for` 循环渲染 |
| 输入框 | `input` 字符串 | 用 `v-model` 双向绑定 |
| 发送按钮 | `loading` 布尔值 | 点击触发 `sendMessage()` |

小白重点：**别把对话界面想得很复杂**。它就是"一个数组渲染出来的列表"，加"一个输入框"，加"一个函数负责把新消息塞进数组"。

---

### 1.2 Vue3 响应式：`ref` 和 `reactive`

Vue3 用 Composition API，核心是 `ref` 和 `reactive`。

```vue
<script setup>
import { ref, reactive } from "vue";

// ref：适合单个值（字符串、数字、布尔）
const input = ref("");
const loading = ref(false);

// reactive：适合对象或数组
const messages = reactive([]);
</script>
```

小白重点：`ref` 包装的值，在 `<script>` 里访问要用 `.value`，在 `<template>` 里不用：

```vue
<script setup>
import { ref } from "vue";
const count = ref(0);

function add() {
  count.value++; // 脚本里要 .value
}
</script>

<template>
  <!-- 模板里不用 .value -->
  <div>{{ count }}</div>
  <button @click="add">加一</button>
</template>
```

和 React 对比：

| 对比项 | Vue3 | React |
|---|---|---|
| 定义状态 | `const c = ref(0)` | `const [c, setC] = useState(0)` |
| 读取 | `c.value`（脚本）/ `c`（模板） | `c` |
| 修改 | `c.value = 1` | `setC(1)` |
| 触发刷新 | 自动（改 `.value` 就刷新） | 必须调用 `setC` |

Vue 的爽点：改 `.value` 就自动刷新，不用手动 `setState`。

---

### 1.3 用 `v-for` 渲染消息列表

`v-for` 就是 Vue 的循环渲染，对标 React 的 `messages.map(...)`：

```vue
<template>
  <div class="message-list">
    <div
      v-for="(msg, index) in messages"
      :key="index"
      :class="['bubble', msg.role]"
    >
      {{ msg.content }}
    </div>
  </div>
</template>
```

React 写法对比：

```jsx
<div className="message-list">
  {messages.map((msg, index) => (
    <div key={index} className={`bubble ${msg.role}`}>
      {msg.content}
    </div>
  ))}
</div>
```

| 对比项 | Vue3 | React |
|---|---|---|
| 循环 | `v-for="item in list"` | `list.map(item => ...)` |
| key | `:key="index"` | `key={index}` |
| 动态 class | `:class="[...]"` | `className={...}` |
| 插值 | `{{ msg.content }}` | `{msg.content}` |

小白重点：`:key` 一定要写，Vue 用它来判断哪条消息变了，写错会导致渲染乱套。

---

### 1.4 用 `v-model` 绑定输入框

`v-model` 是 Vue 的双向绑定：输入框的值和变量自动同步。

```vue
<script setup>
import { ref } from "vue";
const input = ref("");
</script>

<template>
  <input v-model="input" placeholder="问点什么…" />
  <p>你正在输入：{{ input }}</p>
</template>
```

React 里要手写受控组件：

```jsx
const [input, setInput] = useState("");

<input
  value={input}
  onChange={(e) => setInput(e.target.value)}
/>
```

| 对比项 | Vue3 | React |
|---|---|---|
| 双向绑定 | `v-model="input"` | `value` + `onChange` 两个属性 |
| 代码量 | 一行搞定 | 需要手动写 onChange |

小白重点：`v-model` 是 Vue 的"语法糖"，省掉了 React 那套 `value + onChange`。

---

### 1.5 定义一条消息的数据结构

对话里每条消息，我们统一用这个结构（脱敏示例）：

```js
// 一条消息长这样
const message = {
  role: "user",        // "user" 或 "assistant"
  content: "退货政策是什么？", // 消息文本
  sources: [],         // 引用来源，AI 消息才有
};
```

AI 回答带引用来源时：

```js
const aiMessage = {
  role: "assistant",
  content: "支持 7 天无理由退货，需商品完好。",
  sources: [
    { title: "售后政策 v2", snippet: "自签收起 7 日内…", docId: "POLICY-007" },
    { title: "退换货细则", snippet: "商品需保持完好…", docId: "POLICY-012" },
  ],
};
```

小白重点：`role` 用来决定气泡是左还是右；`sources` 是后端 RAG（Day04）返回的，前端今天先留空数组占位。

---

### 1.6 写一个最小可运行的 `ChatView.vue`

把上面几块拼起来，这就是今天的核心产出：

```vue
<!-- src/views/ChatView.vue -->
<script setup>
import { ref, reactive, nextTick } from "vue";

const messages = reactive([]); // 消息列表
const input = ref("");         // 输入框内容
const loading = ref(false);    // 是否等待 AI 回复

const listRef = ref(null);     // 用于自动滚到底部

// 滚动到消息列表底部
async function scrollToBottom() {
  await nextTick(); // 等 DOM 更新完
  if (listRef.value) {
    listRef.value.scrollTop = listRef.value.scrollHeight;
  }
}

async function sendMessage() {
  const text = input.value.trim();
  if (!text || loading.value) return;

  // 1) 先把用户消息塞进列表
  messages.push({ role: "user", content: text, sources: [] });
  input.value = "";
  loading.value = true;
  await scrollToBottom();

  // 2) 先塞一个空的 AI 消息，等会儿往里填内容
  const aiMsg = reactive({ role: "assistant", content: "", sources: [] });
  messages.push(aiMsg);

  try {
    // 3) 调后端（今天先用假数据，Day02 换成真 API）
    await fakeReply(aiMsg, text);
  } catch (e) {
    aiMsg.content = "出错了，请稍后再试。";
  } finally {
    loading.value = false;
    await scrollToBottom();
  }
}

// 今日临时假接口：模拟 AI 回复
function fakeReply(aiMsg, text) {
  return new Promise((resolve) => {
    setTimeout(() => {
      aiMsg.content = `你问的是「${text}」，这是一条模拟回复。`;
      aiMsg.sources = [
        { title: "示例政策", snippet: "这是引用片段…", docId: "DOC-001" },
      ];
      resolve();
    }, 800);
  });
}
</script>

<template>
  <div class="chat">
    <!-- 消息列表 -->
    <div ref="listRef" class="message-list">
      <div
        v-for="(msg, i) in messages"
        :key="i"
        :class="['row', msg.role]"
      >
        <div class="bubble">
          {{ msg.content || "…" }}

          <!-- 引用来源（只有 AI 消息且有来源时才显示） -->
          <div v-if="msg.sources && msg.sources.length" class="sources">
            <div class="sources-title">引用来源：</div>
            <ul>
              <li v-for="(s, j) in msg.sources" :key="j">
                {{ s.title }}：{{ s.snippet }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- 加载态 -->
      <div v-if="loading" class="row assistant">
        <div class="bubble typing">AI 正在思考…</div>
      </div>
    </div>

    <!-- 输入区 -->
    <div class="input-bar">
      <input
        v-model="input"
        placeholder="问点什么…（回车发送）"
        @keyup.enter="sendMessage"
      />
      <button :disabled="loading" @click="sendMessage">发送</button>
    </div>
  </div>
</template>

<style scoped>
.chat {
  display: flex;
  flex-direction: column;
  height: 100vh;
  max-width: 720px;
  margin: 0 auto;
}
.message-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}
.row {
  display: flex;
  margin-bottom: 12px;
}
.row.user {
  justify-content: flex-end;
}
.row.assistant {
  justify-content: flex-start;
}
.bubble {
  max-width: 70%;
  padding: 10px 14px;
  border-radius: 12px;
  line-height: 1.5;
  word-break: break-word;
}
.row.user .bubble {
  background: #4f7cff;
  color: #fff;
}
.row.assistant .bubble {
  background: #f1f2f5;
  color: #222;
}
.typing {
  color: #999;
  font-style: italic;
}
.sources {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #ccc;
  font-size: 12px;
  color: #666;
}
.sources-title {
  font-weight: bold;
  margin-bottom: 4px;
}
.input-bar {
  display: flex;
  gap: 8px;
  padding: 12px;
  border-top: 1px solid #eee;
}
.input-bar input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 8px;
}
.input-bar button {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  background: #4f7cff;
  color: #fff;
  cursor: pointer;
}
.input-bar button:disabled {
  background: #aaa;
  cursor: not-allowed;
}
</style>
```

小白重点：这份代码今天就能跑。`fakeReply` 是临时假接口，Day02 会换成真正调 PHP 后端。**先让界面动起来，再接真数据**，这是前端开发的正确顺序。

---

### 1.7 加载态、空态、错误态

一个像样的对话 UI，要处理三种"非正常"状态：

```text
- 加载态：AI 还没回，显示"正在思考…"
- 空态：一条消息都没有时，显示引导语
- 错误态：接口失败时，显示"出错了，请重试"
```

空态可以这样加：

```vue
<div v-if="messages.length === 0" class="empty">
  你好，我是运营知识助手。可以问我政策、订单、商品相关的问题。
</div>
```

小白重点：新手容易只写"正常流程"，但真实产品里，**加载/空/错三态都要处理**，否则用户会以为界面卡死了。

---

### 1.8 为 SSE 流式渲染打基础（预告 Day02）

真实的 AI 回复是"一个字一个字蹦出来"的（流式），靠 SSE（Server-Sent Events）实现。今天先理解思路：

```text
普通请求：等 AI 全部生成完 → 一次性返回 → 界面才显示（用户要等好几秒）
流式请求：AI 生成一点 → 推一点 → 界面一点点显示（用户马上有反馈）
```

前端接收流式数据的雏形（Day02 会完整实现）：

```js
// 用 fetch 读流（比 EventSource 更灵活）
async function streamReply(aiMsg, text) {
  const res = await fetch("/api/chat/stream", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message: text }),
  });

  const reader = res.body.getReader();
  const decoder = new TextDecoder();

  // 一块一块读，读到就往消息里追加
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    aiMsg.content += decoder.decode(value); // 响应式自动刷新！
  }
}
```

小白重点：注意 `aiMsg.content += ...` 这行——因为 `aiMsg` 是响应式的，每追加一个字，界面就自动重绘一次，这就是"打字机效果"的原理。今天先看懂，明天动手。

---

## 2. 源码阅读

- `graduation-project/frontend/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读前端项目时重点找这些内容：

1. 项目入口 `src/main.js`：怎么挂载 Vue 应用
2. 路由 `src/router/`：对话页对应哪个路由
3. 对话组件（可能叫 `ChatView.vue` / `Chat.vue`）：消息数组怎么定义
4. API 封装（可能在 `src/api/`）：怎么调后端
5. 状态管理（可能用 Pinia）：消息是否存在全局 store

建议在笔记里写出这张表：

| 前端文件 | 作用 | React 类比 |
|---|---|---|
| `main.js` | 挂载应用 | `ReactDOM.createRoot` |
| `router/index.js` | 页面路由 | `react-router` |
| `views/ChatView.vue` | 对话页面 | 对话页组件 |
| `api/chat.js` | 请求封装 | `axios` 封装 |
| `stores/chat.js`（Pinia） | 全局消息状态 | Redux/Zustand store |

---

## 3. 练习任务

### 练习 1：跑起来一个 Vue3 项目

```bash
# 用 Vite 创建（对标 create-react-app）
npm create vite@latest my-chat -- --template vue
cd my-chat
npm install
npm run dev
```

打开浏览器访问终端提示的地址（一般是 `http://localhost:5173`），能看到 Vue 默认页面就算成功。

目标：确认 Node 环境和 Vue 项目能跑。

---

### 练习 2：实现 ChatView 组件

把 1.6 的 `ChatView.vue` 复制到 `src/views/ChatView.vue`，然后在 `src/App.vue` 里引用它：

```vue
<!-- src/App.vue -->
<script setup>
import ChatView from "./views/ChatView.vue";
</script>

<template>
  <ChatView />
</template>
```

目标：界面能显示，输入文字点发送，能看到用户消息和 800ms 后的模拟 AI 回复。

---

### 练习 3：加空态和回车发送

在消息列表顶部加空态提示（见 1.7），并确认 `@keyup.enter="sendMessage"` 生效（回车能发消息）。

目标：理解事件绑定 `@keyup.enter` 的写法。

---

### 练习 4：模拟引用来源展示

修改 `fakeReply`，让它返回 2-3 条 `sources`，确认引用来源区能正确渲染成列表。

```js
aiMsg.sources = [
  { title: "售后政策", snippet: "7 天无理由退货…", docId: "P-01" },
  { title: "运费规则", snippet: "满 99 包邮…", docId: "P-02" },
];
```

目标：确认 `v-for` 嵌套渲染（消息里再循环来源）能工作。

---

### 练习 5：列出对话 UI 状态清单

在笔记里写出对话 UI 需要处理的所有状态：

| 状态 | 触发时机 | 界面表现 |
|---|---|---|
| 空态 | 无任何消息 | 显示引导语 |
| 输入中 | 用户打字 | 输入框实时显示 |
| 加载态 | 已发送，等回复 | "AI 正在思考…" + 禁用按钮 |
| 正常回复 | 收到 AI 回复 | 显示气泡 + 引用来源 |
| 错误态 | 接口失败 | "出错了，请重试" |

目标：养成"先想清楚所有状态再写代码"的习惯。

---

## 4. JS/Node.js 类比

| Vue3 概念 | React / 前端类比 | 说明 |
|---|---|---|
| `ref(0)` | `useState(0)` | 定义响应式状态 |
| `.value` | 直接读变量 | Vue 脚本里要加 `.value` |
| `reactive([])` | `useState([])` | 响应式数组/对象 |
| `v-for` | `.map()` | 列表渲染 |
| `v-model` | `value` + `onChange` | 双向绑定 |
| `v-if` | `{cond && <div/>}` | 条件渲染 |
| `@click` | `onClick` | 事件绑定 |
| `nextTick()` | `useEffect` 后的 DOM 操作 | 等 DOM 更新完再操作 |
| `<script setup>` | 函数组件 body | 组件逻辑写这里 |
| Vite | create-react-app / Next 的 dev server | 开发构建工具 |

一句话类比：

> Vue 的对话 UI = React 的对话 UI，只是把 `useState` 换成 `ref`、把 `.map()` 换成 `v-for`、把受控 input 换成 `v-model`，思路完全一样。

---

## 5. AI Review 提问

完成练习后，把你的 `ChatView.vue` 贴给 AI，然后问：

```text
我正在学习 Week 22 Day 01：用 Vue3 实现对话 UI（消息列表+输入+引用来源）。

请你按资深前端 + 全栈工程师标准帮我检查：

1. 我的消息数据结构（role/content/sources）设计合理吗？
2. 响应式用法（ref/reactive）有没有踩坑？
3. 加载态、空态、错误态处理是否完整？
4. 自动滚动到底部的实现有没有问题？
5. 为后面接 SSE 流式渲染，我现在的结构需要调整吗？

请用中文输出：
- 我做对的地方
- 我的问题清单
- 修改建议
- 下一步练习
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 一个能跑的 Vue3 项目
- [✅] `ChatView.vue`：消息列表 + 输入框 + 发送
- [✅] 用户/AI 气泡左右分布样式
- [✅] 引用来源展示区（先用假数据）
- [✅] 加载态、空态处理
- [✅] 对话 UI 状态清单笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出对话 UI 的三大组成（消息列表/输入/发送）
- [ ] 能用 `ref`/`reactive` 定义响应式状态
- [ ] 能用 `v-for` 渲染消息列表
- [ ] 能用 `v-model` 绑定输入框
- [ ] 界面能显示用户消息和 AI 模拟回复
- [ ] 能展示引用来源列表
- [ ] 能处理加载态和空态
- [ ] 能说清 Vue 和 React 在状态/循环/绑定上的类比

---

## 8. 今日自测题

### 8.1 Vue3 里 `ref` 和 `reactive` 有什么区别？

参考答案：

> ✅ `ref` 适合包装单个值（字符串/数字/布尔），脚本里访问要加 `.value`；`reactive` 适合对象或数组，直接访问属性即可。对话里 `input`、`loading` 用 `ref`，`messages` 数组用 `reactive`。

---

### 8.2 `v-for` 为什么一定要写 `:key`？

参考答案：

> ✅ `:key` 帮 Vue 识别每条消息的身份，从而在数据变化时高效、正确地复用/更新 DOM。不写会导致渲染错乱或性能问题，这点和 React 的 `key` 一样。

---

### 8.3 `v-model` 相当于 React 的什么？

参考答案：

> ✅ 相当于 React 受控组件里的 `value` + `onChange` 组合。Vue 用 `v-model` 一行搞定双向绑定，React 需要手写两个属性。

---

### 8.4 为什么把空的 AI 消息先 push 进数组，再往里填内容？

参考答案：

> ✅ 因为要做流式渲染。先占一个位置，AI 每返回一段就往 `aiMsg.content` 追加，响应式会自动刷新界面，形成"打字机效果"。如果等全部返回再 push，用户会干等好几秒没反馈。

---

### 8.5 引用来源（sources）从哪来？前端要做什么？

参考答案：

> ✅ 来源来自后端 RAG（Day04），是政策原文的片段。前端只负责"拿到 sources 数组就渲染成列表"，今天先用假数据占位，结构对齐即可。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 22 Day 01：Vue3 对话 UI 的学习。
请你扮演资深前端 + 全栈工程师，帮我检查：
1. 今日理解是否正确（响应式、列表渲染、双向绑定）
2. Vue/React 类比是否准确
3. 对话 UI 的状态处理是否遗漏关键情况
4. 真实企业项目中还需要注意什么（无障碍、性能、流式）

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 22 README](./README.md)
