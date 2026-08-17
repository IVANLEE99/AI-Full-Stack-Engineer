# Day 03 源码阅读笔记：OrderConfirmForm（参数校验）

> 对应课程：`week06/day03.md` OrderConfirmForm 参数校验  
> 源码映射：`week06/OriginCodes/OrderConfirmForm.php`  
> 公开路径：`order-api/forms/OrderConfirmForm.php`  
> 语法补充：[yii2-rules说明.md](./yii2-rules说明.md)  
> 目标：对照结账字段，分清 Form 基础校验和 Service 业务校验。

---

## 1. 一句话结论

`OrderConfirmForm` **不用 `scenarios()`**，而是用多个 `*Validate($params)` 往 `$ruleArray` 塞不同规则，再 `setAttributes` + `validate()`。  
它只做 **参数形状**（必填、枚举、整数、长度），不做库存、金额、地址归属。

课程示例里的 `goods_id` / `sku_id` / `num` **本文件没有**，商品在 `goods_list` 里。

---

## 2. 练习 1：结构记录

```text
Form 类名：AppOrderApi\forms\OrderConfirmForm
继承：common\BaseForm
属性数量：显式 public 约 10 个
rules：rules() = commonRules + 动态 $ruleArray
scenarios：无；用 5 个 *Validate 方法代替
最重要的 5 个字段：eid、goods_list、source、token、address_id
```

骨架：

```php
public function rules()
{
    return array_merge($this->commonRules, $this->ruleArray);
}

public function orderConfirmValidate($params = [])
{
    $this->ruleArray = [ /* 本场景规则 */ ];
    $this->setAttributes($params);
    return $this->validate();
}
```

`OrderController::actionGoodsListCheck` 调用的是 `orderGoodsListCheckValidate()`。  
`commonRules` 来自 `BaseForm`，本地 OriginCodes 暂无父类，规则细节待确认。

---

## 3. 五个校验场景

| 方法 | 用途 | 必填 | 差异 |
|---|---|---|---|
| `orderGoodsListCheckValidate` | 登录用户商品校验 | `eid`, `goods_list` | 只有必填 |
| `touristOrderGoodsListCheckValidate` | 游客商品校验 | 同上 | 与上几乎相同 |
| `orderConfirmValidate` | 旧确认页 | `eid`, `token`, `source` | 要 token；`commit` 最长 400；有 `location_type` 1–5 |
| `tradeConfirmValidate` | 新确认/收银台 | `eid`, `source` | **不要 token**；有 `payment_method`、`order_no`；`commit` 最长 100 |
| `touristTradeConfirmValidate` | 游客确认 | 与 tradeConfirm 相同 | 游客也不强制 token |

`actionTradeConfirm` 在 Controller 已 `forbidden`，Form 方法仍保留。

---

## 4. 阅读记录总表

| Form 属性 | 前端字段 | 必填 | 类型 | rules | scenario（方法） | 错误提示 |
|---|---|---|---|---|---|---|
| `$eid` | `eid` | 是（所有场景） | — | `required` | 全部 | 默认「不能为空」（具体看 BaseForm/i18n） |
| `$goods_list` | `goods_list` | 是 | — | `required` | 两个 GoodsListCheck | **不校验是否数组、内部 sku/num** |
| `$token` | `token` | 仅旧确认页 | — | `required` | `orderConfirmValidate` | 游客/trade 不强制 |
| `$source` | `source` | 确认类场景 | 枚举 | `required` + `in [1,2,3,4]` | confirm / trade | 注释写 1–3，规则允许 **4** |
| `$address_id` | `address_id` | **否** | integer | `'integer'` | confirm / trade | 空能过；有值须整数 |
| `$coupon_user_id` | `coupon_user_id` | 否 | integer | `'integer'` | confirm / trade | 不校验券归属 |
| `$commit` | 备注（不是 remark） | 否 | string | confirm: 0–400；trade: 0–100 | confirm / trade | 超长失败 |
| `$location_type` | 配送/自提 | 否 | integer 1–5 | `min=>1, max=>5` | 仅 `orderConfirmValidate` | trade 不校验 |
| `$payment_method` | 支付方式 | 否 | string max 50 | `string max 50` | 两个 tradeConfirm | 不校验是否真实渠道 |
| `$order_no` | 订单号 | 否 | string max 32 | `string max 32` | 两个 tradeConfirm | 不校验订单是否存在 |
| `pf` | pc/m/android/ios | 否 | 枚举 | `'in' range` | confirm / trade | **类上未声明 public $pf** |
| `goods_id` / `sku_id` / `num` | 课程示例 | — | — | **未找到** | — | 应在 `goods_list` 或 Service |

---

## 5. 练习 2：前端字段 vs Form（10+）

