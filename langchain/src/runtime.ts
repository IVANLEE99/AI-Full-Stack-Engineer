import { config as loadEnv } from "dotenv";

export class TutorialConfigError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "TutorialConfigError";
  }
}

export interface ExecutionOptions {
  live: boolean;
  trace: boolean;
  remote: boolean;
}

/** 不因为终端已有密钥就切换到真实服务；旧追踪开关也必须一起关闭。 */
export function configureRuntime(args = process.argv.slice(2)): ExecutionOptions {
  const known = new Set(["--live", "--trace", "--remote"]);
  const unknown = args.find((arg) => !known.has(arg));
  if (unknown) throw new TutorialConfigError("未知参数：只接受 --live、--trace、--remote，不回显参数内容。");
  const options = {
    live: args.includes("--live"),
    trace: args.includes("--trace"),
    remote: args.includes("--remote"),
  };
  if (options.trace && !options.live) {
    throw new TutorialConfigError("--trace 会上传运行数据，必须同时显式指定 --live。");
  }
  // 本地练习根本不加载 .env；真实联调才读取读者自行准备的配置。
  if (options.live || options.remote) loadEnv({ quiet: true });
  for (const key of ["LANGSMITH_TRACING", "LANGSMITH_TRACING_V2", "LANGCHAIN_TRACING", "LANGCHAIN_TRACING_V2"]) {
    process.env[key] = options.trace ? "true" : "false";
  }
  if (options.trace) requiredEnv("LANGSMITH_API_KEY");
  return options;
}

export function requiredEnv(name: string): string {
  const value = process.env[name]?.trim();
  if (!value) throw new TutorialConfigError(`缺少 ${name}；请按 env.example 配置，或去掉 --live/--remote。`);
  return value;
}

/** URL 配置可能携带秘密，不把完整地址或凭证放进错误消息。 */
export function serviceUrl(name: string): string | undefined {
  const value = process.env[name]?.trim();
  if (!value) return undefined;
  let parsed: URL;
  try {
    parsed = new URL(value);
  } catch {
    throw new TutorialConfigError(`${name} 必须是有效的 HTTP(S) URL。`);
  }
  if (!["https:", "http:"].includes(parsed.protocol) || parsed.username || parsed.password) {
    throw new TutorialConfigError(`${name} 只接受不含用户名/密码的 HTTP(S) URL。`);
  }
  return value;
}

export async function runExample(main: (options: ExecutionOptions) => Promise<void>): Promise<void> {
  let trace = false;
  try {
    const options = configureRuntime();
    trace = options.trace;
    console.log(options.live ? "[live] 真实模型调用可能产生费用。" : "[offline] 固定模型替身，不调用模型 API。" );
    if (trace) console.log("[trace] 显式启用 LangSmith：只使用匿名练习输入。" );
    await main(options);
  } catch (error) {
    // SDK 错误可能包含请求体/供应商信息；默认只打印分类，不输出原始对象。
    const name = error instanceof Error && /^[A-Za-z][A-Za-z0-9_-]{0,80}$/.test(error.name) ? error.name : "UnknownError";
    const safeMessage = error instanceof TutorialConfigError
      ? error.message
      : "执行失败；请对照课程排错表检查包版本、模型能力、参数和网络。不要公开原始请求或密钥。";
    console.error(`[${name}] ${safeMessage}`);
    process.exitCode = 1;
  } finally {
    if (trace) {
      try {
        const { awaitAllCallbacks } = await import("@langchain/core/callbacks/promises");
        await awaitAllCallbacks();
      } catch {
        console.error("[TraceFlushError] 追踪回调收尾失败；未打印原始错误或请求内容。");
        process.exitCode = 1;
      }
    }
  }
}
