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

// 获取所有播放列表
$playlists = get_all_playlists();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $expire_date = $_POST['expire_date'] ?? '';
    $expire_time = $_POST['expire_time'] ?? '';
    $max_usage = isset($_POST['max_usage']) ? (int)$_POST['max_usage'] : 0;
    $max_ip_per_day = isset($_POST['max_ip_per_day']) ? (int)$_POST['max_ip_per_day'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $note = $_POST['note'] ?? '';
    $channel = $_POST['channel'] ?? '';
    $playlist_ids = $_POST['playlist_ids'] ?? [];
    
    // 验证数据
    if (empty($token) && !isset($_POST['auto_generate'])) {
        $error = 'Token 不能为空';
    } elseif (isset($_POST['auto_generate'])) {
        // 自动生成 Token
        $token = generate_unique_token();
    } elseif (token_exists($token)) {
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
            'max_ip_per_day' => $max_ip_per_day,
            'status' => $status,
            'note' => $note,
            'channel' => $channel
        ];
        
        if (create_token($data)) {
            // 获取刚创建的Token ID
            $db = get_db_connection();
            $token_id = $db->lastInsertId();

            // 添加播放列表权限
            foreach ($playlist_ids as $playlist_id) {
                add_token_playlist($token_id, (int)$playlist_id);
            }

            $success = true;
            $_SESSION['flash_message'] = 'Token 创建成功';
            $_SESSION['flash_type'] = 'success';
            header('Location: tokens.php');
            exit;
        } else {
            $error = '保存失败，请重试';
        }
    }
}

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>添加新 Token</h2>
    <a href="tokens.php" class="btn">返回列表</a>
</div>

<?php if ($error): ?>
<div class="alert error">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert success">
    Token 创建成功！<a href="tokens.php">返回列表</a>
</div>
<?php else: ?>
<form action="" method="POST">
    <div class="form-group">
        <label for="token">Token</label>
        <input type="text" class="form-control" id="token" name="token" value="<?php echo isset($_POST['token']) ? htmlspecialchars($_POST['token']) : ''; ?>" <?php echo isset($_POST['auto_generate']) ? 'disabled' : ''; ?>>
        <div style="margin-top: 5px;">
            <label>
                <input type="checkbox" name="auto_generate" id="auto_generate" <?php echo isset($_POST['auto_generate']) ? 'checked' : ''; ?> onchange="document.getElementById('token').disabled = this.checked;">
                自动生成 Token
            </label>
        </div>
    </div>
    
    <div class="form-group">
        <label for="expire_date">过期日期（可选）</label>
        <input type="date" class="form-control" id="expire_date" name="expire_date" value="<?php echo isset($_POST['expire_date']) ? htmlspecialchars($_POST['expire_date']) : ''; ?>">
    </div>
    
    <div class="form-group">
        <label for="expire_time">过期时间（可选）</label>
        <input type="time" class="form-control" id="expire_time" name="expire_time" value="<?php echo isset($_POST['expire_time']) ? htmlspecialchars($_POST['expire_time']) : ''; ?>">
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
        <input type="number" class="form-control" id="max_usage" name="max_usage" min="0" value="<?php echo isset($_POST['max_usage']) ? (int)$_POST['max_usage'] : 0; ?>">
    </div>

    <div class="form-group">
        <label for="max_ip_per_day">每天最大IP数（0表示无限制）</label>
        <input type="number" class="form-control" id="max_ip_per_day" name="max_ip_per_day" min="0" value="<?php echo isset($_POST['max_ip_per_day']) ? (int)$_POST['max_ip_per_day'] : 5; ?>">
        <small>每天允许不同的IP地址访问此Token的数量，超过后当天将拒绝访问</small>
    </div>

    <div class="form-group">
        <label for="channel">渠道信息</label>
        <input type="text" class="form-control" id="channel" name="channel" value="<?php echo isset($_POST['channel']) ? htmlspecialchars($_POST['channel']) : ''; ?>" placeholder="如：咸鱼、小红书等" required>
        <small>表示用户来源的渠道，复制链接时将自动带上此参数</small>
    </div>

    <div class="form-group">
        <label>状态</label>
        <div style="display: flex; gap: 20px;">
            <label style="font-weight: normal;">
                <input type="radio" name="status" value="1" checked> 有效
            </label>
            <label style="font-weight: normal;">
                <input type="radio" name="status" value="0"> 无效
            </label>
        </div>
        <small>设置为无效后，该Token将无法访问播放列表</small>
    </div>

    <div class="form-group">
        <label>播放列表权限</label>
        <?php if (count($playlists) > 0): ?>
            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                <label style="margin-bottom: 10px;">
                    <input type="checkbox" id="select_all_playlists" onclick="toggleAllPlaylists(this)">
                    <strong>全选/取消全选</strong>
                </label>
                <hr style="margin: 10px 0;">
                <?php foreach ($playlists as $playlist): ?>
                    <label style="display: block; margin-bottom: 8px; padding: 5px; background: #f9f9f9; border-radius: 3px;">
                        <input type="checkbox" name="playlist_ids[]" value="<?php echo $playlist['id']; ?>" class="playlist-checkbox"
                            <?php echo (isset($_POST['playlist_ids']) && in_array($playlist['id'], $_POST['playlist_ids'])) ? 'checked' : ''; ?>>
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
        <textarea class="form-control" id="note" name="note" rows="3"><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note']) : ''; ?></textarea>
    </div>
    
    <div class="form-group">
        <button type="submit" class="btn btn-success">创建 Token</button>
        <a href="tokens.php" class="btn">取消</a>
    </div>
</form>
<?php endif; ?>

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
