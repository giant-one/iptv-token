# 开放平台接口文档

面向第三方接入方的开放接口。每个接入方拥有独立的 `app_id` / `app_secret`，请求需携带签名并防重放。

## 一、凭证获取

在管理后台「开放平台」页面创建接入方，系统生成：
- `app_id`：应用标识（形如 `app_xxxxxxxxxxxxxxxx`）
- `app_secret`：签名密钥（64 位 hex，**仅创建/重置时显示一次**，请妥善保存）

接入方可被停用，停用后无法调用接口。可为接入方配置「播放列表白名单」，限制其只能为指定播放列表创建 Token。

## 二、通用约定

- 请求方式：`POST`
- 请求体：`application/json`（也兼容 form 表单）
- 响应格式：JSON
  ```json
  { "code": 0, "message": "ok", "data": { ... } }
  ```
  `code = 0` 表示成功，非 0 时对应 HTTP 状态码，`message` 为错误描述。

### 公共参数（所有请求必带）

| 参数 | 类型 | 说明 |
| --- | --- | --- |
| `app_id` | string | 接入方标识 |
| `timestamp` | int | 请求时的 Unix 秒级时间戳，服务端校验 ±5 分钟 |
| `nonce` | string | 随机字符串，5 分钟内不可重复（防重放） |
| `sign` | string | 签名，算法见下 |

## 三、签名算法

签名采用 **SHA256 拼接密钥**：

1. 取请求体中**除 `sign` 外**的所有参数。
2. 若参数值为数组（如 `playlist_ids`），先转成紧凑 JSON 字符串：`[1,2]`（无空格，`json_encode` 默认输出）。
3. 按参数名（key）**字典序升序**排序。
4. 用 `http_build_query` 拼成 `k1=v1&k2=v2...` 字符串（URL 编码）。
5. 末尾直接拼接 `app_secret`。
6. 对整串做 `sha256`，得到 `sign`（小写 hex）。

服务端用相同算法重算并用 `hash_equals` 比对。

### 签名示例（PHP）

```php
$app_secret = 'your_app_secret';
$params = [
    'app_id'       => 'app_1234567890abcdef',
    'timestamp'    => 1753142400,
    'nonce'        => 'a1b2c3d4e5',
    'expire_days'  => 30,
    'playlist_ids' => [1, 2],
    'max_ip_per_day' => 5,
];

// 数组值转紧凑 JSON
foreach ($params as $k => $v) {
    if (is_array($v)) {
        $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
ksort($params);
$sign = hash('sha256', http_build_query($params) . $app_secret);

$params['sign'] = $sign;
// 用 $params 作为 JSON body 发起 POST
```

> 注意：参与签名的数组序列化方式与请求体一致（紧凑 JSON）。若你的语言 `json` 序列化带空格，请去除空格保持 `[1,2]` 形式。

## 四、接口

### 创建 Token

```
POST /open/create_token.php
```

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `playlist_ids` | int[] | 是 | 授权的播放列表 ID 数组，非空 |
| `expire_days` | int | 否 | 从现在起 N 天后过期 |
| `expire_at` | int | 否 | 直接指定过期时间戳 |
| `expire_date` | string | 否 | 过期日期，如 `2026-12-31` 或 `2026-12-31 23:59:59` |
| `max_usage` | int | 否 | 最大使用次数，0=不限制（默认） |
| `max_ip_per_day` | int | 否 | 每日最大 IP 数，不传默认 **4**，传 0 表示不限制 |
| `note` | string | 否 | 备注 |
| `channel` | string | 否 | 频道标识，默认 `open` |
| `token` | string | 否 | 自定义 token 值，不传则自动生成 |

> 有效期三选一，优先级 `expire_days` > `expire_at` > `expire_date`；都不传表示永不过期。
> 若接入方配置了播放列表白名单，`playlist_ids` 必须全部在白名单内。

请求示例：

```json
{
  "app_id": "app_1234567890abcdef",
  "timestamp": 1753142400,
  "nonce": "a1b2c3d4e5",
  "expire_days": 30,
  "playlist_ids": [1, 2],
  "max_ip_per_day": 5,
  "note": "某某客户",
  "sign": "..."
}
```

成功响应：

```json
{
  "code": 0,
  "message": "Token 创建成功",
  "data": {
    "id": 123,
    "token": "abc...",
    "expire_at": 1755734400,
    "expire_date": "2026-08-21 12:00:00",
    "playlist_ids": [1, 2],
    "channel": "open",
    "subscribe_url": "http://your-domain/live.php?token=abc...&c=open"
  }
}
```

## 五、错误码

| HTTP | 场景 |
| --- | --- |
| 400 | 参数缺失/非法、签名校验失败、timestamp 超出允许范围 |
| 401 | 缺少 app_id |
| 403 | app_id 无效、接入方已停用、播放列表不在授权范围 |
| 405 | 非 POST 请求 |
| 409 | nonce 重复（重放）、自定义 token 已存在 |
| 500 | 服务器内部错误 |

## 六、curl 测试示例

```bash
# 先用上面的 PHP 片段算出 sign，再拼 body
curl -X POST "http://your-domain/open/create_token.php" \
  -H "Content-Type: application/json" \
  -d '{
    "app_id": "app_1234567890abcdef",
    "timestamp": 1753142400,
    "nonce": "a1b2c3d4e5",
    "expire_days": 30,
    "playlist_ids": [1, 2],
    "max_ip_per_day": 5,
    "sign": "算出的签名"
  }'
```
