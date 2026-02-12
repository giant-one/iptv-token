<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['log_id'])) {
        delete_log((int)$_POST['log_id']);
        header('Location: logs.php?' . http_build_query(array_filter(['token' => $filter_token ?? null, 'page' => $_GET['page'] ?? null])));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['log_ids']) && is_array($_POST['log_ids'])) {
        delete_logs(array_map('intval', $_POST['log_ids']));
        header('Location: logs.php?' . http_build_query(array_filter(['token' => $filter_token ?? null, 'page' => $_GET['page'] ?? null])));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'clear_token_logs' && isset($_POST['token'])) {
        delete_logs_by_token($_POST['token']);
        header('Location: logs.php?token=' . urlencode($_POST['token']));
        exit;
    }
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
<form method="POST" id="logsForm">
    <input type="hidden" name="action" value="">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;"><input type="checkbox" id="selectAll"></th>
                <th>#</th>
                <th>Token</th>
                <th>IP</th>
                <th>位置</th>
                <th>频道/直播源</th>
                <th>User-Agent</th>
                <th>访问时间</th>
                <th style="width: 120px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><input type="checkbox" name="log_ids[]" value="<?php echo $log['id']; ?>" class="log-checkbox"></td>
                <td><?php echo $log['id']; ?></td>
                <td>
                    <?php if ($filter_token): ?>
                    <?php echo htmlspecialchars($log['token']); ?>
                    <?php else: ?>
                    <a href="logs.php?token=<?php echo urlencode($log['token']); ?>"><?php echo htmlspecialchars($log['token']); ?></a>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($log['ip']); ?></td>
                <td class="ip-location" data-ip="<?php echo htmlspecialchars($log['ip']); ?>">
                    <span class="location-loading">查询中...</span>
                </td>
                <td><?php echo htmlspecialchars($log['channel']); ?></td>
                <td title="<?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>"><?php echo htmlspecialchars(substr($log['user_agent'] ?? 'N/A', 0, 50)) . (strlen($log['user_agent'] ?? '') > 50 ? '...' : ''); ?></td>
                <td><?php echo format_timestamp($log['access_time']); ?></td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteLog(<?php echo $log['id']; ?>)">删除</button>
                    <?php if (!$filter_token): ?>
                    <a href="logs.php?token=<?php echo urlencode($log['token']); ?>" class="btn btn-sm">仅看</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 15px;">
        <button type="button" class="btn btn-danger" onclick="deleteSelectedLogs()">删除选中</button>
        <?php if ($filter_token): ?>
        <button type="button" class="btn btn-danger" onclick="clearAllLogs()">清空此Token所有日志</button>
        <?php endif; ?>
    </div>
</form>

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

<script>
// 使用后端 API 查询 IP 地理位置
async function queryIPLocation(ip) {
    try {
        const response = await fetch(`ip_query.php?ip=${encodeURIComponent(ip)}`);
        const data = await response.json();
        if (data && data.ret === 'success') {
            const parts = [];
            if (data.country) parts.push(data.country);
            if (data.prov) parts.push(data.prov);
            if (data.city) parts.push(data.city);
            return parts.length > 0 ? parts.join(' ') : '未知';
        }
        return data.country || data.error || '未知';
    } catch (e) {
        return '查询失败';
    }
}

// 为所有 IP 查询地理位置
document.addEventListener('DOMContentLoaded', async function() {
    const locationCells = document.querySelectorAll('.ip-location');
    const processedIPs = new Map(); // 使用 Map 缓存结果

    for (const cell of locationCells) {
        const ip = cell.dataset.ip;

        // 对相同的 IP 使用缓存结果
        if (processedIPs.has(ip)) {
            cell.innerHTML = processedIPs.get(ip);
            continue;
        }

        cell.innerHTML = '查询中...';
        const location = await queryIPLocation(ip);
        cell.innerHTML = location;
        processedIPs.set(ip, location);

        // 添加延迟避免请求过快
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    // 全选/取消全选功能
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.log-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
});

// 删除单条日志
function deleteLog(logId) {
    if (!confirm('确定要删除这条日志记录吗？')) {
        return;
    }
    const form = document.getElementById('logsForm');
    form.elements['action'].value = 'delete';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'log_id';
    input.value = logId;
    form.appendChild(input);
    form.submit();
}

// 批量删除选中的日志
function deleteSelectedLogs() {
    const checkboxes = document.querySelectorAll('.log-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('请先选择要删除的日志记录');
        return;
    }
    if (!confirm(`确定要删除选中的 ${checkboxes.length} 条日志记录吗？`)) {
        return;
    }
    const form = document.getElementById('logsForm');
    form.elements['action'].value = 'bulk_delete';
    form.submit();
}

// 清空当前Token的所有日志
function clearAllLogs() {
    const token = "<?php echo $filter_token ?? ''; ?>";
    if (!token) {
        alert('未指定Token');
        return;
    }
    if (!confirm('确定要清空此Token的所有日志记录吗？此操作不可恢复！')) {
        return;
    }
    const form = document.getElementById('logsForm');
    form.elements['action'].value = 'clear_token_logs';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'token';
    input.value = token;
    form.appendChild(input);
    form.submit();
}
</script>

<?php require_once '../templates/footer.php'; ?>
