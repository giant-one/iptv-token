<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取Token总数
$total_tokens = get_tokens_count();

// 获取当前页的Token
$tokens = get_all_tokens($per_page, $offset);

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Token 管理</h2>
    <a href="token_add.php" class="btn btn-success">添加新 Token</a>
</div>

<?php if (count($tokens) > 0): ?>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Token</th>
            <th>过期时间</th>
            <th>使用次数</th>
            <th>限制次数</th>
            <th>备注</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tokens as $token): ?>
        <tr>
            <td><?php echo $token['id']; ?></td>
            <td><?php echo htmlspecialchars($token['token']); ?></td>
            <td><?php echo format_timestamp($token['expire_at']); ?></td>
            <td><?php echo $token['usage_count']; ?></td>
            <td><?php echo $token['max_usage'] > 0 ? $token['max_usage'] : '∞'; ?></td>
            <td><?php echo htmlspecialchars($token['note']); ?></td>
            <td><?php echo format_timestamp($token['created_at']); ?></td>
            <td>
                <a href="token_edit.php?id=<?php echo $token['id']; ?>" class="btn btn-primary btn-sm">编辑</a>
                <a href="token_delete.php?id=<?php echo $token['id']; ?>" class="btn btn-danger btn-sm confirm-delete">删除</a>
                <a href="logs.php?token=<?php echo urlencode($token['token']); ?>" class="btn btn-sm">查看日志</a>
                <a href="#" class="btn btn-sm copy-link" data-link="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/live.php?token=<?php echo urlencode($token['token']); ?>&c=">复制链接</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// 生成分页
echo generate_pagination($total_tokens, $per_page, $page, 'tokens.php?page=%d');
?>

<?php else: ?>
<div class="alert info">
    <p>暂无 Token 数据。<a href="token_add.php">点击此处</a>创建一个新的 Token。</p>
</div>
<?php endif; ?>

<div class="usage-guide">
    <h3>使用说明</h3>
    <p>1. Token 访问链接: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/../live.php?token=YOUR_TOKEN&c=CHANNEL_URL</code></p>
    <p>2. 过期时间为空表示永不过期，限制次数为0表示无限制</p>
    <p>3. 点击"复制链接"可以获取基础URL，只需在后面添加直播源URL即可使用</p>
</div>

<?php require_once '../templates/footer.php'; ?>