| 前端可能字段 | Form | 结论 |
|---|---|---|
| `eid` | `$eid` required | 有 |
| `goods_list` | 仅 check 场景 required | 有；**不拆 sku/num** |
| `token` | 仅旧确认 required | 有 |
| `source` | required + in 1–4 | 有 |
| `address_id` | 可选 integer | 有；**不校验归属** |
| `coupon_user_id` | 可选 integer | 有；不是 `coupon_id` |
| 备注 | `$commit` 长度限制 | 有；不是 `remark` |
| `location_type` | 仅旧确认 1–5 | 有 |
| `payment_method` | trade max 50 | 有 |
| `order_no` | trade max 32 | 有 |
| `pf` | in 枚举 | 有规则，缺属性声明 |
| `num` / `amount` | 无 | 数量在列表或 Service；**金额绝不能信前端** |

---

## 6. 练习 3：必填与失败展示

| 字段 | 是否必填 | 失败时前端建议文案 |
|---|---|---|
| `eid` | 是 | 系统参数错误（用户通常看不到 eid） |
| `goods_list` | 商品校验场景是 | 请选择商品 / 购物车为空 |
| `token` | 仅 `orderConfirmValidate` | 请先登录 |
| `source` | 确认场景是 | 下单来源无效 |
| `pf` | 否，非法值失败 | 请使用官方客户端 |
| `address_id` | 否 | 仅非整数时提示地址无效；**空地址 Form 拦不住** |
| `coupon_user_id` | 否 | 优惠券参数错误 |
| `commit` | 否 | 备注过长 |
| `location_type` | 否 | 配送方式无效 |
| `payment_method` / `order_no` | 否 | 支付方式/订单号格式错误 |

源码 **没有自定义 `message`**。Controller 失败时：`endValidate(800000, getSimpleFirstError())`，并打企业微信。

---

## 7. Form vs Service 边界

| 风险 | Form 做了吗 | 应在哪里 |
|---|---|---|
| `num=-1` | **没校验 num** | `goods_list` 结构 + Service/Node |
| 前端传 `amount` | 无此字段 | 金额以后端计算为准 |
| 地址越权 | 只 `integer` | Service 校验地址属于当前用户 |
| 优惠券越权 | 只 `integer` | CouponUseNode / 营销服务 |
| 商品下架/库存 | `goods_list` 只 required | `goodsListCheck` Node 链 |
| `source=4` | 允许 | 对照业务是否游客/其它来源 |

---

## 8. 和课程模板的差异

| 课程 | 本文件 |
|---|---|
| 固定 `rules()` | `$ruleArray` 按方法切换 |
| `scenarios()` | 五个 `*Validate` |
| `goods_id/sku_id/num` | `goods_list` |
| `remark` | `commit` |
| `coupon_id` | `coupon_user_id` |

Zod 类比（`tradeConfirmValidate`）：

```ts
z.object({
  eid: z.any(),
  source: z.union([z.literal(1), z.literal(2), z.literal(3), z.literal(4)]),
  pf: z.enum(['pc', 'm', 'android', 'ios']).optional(),
  address_id: z.number().int().optional(),
  coupon_user_id: z.number().int().optional(),
  commit: z.string().max(100).optional(),
  payment_method: z.string().max(50).optional(),
  order_no: z.string().max(32).optional(),
});
```

| Yii2 | Node.js |
|---|---|
| Form Model | Joi/Zod schema |
| 动态 `$ruleArray` | 不同接口不同 schema |
| `validate()` | `schema.parse()` |
| `getSimpleFirstError()` | validation error message |

---

## 9. 自测题（附简答）

1. **Form 的职责？**  
   后端第一道防线：必填、类型、枚举、长度。不替代 Service。

2. **有没有 `scenarios()`？**  
   没有。用 `*Validate` 方法切换 `$ruleArray`。

3. **`address_id` 为空能过吗？**  
   能。它不是 required，空地址要靠业务层拦。

4. **数量负数谁拦？**  
   本 Form 不拦 `num`。应在 `goods_list` 结构或 `goodsListCheck`。

5. **校验失败怎么回前端？**  
   `validate()` 失败 → `getSimpleFirstError()` → `endValidate(800000, …)`。

---

## 10. 20 分钟复盘练习

- [ ] 口述 5 个 `*Validate` 各自给哪个接口用
- [ ] 指出 3 个「Form 过了、Service 还必须查」的字段
- [ ] 对照课程 `remark` / `coupon_id` 在源码里的真名
- [ ] 说明为什么空 `address_id` 不是 Form 的锅

---

## 11. 最终复盘句

`OrderConfirmForm` 是结账参数的 **形状检查器**：场景用方法切换，规则只保证「传得像那么回事」。  
能不能下单，仍然看 Service 的库存、金额、地址和券。
