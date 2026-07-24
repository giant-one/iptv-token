<?php
/**
 * 开放平台 - 创建 Token
 *
 * POST /open/create_token.php
 * 请求体 (JSON)：
 *   {
 *     "app_id": "app_xxx",
 *     "timestamp": 1753142400,
 *     "nonce": "随机字符串",
 *     "expire_days": 30,            // 或 expire_at / expire_date，可选，不传则永不过期
 *     "playlist_ids": [1, 2],       // 必填
 *     "max_usage": 0,               // 可选
 *     "max_ip_per_day": 5,          // 可选
 *     "note": "备注",               // 可选
 *     "channel": "api",             // 可选
 *     "token": "自定义token",       // 可选，不传则自动生成
 *     "sign": "sha256签名"
 *   }
 *
 * 签名规则见 docs/OPEN_API.md
 */

require_once __DIR__ . '/open_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    open_error('仅支持 POST 请求', 405);
}

$input = open_input();

// 鉴权 + 签名 + 防重放
$app = open_authenticate($input);

// ---- 播放列表校验 ----
$playlist_ids = $input['playlist_ids'] ?? [];
if (!is_array($playlist_ids) || empty($playlist_ids)) {
    open_error('playlist_ids 必须是非空数组');
}
$playlist_ids = array_map('intval', $playlist_ids);

// 若该接入方配置了白名单，则请求的播放列表必须在白名单内
if (!empty($app['default_playlist_ids'])) {
    $allowed = json_decode($app['default_playlist_ids'], true);
    if (is_array($allowed) && !empty($allowed)) {
        $allowed = array_map('intval', $allowed);
        foreach ($playlist_ids as $pid) {
            if (!in_array($pid, $allowed, true)) {
                open_error("播放列表 ID {$pid} 不在该接入方的授权范围内", 403);
            }
        }
    }
}

// 校验播放列表是否存在
foreach ($playlist_ids as $pid) {
    if (!get_playlist_by_id($pid)) {
        open_error("播放列表 ID {$pid} 不存在");
    }
}

// ---- token 值 ----
$token = trim($input['token'] ?? '');
if (empty($token)) {
    $token = generate_unique_token();
} elseif (token_exists($token)) {
    open_error('Token 已存在', 409);
}

// ---- 有效期 ----
$expire_at = open_parse_expire($input);

// ---- 其他参数 ----
$channel   = trim($input['channel'] ?? 'open');
$max_usage = isset($input['max_usage']) ? (int)$input['max_usage'] : 0;
// 未传 max_ip_per_day 时默认限制每日 4 个 IP（传 0 则表示不限制）
$max_ip    = isset($input['max_ip_per_day']) ? (int)$input['max_ip_per_day'] : 4;
$note      = trim($input['note'] ?? '');

$data = [
    'token'          => $token,
    'expire_at'      => $expire_at,
    'max_usage'      => $max_usage,
    'max_ip_per_day' => $max_ip,
    'status'         => 1,
    'note'           => $note,
    'channel'        => $channel,
];

if (!create_token($data)) {
    open_error('创建 Token 失败', 500);
}

// 绑定播放列表
$db = get_db_connection();
$token_id = $db->lastInsertId();
foreach ($playlist_ids as $pid) {
    add_token_playlist($token_id, $pid);
}

$subscribe_url = open_base_url() . '/live.php?token=' . urlencode($token) . '&c=' . urlencode($channel);

open_success([
    'id'            => (int)$token_id,
    'token'         => $token,
    'expire_at'     => $expire_at,
    'expire_date'   => $expire_at > 0 ? date('Y-m-d H:i:s', $expire_at) : null,
    'playlist_ids'  => $playlist_ids,
    'channel'       => $channel,
    'subscribe_url' => $subscribe_url,
], 'Token 创建成功');
