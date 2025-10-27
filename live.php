<?php
require_once 'config.php';

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
        http_response_code(403);
        echo 'Invalid token';
        exit;
    }

    if ($row['expire_at'] && $row['expire_at'] < time()) {
        http_response_code(403);
        echo 'Token expired';
        exit;
    }

    if ($row['max_usage'] > 0 && $row['usage_count'] >= $row['max_usage']) {
        http_response_code(403);
        echo 'Usage limit reached';
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

    // 模拟直播流代理
    header('Content-Type: application/vnd.apple.mpegurl');
    echo "#EXTM3U\n#EXTINF:10, Sample Stream\nhttps://example.com/stream.m3u8";
} catch (PDOException $e) {
    http_response_code(500);
    echo 'DB Error';
}
?>
