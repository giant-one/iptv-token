<?php
// IP 查询 API
require_once 'db_functions.php';

header('Content-Type: application/json');

$ip = isset($_GET['ip']) ? $_GET['ip'] : '';
if (empty($ip)) {
    echo json_encode(['error' => 'IP 参数缺失']);
    exit;
}

// 简单的IP格式验证
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['error' => 'IP 格式无效']);
    exit;
}

// 缓存配置
$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/ip_' . md5($ip) . '.json';
$cache_expire = 86400; // 缓存24小时

// 创建缓存目录
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// 检查缓存是否存在且未过期
if (file_exists($cache_file)) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data && (time() - $cache_data['time']) < $cache_expire) {
        echo json_encode(['ret' => 'success', 'cache' => true] + $cache_data['data']);
        exit;
    }
}

// 查询 API
$result = query_ip_location($ip);

// 缓存结果
if ($result['ret'] === 'success') {
    $cache_data = [
        'time' => time(),
        'data' => [
            'country' => $result['country'] ?? '',
            'prov' => $result['prov'] ?? '',
            'city' => $result['city'] ?? ''
        ]
    ];
    file_put_contents($cache_file, json_encode($cache_data));
}

echo json_encode($result);
?>
