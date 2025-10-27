<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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

// 处理删除确认
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    if (delete_token($id)) {
        $_SESSION['flash_message'] = 'Token 删除成功';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = '删除失败，请重试';
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: tokens.php');
    exit;
}

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>删除 Token</h2>
    <a href="tokens.php" class="btn">返回列表</a>
</div>

<div class="alert error">
    <p>您确定要删除此 Token 吗？此操作不可撤销。</p>
</div>

<div class="usage-guide">
    <h3>Token 信息</h3>
    <p><strong>Token:</strong> <?php echo htmlspecialchars($token_data['token']); ?></p>
    <p><strong>创建时间:</strong> <?php echo format_timestamp($token_data['created_at']); ?></p>
    <p><strong>过期时间:</strong> <?php echo format_timestamp($token_data['expire_at']); ?></p>
    <p><strong>使用次数:</strong> <?php echo $token_data['usage_count']; ?> / <?php echo $token_data['max_usage'] > 0 ? $token_data['max_usage'] : '∞'; ?></p>
    <p><strong>备注:</strong> <?php echo htmlspecialchars($token_data['note']); ?></p>
</div>

<form action="" method="POST">
    <input type="hidden" name="confirm_delete" value="yes">
    
    <div class="form-group">
        <button type="submit" class="btn btn-danger">确认删除</button>
        <a href="tokens.php" class="btn">取消</a>
    </div>
</form>

<?php require_once '../templates/footer.php'; ?>
