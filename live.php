<?php
require_once 'config.php';

// 检查是否是浏览器直接访问
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserSignatures = ['Mozilla', 'Chrome', 'Safari', 'Firefox', 'Edge', 'Opera'];
$isBrowser = false;

foreach ($browserSignatures as $signature) {
    if (stripos($userAgent, $signature) !== false) {
        $isBrowser = true;
        break;
    }
}

// 如果是浏览器直接访问，返回错误信息
if ($isBrowser) {
    http_response_code(403);
    echo '错误：不允许浏览器直接访问此链接，请使用支持的播放器或应用程序。';
    exit;
}

$token = $_GET['token'] ?? '';
$channel = $_GET['c'] ?? '';

if (!$token || !$channel) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

try {
    $db = (DB_DRIVER === 'sqlite')
        ? new PDO('sqlite:' . DB_FILE)
        : new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);

    $stmt = $db->prepare('SELECT * FROM tokens WHERE token = :token');
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // 无效令牌，重定向到过期链接
        header('Location: ' . EXPIRED_REDIRECT_URL, true, 302);
        exit;
    }

    if ($row['expire_at'] && $row['expire_at'] < time()) {
        // 令牌已过期，重定向到过期链接
        header('Location: ' . EXPIRED_REDIRECT_URL, true, 302);
        exit;
    }

    if ($row['max_usage'] > 0 && $row['usage_count'] >= $row['max_usage']) {
        // 使用次数已达上限，重定向到过期链接
        header('Location: ' . EXPIRED_REDIRECT_URL, true, 302);
        exit;
    }

    // 记录日志和计数
    $insertStmt = $db->prepare('INSERT INTO logs(token, ip, channel, access_time) VALUES (:token, :ip, :channel, :access_time)');
    $insertStmt->bindValue(':token', $token);
    $insertStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $insertStmt->bindValue(':channel', $channel);
    $insertStmt->bindValue(':access_time', time(), PDO::PARAM_INT);
    $insertStmt->execute();
    
    $updateStmt = $db->prepare('UPDATE tokens SET usage_count = usage_count + 1 WHERE token = :token');
    $updateStmt->bindValue(':token', $token);
    $updateStmt->execute();

    // 验证通过后重定向到实际的播放列表
    header('Location: ' . REDIRECT_URL, true, 302);
    exit;
} catch (PDOException $e) {
    // 数据库错误，也重定向到过期链接
    header('Location: ' . EXPIRED_REDIRECT_URL, true, 302);
    exit;
}
?>
