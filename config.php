<?php
// 数据库配置
define('DB_DRIVER', 'mysql'); // 或 mysql
define('DB_FILE', __DIR__ . '/data/database.sqlite');

// MySQL 配置（仅当使用 mysql 时生效）
define('DB_DSN_MYSQL', 'mysql:host=127.0.0.1;dbname=iptv;charset=utf8mb4');
define('DB_USER_MYSQL', 'root');
define('DB_PASS_MYSQL', '123456');

// 默认管理员
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'Admin@123');
?>
