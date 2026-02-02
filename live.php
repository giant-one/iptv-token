<?php
require_once 'config.php';

/**
 * 处理M3U内容，添加刷新时间和到期时间信息
 */
function processM3UContent($content, $tokenInfo) {
    $lines = explode("\n", $content);
    $processedLines = [];
    $addedCustomEntries = false;
    $foundFengniaoExtinf = false;
    $addedEXTm3u = false;
    
    // 获取当前时间和到期时间
    $currentTime = date('Y-m-d H:i:s');
    $expireTime = $tokenInfo['expire_at'] ? date('Y-m-d H:i:s', $tokenInfo['expire_at']) : '永不过期';
    $tokenId = $tokenInfo['id'];

    foreach ($lines as $line) {
        if (empty($line)) {continue;}
        $processedLines[] = $line;

        // 检测是否是#EXTM3U
        if (!$addedEXTm3u && strpos($line, '#EXTM3U') !== false) {
            $processedLines[0] .= ' x-tvg-url="https://live.fanmingming.com/e.xml,http://epg.51zmt.xyz:8000/epg.xml.gz,https://epg.v1.mk/xmltv.xml.gz,http://epg.best/tv/program.xml.gz,https://raw.githubusercontent.com/frantz/EPG/master/epg.xml.gz"';
            $addedEXTm3u = true;
        }

        // 检查是否找到蜂鸟传媒的EXTINF行
        if (!$addedCustomEntries && strpos($line, 'group-title="蜂鸟传媒"') !== false) {
            $foundFengniaoExtinf = true;
        }
        
        // 如果上一行是蜂鸟传媒的EXTINF行，当前行应该是对应的URL行
        // 在URL行之后添加自定义条目
        if ($foundFengniaoExtinf && !$addedCustomEntries && 
            !empty(trim($line)) && 
            !str_starts_with(trim($line), '#')) {
            
            // 添加刷新时间条目
            $processedLines[] = '#EXTINF:-1 tvg-chno="1" tvg-id="" tvg-name="刷新时间-' . $tokenId . " ". $currentTime . '" tvg-logo="http://www.xxl2a.xyz:5080/logo.png" group-title="蜂鸟传媒",刷新时间 ' . $currentTime;
            $processedLines[] = 'http://xxl2a.xyz:9527/hls/ok.m3u8';

            // 添加到期时间条目
            $processedLines[] = '#EXTINF:-1 tvg-chno="1" tvg-id="" tvg-name="到期时间-' . $tokenId . " " . $expireTime . '" tvg-logo="http://www.xxl2a.xyz:5080/logo.png" group-title="蜂鸟传媒",到期时间 ' . $expireTime;
            $processedLines[] = 'http://xxl2a.xyz:9527/hls/ok.m3u8';

            $addedCustomEntries = true;
            $foundFengniaoExtinf = false;
        }
    }
    
    return implode("\n", $processedLines);
}

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
$playlist_id = $_GET['p'] ?? ''; // 播放列表ID
$path_type = $_GET['t'] ?? ''; //旧版本参数

if (!$token || !$channel) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

if (!$playlist_id && $path_type) {
    $playlist_id = 1;
}

if (!$playlist_id) {
    http_response_code(400);
    echo 'Missing playlist parameter';
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

    $token_id = $row['id'];

    // 验证Token是否有该播放列表的权限
    $playlist_id_int = (int)$playlist_id;

    // 检查播放列表是否存在
    $playlistStmt = $db->prepare('SELECT * FROM playlists WHERE id = :id');
    $playlistStmt->bindValue(':id', $playlist_id_int, PDO::PARAM_INT);
    $playlistStmt->execute();
    $playlistRow = $playlistStmt->fetch(PDO::FETCH_ASSOC);

    if (!$playlistRow) {
        // 播放列表不存在
        http_response_code(404);
        echo 'Playlist not found';
        exit;
    }

    // 检查Token是否有该播放列表的权限
    $permissionStmt = $db->prepare('SELECT COUNT(*) FROM token_playlists WHERE token_id = :token_id AND playlist_id = :playlist_id');
    $permissionStmt->bindValue(':token_id', $token_id, PDO::PARAM_INT);
    $permissionStmt->bindValue(':playlist_id', $playlist_id_int, PDO::PARAM_INT);
    $permissionStmt->execute();

    if ($permissionStmt->fetchColumn() == 0) {
        // 没有权限访问该播放列表
        http_response_code(403);
        echo 'Access denied: No permission for this playlist';
        exit;
    }

    // 记录日志和计数
    $insertStmt = $db->prepare('INSERT INTO logs(token, ip, channel, access_time, user_agent) VALUES (:token, :ip, :channel, :access_time, :user_agent)');
    $insertStmt->bindValue(':token', $token);
    $insertStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $insertStmt->bindValue(':channel', $channel);
    $insertStmt->bindValue(':access_time', time(), PDO::PARAM_INT);
    $insertStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
    $insertStmt->execute();
    
    $updateStmt = $db->prepare('UPDATE tokens SET usage_count = usage_count + 1 WHERE token = :token');
    $updateStmt->bindValue(':token', $token);
    $updateStmt->execute();

    // 验证通过后获取播放列表内容并进行二次加工
    // 直接使用数据库中的URL
    $targetUrl = $playlistRow['url'];

    // 获取原始m3u内容
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IPTV-Player/1.0');
    $originalContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $originalContent === false) {
        // 如果获取失败，重定向到原URL
        header('Location: ' . $targetUrl, true, 302);
        exit;
    }

    // 对内容进行二次加工
    $processedContent = processM3UContent($originalContent, $row);
    
    // 设置响应头
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Disposition: inline; filename="playlist.m3u"');
    
    // 输出处理后的内容
    echo $processedContent;
    exit;
} catch (PDOException $e) {
    // 数据库错误，也重定向到过期链接
    header('Location: ' . EXPIRED_REDIRECT_URL, true, 302);
    exit;
}
?>
