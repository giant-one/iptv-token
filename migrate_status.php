<?php
// 迁移脚本：为tokens表添加status字段
require_once 'config.php';

try {
    if (DB_DRIVER === 'sqlite') {
        $db = new PDO('sqlite:' . DB_FILE);
    } else {
        $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查status列是否已存在
    $hasStatus = false;
    if (DB_DRIVER === 'sqlite') {
        $tableInfo = $db->query("PRAGMA table_info(tokens)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tableInfo as $column) {
            if ($column['name'] === 'status') {
                $hasStatus = true;
                break;
            }
        }
    } else {
        // MySQL语法
        $stmt = $db->prepare("SHOW COLUMNS FROM tokens LIKE 'status'");
        $stmt->execute();
        $hasStatus = $stmt->fetch() !== false;
    }

    if (!$hasStatus) {
        //INT UNSIGNED NOT NULL DEFAULT 5
        $db->exec("ALTER TABLE tokens ADD COLUMN status INT UNSIGNED NOT NULL DEFAULT 1");
        echo "成功添加status列到tokens表\n";

        // 将所有现有token设为有效状态
        $db->exec("UPDATE tokens SET status = 1 WHERE status IS NULL");
        echo "已将所有现有token设置为有效状态\n";
    } else {
        echo "status列已存在，无需迁移\n";
    }

} catch (PDOException $e) {
    echo '错误: ' . $e->getMessage() . "\n";
}
?>
