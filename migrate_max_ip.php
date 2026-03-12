<?php
// 迁移脚本：为tokens表添加max_ip_per_day字段（每天最大IP限制）
require_once 'config.php';

try {
    if (DB_DRIVER === 'sqlite') {
        $db = new PDO('sqlite:' . DB_FILE);
    } else {
        $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查字段是否已存在
    $hasField = false;
    if (DB_DRIVER === 'sqlite') {
        $tableInfo = $db->query("PRAGMA table_info(tokens)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tableInfo as $column) {
            if ($column['name'] === 'max_ip_per_day') {
                $hasField = true;
                break;
            }
        }
    } else {
        $result = $db->query("SHOW COLUMNS FROM tokens LIKE 'max_ip_per_day'")->fetchAll(PDO::FETCH_ASSOC);
        $hasField = count($result) > 0;
    }

    if (!$hasField) {
        $db->exec("ALTER TABLE tokens
ADD COLUMN max_ip_per_day INT UNSIGNED NOT NULL DEFAULT 5
COMMENT '每日允许的最大IP数，0表示不限制';");
        echo "成功添加max_ip_per_day列到tokens表\n";
    } else {
        echo "max_ip_per_day列已存在，无需迁移\n";
    }

} catch (PDOException $e) {
    echo '错误: ' . $e->getMessage() . "\n";
}
?>
