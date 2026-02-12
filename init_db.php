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
                status INT DEFAULT 1,
                note TEXT,
                channel TEXT,
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

        $sqlPlaylists = "
            CREATE TABLE IF NOT EXISTS playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                name_en TEXT NOT NULL,
                created_at INTEGER,
                updated_at INTEGER
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
                status INT DEFAULT 1,
                note TEXT,
                channel VARCHAR(255) DEFAULT NULL,
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
                access_time BIGINT,
                user_agent varchar(255)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $sqlPlaylists = "
            CREATE TABLE IF NOT EXISTS playlists (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                created_at BIGINT,
                updated_at BIGINT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $sqlTokenPlaylists = "
            CREATE TABLE IF NOT EXISTS token_playlists (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token_id INT UNSIGNED NOT NULL,
                playlist_id INT UNSIGNED NOT NULL,
                created_at BIGINT,
                UNIQUE KEY unique_token_playlist (token_id, playlist_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    }

    $db->exec($sqlTokens);
    $db->exec($sqlLogs);
    $db->exec($sqlPlaylists);

    if (DB_DRIVER === 'mysql') {
        $db->exec($sqlTokenPlaylists);
    } else {
        // SQLite版本
        $sqlTokenPlaylists = "
            CREATE TABLE IF NOT EXISTS token_playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_id INTEGER NOT NULL,
                playlist_id INTEGER NOT NULL,
                created_at INTEGER,
                UNIQUE(token_id, playlist_id)
            );
        ";
        $db->exec($sqlTokenPlaylists);
    }

    echo "Database initialized successfully!\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
