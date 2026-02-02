<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = false;

// 获取Token ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['flash_message'] = 'Token ID不存在';
    $_SESSION['flash_type'] = 'error';
    header('Location: tokens.php');
    exit;
}

// 获取Token数据
$token_data = get_token_by_id($id);
if (!$token_data) {
    $_SESSION['flash_message'] = 'Token不存在或已被删除';
    $_SESSION['flash_type'] = 'error';
    header('Location: tokens.php');
    exit;
}

// 获取所有播放列表
$playlists = get_all_playlists();

// 获取Token已有的播放列表权限
$token_playlist_ids = get_token_playlist_ids($id);

    // 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $expire_date = $_POST['expire_date'] ?? '';
    $expire_time = $_POST['expire_time'] ?? '';
    $max_usage = isset($_POST['max_usage']) ? (int)$_POST['max_usage'] : 0;
    $note = $_POST['note'] ?? '';
    $channel = $_POST['channel'] ?? '';
    $playlist_ids = $_POST['playlist_ids'] ?? [];
    
    // 验证数据
    if (empty($token)) {
        $error = 'Token 不能为空';
    } elseif ($token !== $token_data['token'] && token_exists($token)) {
        $error = 'Token 已存在，请使用其他值';
    } elseif (empty($channel)) {
        $error = '渠道信息不能为空';
    } elseif (empty($playlist_ids)) {
        $error = '请至少选择一个播放列表';
    }
    
    if (empty($error)) {
        // 处理过期时间
        $expire_at = 0;
        if (!empty($expire_date)) {
            $expire_time = empty($expire_time) ? '23:59:59' : $expire_time;
            $expire_at = strtotime("$expire_date $expire_time");
        }
        
        // 保存到数据库
        $data = [
            'token' => $token,
            'expire_at' => $expire_at,
            'max_usage' => $max_usage,
            'note' => $note,
            'channel' => $channel
        ];
        
        if (update_token($id, $data)) {
            // 更新播放列表权限
            // 先删除旧的权限
            delete_token_playlists($id);

            // 再添加新的权限
            foreach ($playlist_ids as $playlist_id) {
                add_token_playlist($id, (int)$playlist_id);
            }

            $_SESSION['flash_message'] = 'Token 更新成功';
            $_SESSION['flash_type'] = 'success';
            header('Location: tokens.php');
            exit;
        } else {
            $error = '保存失败，请重试';
        }
    }
}

// 准备表单数据
$expire_date = '';
$expire_time = '';
if ($token_data['expire_at'] > 0) {
    $expire_date = date('Y-m-d', $token_data['expire_at']);
    $expire_time = date('H:i', $token_data['expire_at']);
}

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>编辑 Token</h2>
    <a href="tokens.php" class="btn">返回列表</a>
</div>

