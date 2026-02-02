<?php
require_once 'config.php';

echo "开始迁移数据库...\n";

try {
    if (DB_DRIVER === 'sqlite') {
        $db = new PDO('sqlite:' . DB_FILE);
    } else {
        $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查playlists表是否有name_en字段
    if (DB_DRIVER === 'sqlite') {
        $stmt = $db->query("PRAGMA table_info(playlists)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasNameEn = false;
        $hasUrl = false;

        foreach ($columns as $col) {
            if ($col['name'] === 'name_en') {
                $hasNameEn = true;
            }
            if ($col['name'] === 'url') {
                $hasUrl = true;
            }
        }

        if ($hasNameEn && !$hasUrl) {
            echo "检测到旧版本数据库结构，开始迁移...\n";

            // SQLite需要重建表
            $db->exec("ALTER TABLE playlists RENAME TO playlists_old");

            $db->exec("
                CREATE TABLE playlists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    url TEXT NOT NULL,
                    created_at INTEGER,
                    updated_at INTEGER
                )
            ");

            // 复制数据，将name_en作为临时URL
            $db->exec("
                INSERT INTO playlists (id, name, url, created_at, updated_at)
                SELECT id, name, name_en, created_at, updated_at FROM playlists_old
            ");

            $db->exec("DROP TABLE playlists_old");

            echo "playlists表迁移完成\n";
        } else if ($hasUrl) {
            echo "playlists表已是新结构，无需迁移\n";
        }

        // 创建token_playlists表
        $db->exec("
            CREATE TABLE IF NOT EXISTS token_playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_id INTEGER NOT NULL,
                playlist_id INTEGER NOT NULL,
                created_at INTEGER,
                UNIQUE(token_id, playlist_id)
            )
        ");

    } else {
        // MySQL版本
        $stmt = $db->query("SHOW COLUMNS FROM playlists LIKE 'name_en'");
        $hasNameEn = $stmt->rowCount() > 0;

        $stmt = $db->query("SHOW COLUMNS FROM playlists LIKE 'url'");
        $hasUrl = $stmt->rowCount() > 0;

        if ($hasNameEn && !$hasUrl) {
            echo "检测到旧版本数据库结构，开始迁移...\n";

            // 添加url字段（先允许NULL）
            $db->exec("ALTER TABLE playlists ADD COLUMN url TEXT");

            // 将name_en的值复制到url
            $db->exec("UPDATE playlists SET url = name_en");

            // 修改url字段为NOT NULL
            $db->exec("ALTER TABLE playlists MODIFY COLUMN url TEXT NOT NULL");

            // 删除name_en字段
            $db->exec("ALTER TABLE playlists DROP COLUMN name_en");

            echo "playlists表迁移完成\n";
        } else if ($hasUrl) {
            echo "playlists表已是新结构，无需迁移\n";
        }

        // 创建token_playlists表
        $db->exec("
            CREATE TABLE IF NOT EXISTS token_playlists (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token_id INT UNSIGNED NOT NULL,
                playlist_id INT UNSIGNED NOT NULL,
                created_at BIGINT,
                UNIQUE KEY unique_token_playlist (token_id, playlist_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    echo "token_playlists表创建完成\n";
    echo "数据库迁移成功！\n";

} catch (PDOException $e) {
    echo '迁移失败: ' . $e->getMessage() . "\n";
}
?>
