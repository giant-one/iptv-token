<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 获取筛选条件
$filter_token = isset($_GET['token']) ? $_GET['token'] : null;

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取日志总数
$total_logs = get_logs_count($filter_token);

// 获取当前页的日志
$logs = get_logs($per_page, $offset, $filter_token);

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2><?php echo $filter_token ? 'Token 日志：' . htmlspecialchars($filter_token) : '访问日志'; ?></h2>
    <div>
        <?php if ($filter_token): ?>
        <a href="logs.php" class="btn">查看所有日志</a>
        <?php endif; ?>
        <a href="tokens.php" class="btn">返回 Token 列表</a>
    </div>
</div>

<?php if (count($logs) > 0): ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Token</th>
            <th>IP</th>
            <th>频道/直播源</th>
            <th>访问时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo $log['id']; ?></td>
            <td>
                <?php if ($filter_token): ?>
                <?php echo htmlspecialchars($log['token']); ?>
                <?php else: ?>
                <a href="logs.php?token=<?php echo urlencode($log['token']); ?>"><?php echo htmlspecialchars($log['token']); ?></a>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($log['ip']); ?></td>
            <td><?php echo htmlspecialchars($log['channel']); ?></td>
            <td><?php echo format_timestamp($log['access_time']); ?></td>
            <td>
                <?php if (!$filter_token): ?>
                <a href="logs.php?token=<?php echo urlencode($log['token']); ?>" class="btn btn-sm">仅看此 Token</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// 生成分页
$url_pattern = $filter_token ? 'logs.php?token=' . urlencode($filter_token) . '&page=%d' : 'logs.php?page=%d';
echo generate_pagination($total_logs, $per_page, $page, $url_pattern);
?>

<?php else: ?>
<div class="alert info">
    <p>暂无访问日志记录。</p>
</div>
<?php endif; ?>

<div class="usage-guide">
    <h3>日志说明</h3>
    <p>此页面显示了所有直播源的访问记录，包括 Token、访问 IP、访问的频道 URL 和访问时间。</p>
    <p>点击 Token 可以筛选查看该 Token 的所有访问记录。</p>
    <p>系统会记录每次访问，即使 Token 已过期或超过使用限制，访问失败也会被记录。</p>
</div>

<?php require_once '../templates/footer.php'; ?>