<?php if ($error): ?>
<div class="alert error">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<form action="" method="POST">
    <div class="form-group">
        <label for="token">Token</label>
        <input type="text" class="form-control" id="token" name="token" value="<?php echo htmlspecialchars($token_data['token']); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="expire_date">过期日期（可选）</label>
        <input type="date" class="form-control" id="expire_date" name="expire_date" value="<?php echo $expire_date; ?>">
    </div>
    
    <div class="form-group">
        <label for="expire_time">过期时间（可选）</label>
        <input type="time" class="form-control" id="expire_time" name="expire_time" value="<?php echo $expire_time; ?>">
    </div>
    
    <div class="form-group">
        <label>快捷设置过期时间</label>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn" onclick="setExpireTime(1, 'hour')">1小时</button>
            <button type="button" class="btn" onclick="setExpireTime(24, 'hour')">1天</button>
            <button type="button" class="btn" onclick="setExpireTime(7, 'day')">1周</button>
            <button type="button" class="btn" onclick="setExpireTime(30, 'day')">1个月</button>
            <button type="button" class="btn" onclick="setExpireTime(90, 'day')">3个月</button>
            <button type="button" class="btn" onclick="setExpireTime(365, 'day')">1年</button>
            <button type="button" class="btn" onclick="setExpireTime(0, 'never')">永不过期</button>
        </div>
    </div>
    
    <div class="form-group">
        <label for="max_usage">最大使用次数（0表示无限制）</label>
        <input type="number" class="form-control" id="max_usage" name="max_usage" min="0" value="<?php echo (int)$token_data['max_usage']; ?>">
    </div>
    
    <div class="form-group">
        <label>已使用次数：<?php echo (int)$token_data['usage_count']; ?></label>
    </div>
    
    <div class="form-group">
        <label for="channel">渠道信息</label>
        <input type="text" class="form-control" id="channel" name="channel" value="<?php echo htmlspecialchars($token_data['channel'] ?? ''); ?>" placeholder="如：咸鱼、小红书等" required>
        <small>表示用户来源的渠道，复制链接时将自动带上此参数</small>
    </div>

    <div class="form-group">
        <label>播放列表权限</label>
        <?php if (count($playlists) > 0): ?>
            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                <label style="margin-bottom: 10px;">
                    <input type="checkbox" id="select_all_playlists" onclick="toggleAllPlaylists(this)"
                        <?php echo count($token_playlist_ids) === count($playlists) ? 'checked' : ''; ?>>
                    <strong>全选/取消全选</strong>
                </label>
                <hr style="margin: 10px 0;">
                <?php foreach ($playlists as $playlist): ?>
                    <label style="display: block; margin-bottom: 8px; padding: 5px; background: #f9f9f9; border-radius: 3px;">
                        <input type="checkbox" name="playlist_ids[]" value="<?php echo $playlist['id']; ?>" class="playlist-checkbox"
                            <?php echo in_array($playlist['id'], $token_playlist_ids) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($playlist['name']); ?>
                        <small style="color: #666; display: block; margin-left: 20px; word-break: break-all;">
                            <?php echo htmlspecialchars($playlist['url']); ?>
                        </small>
                    </label>
                <?php endforeach; ?>
            </div>
            <small>选择该Token可以访问的播放列表</small>
        <?php else: ?>
            <div class="alert info">
                <p>暂无播放列表，请先<a href="playlists.php">创建播放列表</a></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="note">备注（可选）</label>
        <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($token_data['note']); ?></textarea>
    </div>
    
    <div class="form-group">
        <button type="submit" class="btn btn-primary">保存修改</button>
        <a href="tokens.php" class="btn">取消</a>
    </div>
</form>

<div class="usage-guide">
    <h3>使用情况</h3>
    <p>Token: <code><?php echo htmlspecialchars($token_data['token']); ?></code></p>
    <p>创建时间: <?php echo format_timestamp($token_data['created_at']); ?></p>
    <p>最后更新: <?php echo format_timestamp($token_data['updated_at']); ?></p>
    <p>访问链接: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/live.php?token=<?php echo urlencode($token_data['token']); ?>&c=<?php echo urlencode($token_data['channel'] ?? ''); ?></code></p>
    <a href="#" class="btn btn-sm copy-link" data-link="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/live.php?token=<?php echo urlencode($token_data['token']); ?>&c=<?php echo urlencode($token_data['channel'] ?? ''); ?>">复制链接</a>
    <a href="logs.php?token=<?php echo urlencode($token_data['token']); ?>" class="btn btn-sm">查看访问日志</a>
</div>

<script>
function setExpireTime(value, unit) {
    const now = new Date();

    if (unit === 'never') {
        // 清空日期和时间
        document.getElementById('expire_date').value = '';
        document.getElementById('expire_time').value = '';
        return;
    }

    let targetDate = new Date(now);

    // 根据单位计算目标日期
    if (unit === 'hour') {
        targetDate.setHours(now.getHours() + value);
    } else if (unit === 'day') {
        targetDate.setDate(now.getDate() + value);
    }

    // 设置日期
    const yyyy = targetDate.getFullYear();
    const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
    const dd = String(targetDate.getDate()).padStart(2, '0');
    document.getElementById('expire_date').value = `${yyyy}-${mm}-${dd}`;

    // 设置时间
    const hh = String(targetDate.getHours()).padStart(2, '0');
    const mi = String(targetDate.getMinutes()).padStart(2, '0');
    document.getElementById('expire_time').value = `${hh}:${mi}`;
}

function toggleAllPlaylists(checkbox) {
    const checkboxes = document.querySelectorAll('.playlist-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}
</script>

<?php require_once '../templates/footer.php'; ?>
