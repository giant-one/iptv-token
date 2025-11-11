<?php
// 从环境变量获取配置，如果没有则使用默认值
// 数据库配置
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite'); // sqlite 或 mysql
define('DB_FILE', __DIR__ . '/data/database.sqlite');

// MySQL 配置（仅当使用 mysql 时生效）
define('DB_DSN_MYSQL', getenv('DB_DSN_MYSQL') ?: 'mysql:host=localhost;dbname=iptv;charset=utf8mb4');
define('DB_USER_MYSQL', getenv('DB_USER_MYSQL') ?: 'iptv_user');
define('DB_PASS_MYSQL', getenv('DB_PASS_MYSQL') ?: 'password');

// 默认管理员
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'password');

// 直播流重定向 URL
define('REDIRECT_URL', getenv('REDIRECT_URL') ?: 'http://example.com/playlist.m3u');
?>
