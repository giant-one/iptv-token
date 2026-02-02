<?php
// 设置默认时区
date_default_timezone_set('Asia/Shanghai');

// 读取 .env 文件
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // 跳过注释行
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!empty($name)) {
            $_ENV[$name] = $value;
        }
    }
}

// 从环境变量获取配置，如果没有则使用默认值
// 数据库配置
define('DB_DRIVER', $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'mysql'); // sqlite 或 mysql
define('DB_FILE', __DIR__ . '/data/database.sqlite');

// MySQL 配置（仅当使用 mysql 时生效）
define('DB_DSN_MYSQL', $_ENV['DB_DSN_MYSQL'] ?? getenv('DB_DSN_MYSQL') ?: 'mysql:host=localhost:3306;dbname=iptv;charset=utf8mb4');
define('DB_USER_MYSQL', $_ENV['DB_USER_MYSQL'] ?? getenv('DB_USER_MYSQL') ?: 'root');
define('DB_PASS_MYSQL', $_ENV['DB_PASS_MYSQL'] ?? getenv('DB_PASS_MYSQL') ?: '123456');

// 默认管理员
define('ADMIN_USER', $_ENV['ADMIN_USER'] ?? getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', $_ENV['ADMIN_PASS'] ?? getenv('ADMIN_PASS') ?: 'password');

// 直播流重定向域名
define('REDIRECT_URL', $_ENV['REDIRECT_URL'] ?? getenv('REDIRECT_URL') ?: 'http://example.com');

// 过期或无效令牌重定向 URL
define('EXPIRED_REDIRECT_URL', $_ENV['EXPIRED_REDIRECT_URL'] ?? getenv('EXPIRED_REDIRECT_URL') ?: 'http://example.com/expired.m3u');
?>
