<?php
/**
 * 开放平台公共库
 * - JSON 响应辅助
 * - 请求体解析
 * - 接入方鉴权 (app_id)
 * - 参数签名校验 (SHA256 拼接密钥)
 * - 防重放 (timestamp + nonce)
 * - 有效期解析
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/db_functions.php';

header('Content-Type: application/json; charset=utf-8');

// 时间戳允许的偏差（秒）
if (!defined('OPEN_API_TIME_WINDOW')) {
    define('OPEN_API_TIME_WINDOW', 300); // ±5 分钟
}

// ---- 响应辅助 ----
function open_success($data = [], $message = 'ok') {
    echo json_encode(['code' => 0, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function open_error($message, $http_code = 400) {
    http_response_code($http_code);
    echo json_encode(['code' => $http_code, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- 获取请求体（JSON 优先，兼容 form 表单）----
function open_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    return $data;
}

// ---- 计算签名 ----
// 规则：取除 sign 外的全部参数，按 key 字典序排序，http_build_query 拼串，
//       末尾追加 app_secret，再做 sha256。
function open_calc_sign($params, $app_secret) {
    unset($params['sign']);
    ksort($params);
    // 数组类参数（如 playlist_ids）转为紧凑 JSON，保证两端一致
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    $queryString = http_build_query($params);
    return hash('sha256', $queryString . $app_secret);
}

// ---- 鉴权 + 签名 + 防重放 全流程校验，返回接入方记录 ----
function open_authenticate($input) {
    // 1. app_id
    $app_id = trim($input['app_id'] ?? '');
    if (empty($app_id)) {
        open_error('缺少 app_id', 401);
    }
    $app = get_app_by_app_id($app_id);
    if (!$app) {
        open_error('app_id 无效', 403);
    }
    if ((int)$app['status'] !== 1) {
        open_error('该接入方已被停用', 403);
    }

    // 2. 防重放：timestamp + nonce
    if (!isset($input['timestamp']) || !is_numeric($input['timestamp'])) {
        open_error('缺少或非法的 timestamp', 400);
    }
    $timestamp = (int)$input['timestamp'];
    if (abs(time() - $timestamp) > OPEN_API_TIME_WINDOW) {
        open_error('请求已过期（timestamp 超出允许范围）', 400);
    }

    $nonce = trim($input['nonce'] ?? '');
    if (empty($nonce)) {
        open_error('缺少 nonce', 400);
    }

    // 3. 签名校验（在 nonce 落库前，避免无效请求占用 nonce）
    $sign = $input['sign'] ?? '';
    if (empty($sign)) {
        open_error('缺少 sign', 400);
    }
    $expected = open_calc_sign($input, $app['app_secret']);
    if (!hash_equals($expected, (string)$sign)) {
        open_error('签名校验失败', 400);
    }

    // 4. nonce 去重（签名通过后才占用）
    if (!check_and_store_nonce($nonce, OPEN_API_TIME_WINDOW)) {
        open_error('重复请求（nonce 已使用）', 409);
    }

    return $app;
}

// ---- 解析有效期参数，返回 expire_at 时间戳（0 = 永不过期）----
function open_parse_expire($input) {
    if (isset($input['expire_days'])) {
        $days = (int)$input['expire_days'];
        return $days > 0 ? time() + $days * 86400 : 0;
    }
    if (isset($input['expire_at'])) {
        $ts = (int)$input['expire_at'];
        return $ts > 0 ? $ts : 0;
    }
    if (isset($input['expire_date'])) {
        $ts = strtotime($input['expire_date']);
        return $ts ? $ts : 0;
    }
    return 0; // 永不过期
}

// ---- 获取当前基础 URL ----
function open_base_url() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
