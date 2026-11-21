import { afterEach, describe, expect, it, vi } from "vitest";
import { configureRuntime, requiredEnv, runExample, serviceUrl } from "../src/runtime.js";
import { messageText } from "../src/models.js";

// 仅覆盖环境开关，不做任何真实网络调用。
afterEach(() => vi.unstubAllEnvs());
describe("运行边界", () => {
  it("即使终端有密钥和旧追踪开关，也默认保持离线", () => {
    vi.stubEnv("OPENAI_API_KEY", "fixture-not-a-real-key");
    const tracingKeys = ["LANGSMITH_TRACING", "LANGSMITH_TRACING_V2", "LANGCHAIN_TRACING", "LANGCHAIN_TRACING_V2"];
    for (const key of tracingKeys) vi.stubEnv(key, "true");
    expect(configureRuntime([])).toEqual({ live: false, trace: false, remote: false });
    for (const key of tracingKeys) expect(process.env[key]).toBe("false");
  });
  it("不允许仅凭 --trace 打开上传", () => {
    expect(() => configureRuntime(["--trace"])).toThrow("--live");
  });
  it("拒绝拼写错误的标志，避免默默改变行为", () => {
    expect(() => configureRuntime(["--lve"])).toThrow("未知参数");
  });
  it("缺少必需配置时给出变量名称而非回显秘密", () => {
    vi.stubEnv("TUTORIAL_EMPTY_TEST_VALUE", "");
    expect(() => requiredEnv("TUTORIAL_EMPTY_TEST_VALUE")).toThrow("TUTORIAL_EMPTY_TEST_VALUE");
  });
  it("第三方抛出普通 Error 也不回显原始内容", async () => {
    const previousCode = process.exitCode;
    const argv = process.argv;
    const stderr = vi.spyOn(console, "error").mockImplementation(() => {});
    const stdout = vi.spyOn(console, "log").mockImplementation(() => {});
    process.argv = ["node", "fixture-example"];
    try {
      await runExample(async () => { throw new Error("fixture-confidential-request-body"); });
      expect(stderr).toHaveBeenCalled();
      expect(JSON.stringify(stderr.mock.calls)).not.toContain("fixture-confidential-request-body");
      expect(process.exitCode).toBe(1);
    } finally {
      process.argv = argv;
      process.exitCode = previousCode;
      stderr.mockRestore();
      stdout.mockRestore();
    }
  });
  it("URL 只允许 HTTP(S) 且不能夹带用户名密码", () => {
    vi.stubEnv("TUTORIAL_TEST_URL", "https://example.invalid/v1");
    expect(serviceUrl("TUTORIAL_TEST_URL")).toBe("https://example.invalid/v1");
    vi.stubEnv("TUTORIAL_TEST_URL", "file:///tmp/demo");
    expect(() => serviceUrl("TUTORIAL_TEST_URL")).toThrow("HTTP(S)");
    vi.stubEnv("TUTORIAL_TEST_URL", "https://demo:fixture@example.invalid");
    expect(() => serviceUrl("TUTORIAL_TEST_URL")).toThrow("用户名/密码");
  });
});

describe("消息文本提取", () => {
  it("同时接受字符串和文本块，但不把其他块强转为文本", () => {
    expect(messageText("文本")).toBe("文本");
    expect(messageText([{ type: "text", text: "A" }, { type: "image_url", image_url: "fixture" }, { type: "text", text: "B" }])).toBe("AB");
    expect(messageText(null)).toBe("");
  });
});
