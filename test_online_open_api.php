<?php
/**
 * 开放平台接口 —— 线上健康检查脚本（黑盒 / 远程）
 *
 * 与 test_open_api.php 的区别：
 *   - test_open_api.php  是本机白盒测试：直连数据库造数据 + 起内置服务器，覆盖 8 个用例。
 *   - 本脚本            是远程黑盒测试：不碰数据库，只用真实 app_id/app_secret 打线上 HTTP 接口，
 *                        用于在自己电脑上确认线上服务是否正常。
 *
 * 用法：
 *   1) 直接改下面 CONFIG 区的常量，然后：
 *        php test_online_open_api.php
 *   2) 或用环境变量覆盖（适合放进 CI / 定时任务，不把密钥写进文件）：
 *        OPEN_BASE_URL="https://your-domain.com" \
 *        OPEN_APP_ID="app_xxx" \
 *        OPEN_APP_SECRET="secret_xxx" \
 *        OPEN_PLAYLIST_ID=1 \
 *        php test_online_open_api.php
 *
 * 前置条件：
 *   - PHP 启用 curl 扩展
 *   - 一个线上「启用」状态的接入方 app_id / app_secret
 *   - 一个该接入方有权访问的 playlist_id（若接入方配了白名单，必须在白名单内）
 *
 * ⚠️ 注意：open/ 只有创建接口、没有删除接口。每成功跑一次「合法创建」用例，
 *          都会在线上真实生成一个 token。本脚本会把这些 token 的 note/channel
 *          标记为 __healthcheck__，方便你之后在后台按标记批量清理。
 */

// ======================= CONFIG（改这里，或用环境变量覆盖） =======================
$CONFIG = [
    // 线上服务根地址，不要带结尾斜杠。例：https://tv.example.com
    'base_url'    => getenv('OPEN_BASE_URL')     ?: 'http://www.xxl2a.xyz/',
    // 接入方凭据（后台「开放平台接入方管理」里获取）
    'app_id'      => getenv('OPEN_APP_ID')       ?: 'app_feece3fcd45f978e',
    'app_secret'  => getenv('OPEN_APP_SECRET')   ?: 'f282c2603dad58afbb7cb7a56a5f42d4d27f061719d5026795ce56e6b0c33f04',
    // 一个该接入方有权访问的播放列表 ID
    'playlist_id' => (int)(getenv('OPEN_PLAYLIST_ID') ?: 1),
    // 请求超时（秒）
    'timeout'     => (int)(getenv('OPEN_TIMEOUT') ?: 15),
];
// ================================================================================

// ---------------- 环境自检 ----------------
if (!function_exists('curl_init')) {
    fwrite(STDERR, "[FATAL] 需要 curl 扩展，请在 php.ini 启用。\n");
    exit(2);
}
if (strpos($CONFIG['base_url'], 'your-domain.com') !== false
    || strpos($CONFIG['app_id'], 'xxxx') !== false) {
    fwrite(STDERR, "[FATAL] 请先在 CONFIG 区填入真实的 base_url / app_id / app_secret，"
        . "或用环境变量传入。\n");
    exit(2);
}

$endpoint = rtrim($CONFIG['base_url'], '/') . '/open/create_token.php';
echo "========================================\n";
echo " 线上开放平台接口健康检查\n";
echo " 目标: {$endpoint}\n";
echo " app_id: {$CONFIG['app_id']}\n";
echo " playlist_id: {$CONFIG['playlist_id']}\n";
echo "========================================\n\n";

// ---------------- 签名（与 open/open_common.php::open_calc_sign 完全一致）----------------
function calc_sign(array $params, string $secret): string {
    unset($params['sign']);
    ksort($params);
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return hash('sha256', http_build_query($params) . $secret);
}

// ---------------- HTTP 客户端 ----------------
function http_post_json(string $url, array $body, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HEADER         => false,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err !== '') {
        return ['http' => 0, 'err' => $err, 'body' => null, 'raw' => ''];
    }
    return ['http' => $code, 'body' => json_decode($resp, true), 'raw' => (string)$resp];
}

// ---------------- 构造合法请求载荷 ----------------
function make_payload(array $cfg, array $override = []): array {
    $p = array_merge([
        'app_id'         => $cfg['app_id'],
        'timestamp'      => time(),
        'nonce'          => bin2hex(random_bytes(8)),
        'expire_days'    => 1,                  // 健康检查造的 token 只需 1 天有效期
        'playlist_ids'   => [$cfg['playlist_id']],
        'note'           => '__healthcheck__',  // 标记：便于后台批量清理
        'channel'        => '__healthcheck__',
    ], $override);
    $p['sign'] = calc_sign($p, $cfg['app_secret']);
    return $p;
}

