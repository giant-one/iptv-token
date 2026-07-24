<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理添加接入方
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $playlist_ids = isset($_POST['default_playlist_ids']) ? array_map('intval', (array)$_POST['default_playlist_ids']) : [];

    if (empty($name)) {
        $_SESSION['flash_message'] = '接入方名称不能为空';
        $_SESSION['flash_type'] = 'error';
    } else {
        $data = [
            'name' => $name,
            'status' => $status,
            'default_playlist_ids' => empty($playlist_ids) ? null : json_encode($playlist_ids),
        ];
        $cred = create_api_app($data);
        if ($cred) {
            // 一次性展示明文 secret
            $_SESSION['new_app_credentials'] = $cred;
            $_SESSION['flash_message'] = '接入方创建成功，请立即保存 App Secret（仅显示一次）';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = '接入方创建失败';
            $_SESSION['flash_type'] = 'error';
        }
    }
    header('Location: api_apps.php');
    exit;
}

// 处理编辑接入方
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $playlist_ids = isset($_POST['default_playlist_ids']) ? array_map('intval', (array)$_POST['default_playlist_ids']) : [];

    if (empty($name)) {
        $_SESSION['flash_message'] = '接入方名称不能为空';
        $_SESSION['flash_type'] = 'error';
    } else {
        $data = [
            'name' => $name,
            'status' => $status,
            'default_playlist_ids' => empty($playlist_ids) ? null : json_encode($playlist_ids),
        ];
        if (update_api_app($id, $data)) {
            $_SESSION['flash_message'] = '接入方更新成功';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = '接入方更新失败';
            $_SESSION['flash_type'] = 'error';
        }
    }
    header('Location: api_apps.php');
    exit;
}

