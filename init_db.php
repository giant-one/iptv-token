<?php
require_once 'config.php';

if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0777, true);

try {
    if (DB_DRIVER === 'sqlite') {
        echo "Using SQLite database\n";
        $db = new PDO('sqlite:' . DB_FILE);
    } else {
        echo "Using MySQL database\n";
        $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ 根据驱动动态生成建表 SQL
    if (DB_DRIVER === 'sqlite') {
        $sqlTokens = "
            CREATE TABLE IF NOT EXISTS tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT UNIQUE,
                expire_at INTEGER,
                max_usage INTEGER DEFAULT 0,
                usage_count INTEGER DEFAULT 0,
                note TEXT,
                created_at INTEGER,
                updated_at INTEGER
            );
        ";

        $sqlLogs = "
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT,
                ip TEXT,
                channel TEXT,
                access_time INTEGER
            );
        ";
    } else {
        $sqlTokens = "
            CREATE TABLE IF NOT EXISTS tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) UNIQUE NOT NULL,
                expire_at BIGINT DEFAULT 0,
                max_usage INT DEFAULT 0,
                usage_count INT DEFAULT 0,
                note TEXT,
                created_at BIGINT,
                updated_at BIGINT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $sqlLogs = "
            CREATE TABLE IF NOT EXISTS logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255),
                ip VARCHAR(64),
                channel VARCHAR(255),
                access_time BIGINT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }

    $db->exec($sqlTokens);
    $db->exec($sqlLogs);

    echo "Database initialized successfully!\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>