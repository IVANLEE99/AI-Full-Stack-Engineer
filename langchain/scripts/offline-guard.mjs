// 为批量离线验证提供额外的 fetch 失败门禁，不是完整网络沙箱。
for (const key of ["LANGSMITH_TRACING", "LANGSMITH_TRACING_V2", "LANGCHAIN_TRACING", "LANGCHAIN_TRACING_V2"]) {
  process.env[key] = "false";
}
globalThis.fetch = async () => {
  throw new Error("本次离线验证禁止 HTTP fetch；真实服务需要单独显式选择。");
};
