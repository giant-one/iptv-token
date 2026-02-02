<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理添加播放列表
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);

    if (empty($name) || empty($url)) {
        $_SESSION['flash_message'] = '播放列表名称和播放地址不能为空';
        $_SESSION['flash_type'] = 'error';
    } else {
        $data = [
            'name' => $name,
            'url' => $url
        ];

        if (create_playlist($data)) {
            $_SESSION['flash_message'] = '播放列表创建成功';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = '播放列表创建失败';
            $_SESSION['flash_type'] = 'error';
        }
    }

    header('Location: playlists.php');
    exit;
}

// 处理编辑播放列表
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);

    if (empty($name) || empty($url)) {
        $_SESSION['flash_message'] = '播放列表名称和播放地址不能为空';
        $_SESSION['flash_type'] = 'error';
    } else {
        $data = [
            'name' => $name,
            'url' => $url
        ];

        if (update_playlist($id, $data)) {
            $_SESSION['flash_message'] = '播放列表更新成功';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = '播放列表更新失败';
            $_SESSION['flash_type'] = 'error';
        }
    }

    header('Location: playlists.php');
    exit;
}

// 处理删除播放列表
if ($_GET && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if (delete_playlist($id)) {
        $_SESSION['flash_message'] = '播放列表删除成功';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = '播放列表删除失败';
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: playlists.php');
    exit;
}

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取播放列表总数
$total_playlists = get_playlists_count();

// 获取当前页的播放列表
$playlists = get_all_playlists($per_page, $offset);

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>播放列表管理</h2>
    <button onclick="showAddForm()" class="btn btn-success">添加新播放列表</button>
</div>

<!-- 添加播放列表表单 -->
<div id="addForm" style="display: none; background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
    <h3>添加播放列表</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div style="margin-bottom: 15px;">
            <label for="name">播放列表名称:</label>
            <input type="text" id="name" name="name" required style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="url">播放地址:</label>
            <input type="url" id="url" name="url" required style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="https://example.com/path/playlist.m3u">
            <small style="color: #666;">请输入完整的播放地址URL</small>
        </div>
        <div>
            <button type="submit" class="btn btn-success">创建</button>
            <button type="button" onclick="hideAddForm()" class="btn">取消</button>
        </div>
    </form>
</div>

<?php if (count($playlists) > 0): ?>
<table>
    <thead>
        <tr>
            <th style="width: 60px;">ID</th>
            <th style="width: 150px;">播放列表名称</th>
            <th style="min-width: 200px;">播放地址</th>
            <th style="width: 150px;">创建时间</th>
            <th style="width: 150px;">更新时间</th>
            <th style="width: 120px;">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($playlists as $playlist): ?>
        <tr>
            <td><?php echo $playlist['id']; ?></td>
            <td><?php echo htmlspecialchars($playlist['name']); ?></td>
            <td style="word-break: break-all; max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                <a href="<?php echo htmlspecialchars($playlist['url']); ?>" target="_blank" style="color: #007bff; text-decoration: none;" title="<?php echo htmlspecialchars($playlist['url']); ?>">
                    <?php echo htmlspecialchars($playlist['url']); ?>
                </a>
            </td>
            <td><?php echo format_timestamp($playlist['created_at']); ?></td>
            <td><?php echo format_timestamp($playlist['updated_at']); ?></td>
            <td>
                <button onclick="showEditForm(<?php echo $playlist['id']; ?>, '<?php echo htmlspecialchars($playlist['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($playlist['url'], ENT_QUOTES); ?>')" class="btn btn-primary btn-sm">编辑</button>
                <a href="playlists.php?action=delete&id=<?php echo $playlist['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除这个播放列表吗？')">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// 生成分页
echo generate_pagination($total_playlists, $per_page, $page, 'playlists.php?page=%d');
?>

<?php else: ?>
<div class="alert info">
    <p>暂无播放列表数据。点击上方按钮创建一个新的播放列表。</p>
</div>
<?php endif; ?>

<!-- 编辑播放列表表单 -->
<div id="editForm" style="display: none; background: #f5f5f5; padding: 20px; border-radius: 5px; margin-top: 20px;">
    <h3>编辑播放列表</h3>
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" id="edit_id" name="id">
        <div style="margin-bottom: 15px;">
            <label for="edit_name">播放列表名称:</label>
            <input type="text" id="edit_name" name="name" required style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="edit_url">播放地址:</label>
            <input type="url" id="edit_url" name="url" required style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="https://example.com/path/playlist.m3u">
            <small style="color: #666;">请输入完整的播放地址URL</small>
        </div>
        <div>
            <button type="submit" class="btn btn-success">更新</button>
            <button type="button" onclick="hideEditForm()" class="btn">取消</button>
        </div>
    </form>
</div>

<div class="usage-guide">
    <h3>使用说明</h3>
    <p>1. 播放列表用于组织和管理不同的直播源</p>
    <p>2. 播放列表名称：用于显示的中文名称</p>
    <p>3. 播放地址：完整的播放列表URL地址（例如：https://example.com/path/playlist.m3u）</p>
    <p>4. 在创建Token时可以指定该Token拥有哪些播放列表的访问权限</p>
</div>

<script>
function showAddForm() {
    document.getElementById('addForm').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
}

function hideAddForm() {
    document.getElementById('addForm').style.display = 'none';
}

function showEditForm(id, name, url) {
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('addForm').style.display = 'none';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_url').value = url;
}

function hideEditForm() {
    document.getElementById('editForm').style.display = 'none';
}
</script>

<?php require_once '../templates/footer.php'; ?>
