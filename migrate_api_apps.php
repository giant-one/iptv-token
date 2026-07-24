<?php
// 迁移脚本：为开放平台创建 api_apps（接入方）与 api_nonces（防重放）两张表
require_once 'config.php';

try {
    if (DB_DRIVER === 'sqlite') {
        $db = new PDO('sqlite:' . DB_FILE);
    } else {
        $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查表是否已存在
    function table_exists($db, $table) {
        if (DB_DRIVER === 'sqlite') {
            $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
            $stmt->bindValue(':t', $table);
            $stmt->execute();
            return $stmt->fetch() !== false;
        } else {
            $stmt = $db->prepare("SHOW TABLES LIKE :t");
            $stmt->bindValue(':t', $table);
            $stmt->execute();
            return $stmt->fetch() !== false;
        }
    }

    if (DB_DRIVER === 'sqlite') {
        $sqlApiApps = "
            CREATE TABLE IF NOT EXISTS api_apps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                app_id TEXT UNIQUE NOT NULL,
                app_secret TEXT NOT NULL,
                name TEXT,
                status INTEGER DEFAULT 1,
                default_playlist_ids TEXT,
                created_at INTEGER,
                updated_at INTEGER
            );
        ";
        $sqlApiNonces = "
            CREATE TABLE IF NOT EXISTS api_nonces (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nonce TEXT UNIQUE NOT NULL,
                expire_at INTEGER
            );
        ";
    } else {
        $sqlApiApps = "
            CREATE TABLE IF NOT EXISTS api_apps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                app_id VARCHAR(64) UNIQUE NOT NULL,
                app_secret VARCHAR(128) NOT NULL,
                name VARCHAR(255),
                status INT DEFAULT 1,
                default_playlist_ids TEXT,
                created_at BIGINT,
                updated_at BIGINT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $sqlApiNonces = "
            CREATE TABLE IF NOT EXISTS api_nonces (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nonce VARCHAR(128) UNIQUE NOT NULL,
                expire_at BIGINT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }

    if (!table_exists($db, 'api_apps')) {
        $db->exec($sqlApiApps);
        echo "成功创建 api_apps 表\n";
    } else {
        echo "api_apps 表已存在，无需迁移\n";
    }

    if (!table_exists($db, 'api_nonces')) {
        $db->exec($sqlApiNonces);
        echo "成功创建 api_nonces 表\n";
    } else {
        echo "api_nonces 表已存在，无需迁移\n";
    }

} catch (PDOException $e) {
    echo '错误: ' . $e->getMessage() . "\n";
}
?>
