<?php
/**
 * 开放平台接口测试脚本
 *
 * 用法（项目根目录执行）：
 *   php test_open_api.php            运行全部测试
 *   php test_open_api.php --cleanup  运行后清理本次测试造的数据
 *
 * 前置条件：
 *   1. 已有 .env（DB_DRIVER=sqlite 时会自动建表，无需手动迁移）
 *   2. PHP 启用了 curl 扩展（用于发起 HTTP 请求）
 *
 * 脚本会：
 *   - 确保 api_apps / api_nonces / playlists.url 存在（兼容未迁移的库）
 *   - 造一个测试播放列表 + 两个测试接入方（一个启用带白名单，一个停用）
 *   - 启动 PHP 内置服务器，用真实签名发起 HTTP 请求
 *   - 覆盖 7 个场景：合法创建 / 重放 / 错签名 / 过期时间戳 / 停用 app / 白名单越权 / 缺 app_id
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin/db_functions.php';

// ---------------- 环境自检 ----------------
if (!function_exists('curl_init')) {
    echo "[FATAL] 需要 curl 扩展，请在 php.ini 启用。\n";
    exit(1);
}

$db = get_db_connection();

// 确保 api_apps / api_nonces 表存在（兼容未跑迁移的库）
foreach (['api_apps', 'api_nonces'] as $tbl) {
    $exists = (DB_DRIVER === 'sqlite')
        ? $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $db->quote($tbl))->fetch()
        : $db->query("SHOW TABLES LIKE " . $db->quote($tbl))->fetch();
    if (!$exists) {
        echo "[FATAL] 表 {$tbl} 不存在，请先运行: php migrate_api_apps.php\n";
        exit(1);
    }
}

// 兼容旧库：playlists 没有 url 列时补上（init_db.php 的 SQLite 版建的是 name_en）
if (DB_DRIVER === 'sqlite') {
    $cols = $db->query("PRAGMA table_info(playlists)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('url', array_column($cols, 'name'))) {
        $db->exec("ALTER TABLE playlists ADD COLUMN url TEXT");
        echo "[INFO] 已为 playlists 补 url 列\n";
    }
}

// 兼容旧库：tokens 没有 max_ip_per_day 列时补上
// （migrate_max_ip.php 的建列语句带 COMMENT 子句，SQLite 不支持会失败，这里兜底）
$tcols = (DB_DRIVER === 'sqlite')
    ? array_column($db->query("PRAGMA table_info(tokens)")->fetchAll(PDO::FETCH_ASSOC), 'name')
    : array_column($db->query("SHOW COLUMNS FROM tokens")->fetchAll(PDO::FETCH_ASSOC), 'Field');
if (!in_array('max_ip_per_day', $tcols)) {
    $db->exec("ALTER TABLE tokens ADD COLUMN max_ip_per_day INTEGER NOT NULL DEFAULT 0");
    echo "[INFO] 已为 tokens 补 max_ip_per_day 列\n";
}

// ---------------- 造数据 ----------------
$now = time();
$db->prepare("INSERT INTO playlists (name, url, created_at, updated_at) VALUES (?, ?, ?, ?)")
   ->execute(['_test_pl',  'http://example.com/test.m3u', $now, $now]);
$pid = (int) $db->lastInsertId();

// 接入方 A：启用 + 白名单限制为 $pid
$appA = create_api_app([
    'name' => '_test_app_A',
    'status' => 1,
    'default_playlist_ids' => json_encode([$pid]),
]);
// 接入方 B：停用
$appB = create_api_app(['name' => '_test_app_B', 'status' => 0]);
update_api_app(get_app_by_app_id($appB['app_id'])['id'], [
    'name' => '_test_app_B', 'status' => 0, 'default_playlist_ids' => null,
]);

echo "[INFO] 测试播放列表 id={$pid}\n";
echo "[INFO] 接入方A(启用) app_id={$appA['app_id']}\n";
echo "[INFO] 接入方B(停用) app_id={$appB['app_id']}\n";

// ---------------- 签名函数（与 open/open_common.php 保持一致）----------------
function calc_sign($params, $secret) {
    unset($params['sign']);
    ksort($params);
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return hash('sha256', http_build_query($params) . $secret);
}

// ---------------- 启动内置服务器 ----------------
$port = 8799;
$root = __DIR__;
$cmd = PHP_BINARY . " -S 127.0.0.1:{$port} -t {$root}";
$desc = [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', 'php://stderr', 'w']];
$proc = proc_open($cmd, $desc, $pipes);
if (!is_resource($proc)) {
    echo "[FATAL] 无法启动内置服务器\n";
    exit(1);
}
echo "[INFO] 内置服务器已启动: http://127.0.0.1:{$port}\n";

// 等待服务器就绪
$ready = false;
for ($i = 0; $i < 30; $i++) {
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
    if ($fp) { fclose($fp); $ready = true; break; }
    usleep(200000);
}
if (!$ready) {
    echo "[FATAL] 服务器未就绪\n";
    proc_terminate($proc);
    exit(1);
}

// ---------------- HTTP 客户端 ----------------
function http_post_json($url, $body) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['http' => 0, 'err' => $err];
    return ['http' => (int)$code, 'body' => json_decode($resp, true), 'raw' => $resp];
}

// ---------------- 构造合法请求载荷 ----------------
function make_payload($appId, $secret, $pid, $override = []) {
    $p = array_merge([
        'app_id' => $appId,
        'timestamp' => time(),
        'nonce' => bin2hex(random_bytes(8)),
        'expire_days' => 30,
        'playlist_ids' => [$pid],
        'max_ip_per_day' => 5,
        'note' => 'test',
    ], $override);
    $p['sign'] = calc_sign($p, $secret);
    return $p;
}

$url = "http://127.0.0.1:{$port}/open/create_token.php";

// ---------------- 测试用例 ----------------
$results = [];

function check($name, $cond, $detail = '') {
    global $results;
    $results[] = $cond;
    echo ($cond ? '[PASS] ' : '[FAIL] ') . $name . ($detail !== '' ? "  → {$detail}" : '') . "\n";
}

// T1 合法创建
$payload = make_payload($appA['app_id'], $appA['app_secret'], $pid);
$r = http_post_json($url, $payload);
$ok1 = ($r['http'] === 200) && ($r['body']['code'] ?? -1) === 0 && !empty($r['body']['data']['token']);
check('T1 合法签名创建 Token', $ok1, $ok1 ? 'token=' . substr($r['body']['data']['token'], 0, 12) . '...' : ($r['raw'] ?? ''));

// T2 重放（同一 nonce）→ 409
$r2 = http_post_json($url, $payload); // 同 payload = 同 nonce+签名
$ok2 = ($r2['http'] === 409) || (($r2['body']['code'] ?? null) === 409);
check('T2 重放请求被拦截', $ok2, $r2['raw'] ?? '');

// T3 错误签名 → 400
$bad = make_payload($appA['app_id'], $appA['app_secret'], $pid);
$bad['sign'] = 'deadbeef';
$r3 = http_post_json($url, $bad);
$ok3 = ($r3['http'] === 400) || (($r3['body']['code'] ?? null) === 400);
check('T3 错误签名被拦截', $ok3, $r3['raw'] ?? '');

// T4 过期时间戳 → 400
$exp = make_payload($appA['app_id'], $appA['app_secret'], $pid, ['timestamp' => time() - 1000]);
$r4 = http_post_json($url, $exp);
$ok4 = ($r4['http'] === 400) || (($r4['body']['code'] ?? null) === 400);
check('T4 过期时间戳被拦截', $ok4, $r4['raw'] ?? '');

// T5 停用的 app → 403
$p5 = make_payload($appB['app_id'], $appB['app_secret'], $pid);
$r5 = http_post_json($url, $p5);
$ok5 = ($r5['http'] === 403) || (($r5['body']['code'] ?? null) === 403);
check('T5 停用接入方被拦截', $ok5, $r5['raw'] ?? '');

// T6 白名单越权（请求不在白名单内的播放列表）→ 403
$p6 = make_payload($appA['app_id'], $appA['app_secret'], $pid, ['playlist_ids' => [99999]]);
$r6 = http_post_json($url, $p6);
$ok6 = ($r6['http'] === 403) || (($r6['body']['code'] ?? null) === 403);
check('T6 白名单越权被拦截', $ok6, $r6['raw'] ?? '');

// T7 缺少 app_id → 401
$p7 = make_payload($appA['app_id'], $appA['app_secret'], $pid);
unset($p7['app_id']);
// app_id 被移除后需重算签名（否则会先因签名失败被拦）
$p7 = [
    'timestamp' => time(),
    'nonce' => bin2hex(random_bytes(8)),
    'playlist_ids' => [$pid],
];
$p7['sign'] = calc_sign($p7, $appA['app_secret']);
$r7 = http_post_json($url, $p7);
$ok7 = ($r7['http'] === 401) || (($r7['body']['code'] ?? null) === 401);
check('T7 缺少 app_id 被拦截', $ok7, $r7['raw'] ?? '');

// T8 不传 max_ip_per_day → 默认应为 4
$p8 = [
    'app_id' => $appA['app_id'],
    'timestamp' => time(),
    'nonce' => bin2hex(random_bytes(8)),
    'expire_days' => 30,
    'playlist_ids' => [$pid],
    'note' => 'test',
];
$p8['sign'] = calc_sign($p8, $appA['app_secret']);
$r8 = http_post_json($url, $p8);
$tk8 = $r8['body']['data']['token'] ?? null;
$max_ip8 = null;
if ($tk8) {
    $row = get_db_connection()->prepare('SELECT max_ip_per_day FROM tokens WHERE token = ?');
    $row->execute([$tk8]);
    $max_ip8 = (int) $row->fetchColumn();
}
$ok8 = ($r8['http'] === 200) && ($max_ip8 === 4);
check('T8 不传 max_ip_per_day 默认为 4', $ok8, 'max_ip_per_day=' . var_export($max_ip8, true));

// ---------------- 关闭服务器 ----------------
proc_terminate($proc);
proc_close($proc);
echo "\n[INFO] 内置服务器已关闭\n";

// ---------------- 结果汇总 ----------------
$pass = count(array_filter($results));
$total = count($results);
echo "\n========== 结果 {$pass}/{$total} 通过 ==========\n";
echo $pass === $total ? "全部通过 ✅\n" : "存在失败 ❌  请检查上面 [FAIL] 项\n";

// ---------------- 可选清理 ----------------
if (in_array('--cleanup', $argv ?? [], true)) {
    // 删除本次测试造的播放列表、接入方，以及测试 token
    $db->prepare("DELETE FROM token_playlists WHERE token_id IN (SELECT id FROM tokens WHERE note='test')");
    $db->exec("DELETE FROM token_playlists WHERE token_id IN (SELECT id FROM tokens WHERE note='test')");
    $db->exec("DELETE FROM tokens WHERE note='test'");
    $db->exec("DELETE FROM api_nonces");
    $appAid = get_app_by_app_id($appA['app_id'])['id'] ?? null;
    $appBid = get_app_by_app_id($appB['app_id'])['id'] ?? null;
    if ($appAid) { delete_api_app($appAid); }
    if ($appBid) { delete_api_app($appBid); }
    $db->prepare("DELETE FROM playlists WHERE id = ?")->execute([$pid]);
    echo "[INFO] 已清理本次测试数据\n";
} else {
    echo "[INFO] 如需清理测试数据，追加 --cleanup 参数重新运行\n";
}

exit($pass === $total ? 0 : 1);
