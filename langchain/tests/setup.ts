import { vi } from "vitest";

// 终端即使已有密钥/历史追踪开关，默认测试仍禁止上传数据。
for (const key of ["LANGSMITH_TRACING", "LANGSMITH_TRACING_V2", "LANGCHAIN_TRACING", "LANGCHAIN_TRACING_V2"]) {
  process.env[key] = "false";
}
vi.stubGlobal("fetch", vi.fn(async () => {
  throw new Error("离线测试禁止 HTTP fetch；真实服务验证必须单独显式运行。");
}));