// ---------------- 用例框架 ----------------
$results = [];
function check(string $name, bool $cond, string $detail = ''): void {
    global $results;
    $results[] = $cond;
    echo ($cond ? "[PASS] " : "[FAIL] ") . $name
        . ($detail !== '' ? "  → {$detail}" : '') . "\n";
}
// 截断响应体，日志更干净
function brief($raw): string {
    $s = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
    $s = trim(preg_replace('/\s+/', ' ', (string)$s));
    return mb_strlen($s) > 160 ? mb_substr($s, 0, 160) . '…' : $s;
}

$created_tokens = [];

// ---------------- T0 连通性（未签名请求，只验证服务活着 & 返回 JSON）----------------
$r0 = http_post_json($endpoint, ['ping' => 1], $CONFIG['timeout']);
if ($r0['http'] === 0) {
    check('T0 连通性', false, '无法连接：' . ($r0['err'] ?? '未知错误'));
    // 连不上就没必要往下测了
    echo "\n[FATAL] 无法连接线上服务，请检查 base_url / 网络 / TLS 证书。\n";
    exit(1);
}
// 服务活着的标志：返回了合法 JSON（无论 code 多少），而不是 502/nginx 报错页
$looks_json = is_array($r0['body']) && array_key_exists('code', $r0['body']);
check('T0 连通性（服务可达且返回 JSON）', $looks_json,
    'HTTP ' . $r0['http'] . ' ' . brief($r0['raw']));

// ---------------- T1 合法签名创建 Token（期望 200 / code=0）----------------
$payload = make_payload($CONFIG);
$r1 = http_post_json($endpoint, $payload, $CONFIG['timeout']);
$tk1 = $r1['body']['data']['token'] ?? null;
$ok1 = ($r1['http'] === 200) && (($r1['body']['code'] ?? -1) === 0) && !empty($tk1);
check('T1 合法签名创建 Token', $ok1,
    $ok1 ? ('token=' . substr($tk1, 0, 12) . '…  ' . ($r1['body']['data']['subscribe_url'] ?? ''))
         : ('HTTP ' . $r1['http'] . ' ' . brief($r1['raw'])));
if ($tk1) { $created_tokens[] = $tk1; }

// ---------------- T2 重放：完全相同的 payload（同 nonce+签名）→ 期望 409 ----------------
$r2 = http_post_json($endpoint, $payload, $CONFIG['timeout']);
$ok2 = ($r2['http'] === 409) || (($r2['body']['code'] ?? null) === 409);
check('T2 重放请求被拦截（nonce 去重）', $ok2, 'HTTP ' . $r2['http'] . ' ' . brief($r2['raw']));

// ---------------- T3 错误签名 → 期望 400 ----------------
$bad = make_payload($CONFIG);
$bad['sign'] = 'deadbeef';
$r3 = http_post_json($endpoint, $bad, $CONFIG['timeout']);
$ok3 = ($r3['http'] === 400) || (($r3['body']['code'] ?? null) === 400);
check('T3 错误签名被拦截', $ok3, 'HTTP ' . $r3['http'] . ' ' . brief($r3['raw']));

// ---------------- T4 过期时间戳（超出 ±5 分钟窗口）→ 期望 400 ----------------
$exp = make_payload($CONFIG, ['timestamp' => time() - 1000]);
$r4 = http_post_json($endpoint, $exp, $CONFIG['timeout']);
$ok4 = ($r4['http'] === 400) || (($r4['body']['code'] ?? null) === 400);
check('T4 过期时间戳被拦截', $ok4, 'HTTP ' . $r4['http'] . ' ' . brief($r4['raw']));

// ---------------- T5 缺少 app_id → 期望 401 ----------------
$p5 = [
    'timestamp'    => time(),
    'nonce'        => bin2hex(random_bytes(8)),
    'playlist_ids' => [$CONFIG['playlist_id']],
];
$p5['sign'] = calc_sign($p5, $CONFIG['app_secret']);
$r5 = http_post_json($endpoint, $p5, $CONFIG['timeout']);
$ok5 = ($r5['http'] === 401) || (($r5['body']['code'] ?? null) === 401);
check('T5 缺少 app_id 被拦截', $ok5, 'HTTP ' . $r5['http'] . ' ' . brief($r5['raw']));

// ---------------- 结果汇总 ----------------
$pass  = count(array_filter($results));
$total = count($results);
echo "\n========== 结果 {$pass}/{$total} 通过 ==========\n";
echo ($pass === $total ? "线上开放平台接口正常 ✅\n" : "存在异常 ❌  请检查上面 [FAIL] 项\n");

if (!empty($created_tokens)) {
    echo "\n[INFO] 本次健康检查在线上创建了 " . count($created_tokens) . " 个 token（note/channel = __healthcheck__）：\n";
    foreach ($created_tokens as $t) {
        echo "        {$t}\n";
    }
    echo "[INFO] 可在后台 tokens 页搜索 __healthcheck__ 批量删除，或按 note 清理。\n";
}

exit($pass === $total ? 0 : 1);
