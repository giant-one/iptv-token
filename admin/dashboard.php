<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 获取数据统计
$db = get_db_connection();

// 获取Token总数
$total_tokens = get_tokens_count();

// 获取日志总数
$total_logs = get_logs_count();

// 获取今日日志数
$today_start = strtotime('today');
$stmt = $db->prepare('SELECT COUNT(*) FROM logs WHERE access_time >= ?');
$stmt->execute([$today_start]);
$today_logs = $stmt->fetchColumn();

// 获取最近的Token
$recent_tokens = get_all_tokens(5);

// 获取最近的日志
$recent_logs = get_logs(5);

// 包含头部
require_once '../templates/header.php';
?>

<h2>控制台</h2>

<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
    <div style="flex: 1; background: #3498db; color: white; padding: 20px; border-radius: 5px; min-width: 200px;">
        <h3>Token 总数</h3>
        <p style="font-size: 2em;"><?php echo $total_tokens; ?></p>
        <a href="tokens.php" style="color: white; text-decoration: underline;">查看所有</a>
    </div>
    
    <div style="flex: 1; background: #2ecc71; color: white; padding: 20px; border-radius: 5px; min-width: 200px;">
        <h3>总访问次数</h3>
        <p style="font-size: 2em;"><?php echo $total_logs; ?></p>
        <a href="logs.php" style="color: white; text-decoration: underline;">查看日志</a>
    </div>
    
    <div style="flex: 1; background: #e67e22; color: white; padding: 20px; border-radius: 5px; min-width: 200px;">
        <h3>今日访问</h3>
        <p style="font-size: 2em;"><?php echo $today_logs; ?></p>
    </div>
</div>

<div class="usage-guide">
    <h3>使用说明</h3>
    <p>访问链接格式: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/../live.php?token=YOUR_TOKEN&c=CHANNEL_URL</code></p>
    <ul>
        <li><strong>token</strong>: 在 Token 管理页面创建的令牌</li>
        <li><strong>c</strong>: 需要访问的原始直播源 URL（将作为参数传递）</li>
    </ul>
</div>

<div style="display: flex; flex-wrap: wrap; gap: 20px;">
    <div style="flex: 1; min-width: 300px;">
        <h3>最新 Token</h3>
        <?php if (count($recent_tokens) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>过期时间</th>
                    <th>已使用/限制</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_tokens as $token): ?>
                <tr>
                    <td><?php echo htmlspecialchars($token['token']); ?></td>
                    <td><?php echo format_timestamp($token['expire_at']); ?></td>
                    <td>
                        <?php
                        echo $token['usage_count'];
                        if ($token['max_usage'] > 0) {
                            echo ' / ' . $token['max_usage'];
                        } else {
                            echo ' / ∞';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>暂无 Token，<a href="token_add.php">点此创建</a></p>
        <?php endif; ?>
    </div>
    
    <div style="flex: 1; min-width: 300px;">
        <h3>最近访问</h3>
        <?php if (count($recent_logs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>IP</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['token']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip']); ?></td>
                    <td><?php echo format_timestamp($log['access_time']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>暂无访问记录</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