// 处理重置 secret
if ($_GET && isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $app = get_api_app_by_id($id);
    $new_secret = reset_api_app_secret($id);
    if ($new_secret && $app) {
        $_SESSION['new_app_credentials'] = ['app_id' => $app['app_id'], 'app_secret' => $new_secret];
        $_SESSION['flash_message'] = 'App Secret 已重置，请立即保存新的 Secret（仅显示一次）';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = '重置失败';
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: api_apps.php');
    exit;
}

// 处理删除接入方
if ($_GET && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (delete_api_app($id)) {
        $_SESSION['flash_message'] = '接入方删除成功';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = '接入方删除失败';
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: api_apps.php');
    exit;
}

// 取出一次性展示的凭证
$new_cred = null;
if (isset($_SESSION['new_app_credentials'])) {
    $new_cred = $_SESSION['new_app_credentials'];
    unset($_SESSION['new_app_credentials']);
}

$apps = get_all_api_apps();
$all_playlists = get_all_playlists();

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>开放平台接入方</h2>
    <button onclick="showAddForm()" class="btn btn-success">新增接入方</button>
</div>

<?php if ($new_cred): ?>
<div class="alert success" style="word-break: break-all;">
    <p><strong>请立即保存以下凭证，App Secret 仅显示这一次：</strong></p>
    <p>App ID：<code><?php echo htmlspecialchars($new_cred['app_id']); ?></code></p>
    <p>App Secret：<code><?php echo htmlspecialchars($new_cred['app_secret']); ?></code></p>
</div>
<?php endif; ?>

<!-- 新增接入方表单 -->
<div id="addForm" style="display: none; background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
    <h3>新增接入方</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div style="margin-bottom: 15px;">
            <label for="name">接入方名称:</label>
            <input type="text" id="name" name="name" required style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="status">状态:</label>
            <select id="status" name="status" style="width: 100%; padding: 8px; margin-top: 5px;">
                <option value="1">启用</option>
                <option value="0">停用</option>
            </select>
        </div>
        <div style="margin-bottom: 15px;">
            <label>默认播放列表白名单（不勾选表示不限制）:</label>
            <div style="margin-top: 5px;">
                <?php foreach ($all_playlists as $pl): ?>
                    <label style="display: inline-block; margin-right: 15px; font-weight: normal;">
                        <input type="checkbox" name="default_playlist_ids[]" value="<?php echo $pl['id']; ?>">
                        <?php echo htmlspecialchars($pl['name']); ?> (ID: <?php echo $pl['id']; ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <button type="submit" class="btn btn-success">创建</button>
            <button type="button" onclick="hideAddForm()" class="btn">取消</button>
        </div>
    </form>
</div>

<?php if (count($apps) > 0): ?>
<table>
    <thead>
        <tr>
            <th style="width: 60px;">ID</th>
            <th style="width: 150px;">名称</th>
            <th style="min-width: 180px;">App ID</th>
            <th style="width: 80px;">状态</th>
            <th style="min-width: 120px;">播放列表白名单</th>
            <th style="width: 150px;">创建时间</th>
            <th style="width: 220px;">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($apps as $app): ?>
        <?php
            $wl = !empty($app['default_playlist_ids']) ? json_decode($app['default_playlist_ids'], true) : [];
            $wl_text = empty($wl) ? '不限制' : implode(', ', $wl);
        ?>
        <tr>
            <td><?php echo $app['id']; ?></td>
            <td><?php echo htmlspecialchars($app['name']); ?></td>
            <td style="word-break: break-all;"><code><?php echo htmlspecialchars($app['app_id']); ?></code></td>
            <td>
                <?php if ((int)$app['status'] === 1): ?>
                    <span style="color: #27ae60;">启用</span>
                <?php else: ?>
                    <span style="color: #e74c3c;">停用</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($wl_text); ?></td>
            <td><?php echo format_timestamp($app['created_at']); ?></td>
            <td>
                <button onclick='showEditForm(<?php echo json_encode([
                    'id' => (int)$app['id'],
                    'name' => $app['name'],
                    'status' => (int)$app['status'],
                    'wl' => array_map('intval', $wl),
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn btn-primary btn-sm">编辑</button>
                <a href="api_apps.php?action=reset&id=<?php echo $app['id']; ?>" class="btn btn-sm" onclick="return confirm('确定要重置该接入方的 App Secret 吗？旧 Secret 将立即失效。')">重置密钥</a>
                <a href="api_apps.php?action=delete&id=<?php echo $app['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除这个接入方吗？')">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="alert info">
    <p>暂无接入方。点击上方按钮创建一个新的接入方。</p>
</div>
<?php endif; ?>

<!-- 编辑接入方表单 -->
<div id="editForm" style="display: none; background: #f5f5f5; padding: 20px; border-radius: 5px; margin-top: 20px;">
    <h3>编辑接入方</h3>
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" id="edit_id" name="id">
        <div style="margin-bottom: 15px;">
            <label for="edit_name">接入方名称:</label>
            <input type="text" id="edit_name" name="name" required style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="edit_status">状态:</label>
            <select id="edit_status" name="status" style="width: 100%; padding: 8px; margin-top: 5px;">
                <option value="1">启用</option>
                <option value="0">停用</option>
            </select>
        </div>
        <div style="margin-bottom: 15px;">
            <label>默认播放列表白名单（不勾选表示不限制）:</label>
            <div style="margin-top: 5px;">
                <?php foreach ($all_playlists as $pl): ?>
                    <label style="display: inline-block; margin-right: 15px; font-weight: normal;">
                        <input type="checkbox" class="edit_pl" name="default_playlist_ids[]" value="<?php echo $pl['id']; ?>">
                        <?php echo htmlspecialchars($pl['name']); ?> (ID: <?php echo $pl['id']; ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <button type="submit" class="btn btn-success">更新</button>
            <button type="button" onclick="hideEditForm()" class="btn">取消</button>
        </div>
    </form>
</div>

<div class="usage-guide">
    <h3>使用说明</h3>
    <p>1. 每个接入方拥有独立的 App ID 与 App Secret，用于开放平台接口鉴权与签名。</p>
    <p>2. App Secret 仅在创建或重置时明文显示一次，请妥善保存。</p>
    <p>3. 停用的接入方将无法调用开放接口。</p>
    <p>4. 播放列表白名单：勾选后，该接入方只能为白名单内的播放列表创建 Token；不勾选表示不限制。</p>
    <p>5. 接口地址与签名规则详见 <code>docs/OPEN_API.md</code>。</p>
</div>

<script>
function showAddForm() {
    document.getElementById('addForm').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
}
function hideAddForm() {
    document.getElementById('addForm').style.display = 'none';
}
function showEditForm(app) {
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('addForm').style.display = 'none';
    document.getElementById('edit_id').value = app.id;
    document.getElementById('edit_name').value = app.name;
    document.getElementById('edit_status').value = app.status;
    var wl = app.wl || [];
    document.querySelectorAll('.edit_pl').forEach(function (cb) {
        cb.checked = wl.indexOf(parseInt(cb.value, 10)) !== -1;
    });
}
function hideEditForm() {
    document.getElementById('editForm').style.display = 'none';
}
</script>

<?php require_once '../templates/footer.php'; ?>
