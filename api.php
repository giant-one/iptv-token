<?php
/**
 * 对外 API 接口
 *
 * POST /api.php?action=create_token  创建 Token
 * POST /api.php?action=update_token  修改指定 Token 的有效期和播放列表
 *
 * 鉴权方式: Authorization: Bearer <API_KEY>
 * 返回格式: JSON
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin/db_functions.php';

header('Content-Type: application/json; charset=utf-8');

// ---- 鉴权 ----
function api_authenticate() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($header) || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        api_error('未提供有效的 Authorization 头', 401);
    }
    $key = trim($m[1]);
    if (!defined('API_KEY') || empty(API_KEY) || !hash_equals(API_KEY, $key)) {
        api_error('API Key 无效', 403);
    }
}

// ---- 响应辅助 ----
function api_success($data = [], $message = 'ok') {
    echo json_encode(['code' => 0, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error($message, $http_code = 400) {
    http_response_code($http_code);
    echo json_encode(['code' => $http_code, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- 获取 JSON 请求体 ----
function api_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        // 兼容 form 表单
        $data = $_POST;
    }
    return $data;
}

// ---- 验证播放列表 ID 数组 ----
function validate_playlist_ids($playlist_ids) {
    if (!is_array($playlist_ids) || empty($playlist_ids)) {
        api_error('playlist_ids 必须是非空数组');
    }
    foreach ($playlist_ids as $pid) {
        $playlist = get_playlist_by_id((int)$pid);
        if (!$playlist) {
            api_error("播放列表 ID {$pid} 不存在");
        }
    }
}

// ---- 获取当前基础 URL ----
function get_base_url() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host;
}

// ---- 解析有效期参数，返回 expire_at 时间戳 ----
function parse_expire($input) {
    if (!isset($input['expire_days']) && !isset($input['expire_at']) && !isset($input['expire_date'])) {
        return 0; // 永不过期
    }

    // 方式1: expire_days — 从现在起 N 天后过期
    if (isset($input['expire_days'])) {
        $days = (int)$input['expire_days'];
        if ($days <= 0) return 0;
        return time() + $days * 86400;
    }

    // 方式2: expire_at — 直接传时间戳
    if (isset($input['expire_at'])) {
        $ts = (int)$input['expire_at'];
        return $ts > 0 ? $ts : 0;
    }

    // 方式3: expire_date — 传日期字符串 "2025-12-31" 或 "2025-12-31 23:59:59"
    if (isset($input['expire_date'])) {
        $ts = strtotime($input['expire_date']);
        return $ts ? $ts : 0;
    }

    return 0;
}

// ========== 路由 ==========

api_authenticate();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('仅支持 POST 请求', 405);
}

switch ($action) {

    // ==================== 创建 Token ====================
    case 'create_token':
        $input = api_input();

        // 可选: 自定义 token 值，否则自动生成
        $token = trim($input['token'] ?? '');
        if (empty($token)) {
            $token = generate_unique_token();
        } elseif (token_exists($token)) {
            api_error('Token 已存在');
        }

        // 有效期
        $expire_at = parse_expire($input);

        // 播放列表
        $playlist_ids = $input['playlist_ids'] ?? [];
        validate_playlist_ids($playlist_ids);

        // 可选参数
        $channel     = trim($input['channel'] ?? 'api');
        $max_usage   = isset($input['max_usage']) ? (int)$input['max_usage'] : 0;
        $max_ip      = isset($input['max_ip_per_day']) ? (int)$input['max_ip_per_day'] : 0;
        $status      = isset($input['status']) ? (int)$input['status'] : 1;
        $note        = trim($input['note'] ?? '');

        $data = [
            'token'          => $token,
            'expire_at'      => $expire_at,
            'max_usage'      => $max_usage,
            'max_ip_per_day' => $max_ip,
            'status'         => $status,
            'note'           => $note,
            'channel'        => $channel,
        ];

        if (!create_token($data)) {
            api_error('创建 Token 失败', 500);
        }

        // 获取新 Token ID
        $db = get_db_connection();
        $token_id = $db->lastInsertId();

        // 绑定播放列表
        foreach ($playlist_ids as $pid) {
            add_token_playlist($token_id, (int)$pid);
        }

        $subscribe_url = get_base_url() . '/live.php?token=' . urlencode($token) . '&c=' . urlencode($channel);

        api_success([
            'id'           => (int)$token_id,
            'token'        => $token,
            'expire_at'    => $expire_at,
            'expire_date'  => $expire_at > 0 ? date('Y-m-d H:i:s', $expire_at) : null,
            'playlist_ids' => array_map('intval', $playlist_ids),
            'channel'      => $channel,
            'status'       => $status,
            'subscribe_url'=> $subscribe_url,
        ], 'Token 创建成功');
        break;

    // ==================== 修改 Token ====================
    case 'update_token':
        $input = api_input();

        // 必须提供 token 值来定位
        $token_value = trim($input['token'] ?? '');
        if (empty($token_value)) {
            api_error('token 参数不能为空');
        }

        // 查找 token
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT * FROM tokens WHERE token = :token LIMIT 1');
        $stmt->bindValue(':token', $token_value);
        $stmt->execute();
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token_data) {
            api_error('Token 不存在', 404);
        }

        $token_id = (int)$token_data['id'];

        // 构建更新数据（只更新传入的字段，其余保持不变）
        $update = [
            'token'          => $token_data['token'],
            'expire_at'      => $token_data['expire_at'],
            'max_usage'      => $token_data['max_usage'],
            'max_ip_per_day' => $token_data['max_ip_per_day'] ?? 0,
            'status'         => $token_data['status'],
            'note'           => $token_data['note'],
            'channel'        => $token_data['channel'],
        ];

        // 更新有效期
        if (isset($input['add_days'])) {
            // 续期模式：在现有过期时间基础上增加天数
            $add_days = (int)$input['add_days'];
            if ($add_days > 0) {
                $base_time = (int)$update['expire_at'];
                // 如果原来是永不过期(0)或已过期，则以当前时间为基准
                if ($base_time <= 0 || $base_time < time()) {
                    $base_time = time();
                }
                $update['expire_at'] = $base_time + $add_days * 86400;
            }
        } elseif (isset($input['expire_days']) || isset($input['expire_at']) || isset($input['expire_date'])) {
            $update['expire_at'] = parse_expire($input);
        }

        // 更新播放列表
        $new_playlist_ids = null;
        if (isset($input['playlist_ids'])) {
            $new_playlist_ids = $input['playlist_ids'];
            validate_playlist_ids($new_playlist_ids);
        }

        // 其他可选字段
        if (isset($input['max_usage']))      $update['max_usage']      = (int)$input['max_usage'];
        if (isset($input['max_ip_per_day'])) $update['max_ip_per_day'] = (int)$input['max_ip_per_day'];
        if (isset($input['status']))         $update['status']         = (int)$input['status'];
        if (isset($input['note']))           $update['note']           = trim($input['note']);
        if (isset($input['channel']))        $update['channel']        = trim($input['channel']);

        if (!update_token($token_id, $update)) {
            api_error('更新 Token 失败', 500);
        }

        // 更新播放列表权限
        if ($new_playlist_ids !== null) {
            delete_token_playlists($token_id);
            foreach ($new_playlist_ids as $pid) {
                add_token_playlist($token_id, (int)$pid);
            }
        }

        // 返回最新数据
        $current_playlist_ids = get_token_playlist_ids($token_id);

        $subscribe_url = get_base_url() . '/live.php?token=' . urlencode($update['token']) . '&c=' . urlencode($update['channel']);

        api_success([
            'id'           => $token_id,
            'token'        => $update['token'],
            'expire_at'    => $update['expire_at'],
            'expire_date'  => $update['expire_at'] > 0 ? date('Y-m-d H:i:s', $update['expire_at']) : null,
            'playlist_ids' => array_map('intval', $current_playlist_ids),
            'channel'      => $update['channel'],
            'status'       => $update['status'],
            'subscribe_url'=> $subscribe_url,
        ], 'Token 更新成功');
        break;

    default:
        api_error('未知的 action，支持: create_token, update_token', 400);
}