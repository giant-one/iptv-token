<?php
session_start();
require_once '../config.php';
require_once 'db_functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 获取搜索参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$expire_filter = isset($_GET['expire_filter']) ? $_GET['expire_filter'] : '';

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取Token总数
$total_tokens = get_tokens_count($search, $expire_filter);

// 获取当前页的Token
$tokens = get_all_tokens($per_page, $offset, $search, $expire_filter);

// 为每个token获取其有权限的播放列表
$tokens_with_playlists = [];
foreach ($tokens as $token) {
    $token['playlists'] = get_token_playlists($token['id']);
    $tokens_with_playlists[] = $token;
}
$tokens = $tokens_with_playlists;

// 获取所有播放列表（用于JavaScript）
$all_playlists = get_all_playlists();

// 包含头部
require_once '../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Token 管理</h2>
    <a href="token_add.php" class="btn btn-success">添加新 Token</a>
</div>

<!-- 搜索表单 -->
<form method="GET" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
        <div>
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">搜索Token:</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="输入Token进行搜索" 
                   style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 250px;">
        </div>
        <div>
            <label for="expire_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">到期时间筛选:</label>
            <select id="expire_filter" name="expire_filter" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 180px;">
                <option value="">全部</option>
                <option value="expired" <?php echo $expire_filter === 'expired' ? 'selected' : ''; ?>>已过期</option>
                <option value="3" <?php echo $expire_filter === '3' ? 'selected' : ''; ?>>3天内到期</option>
                <option value="7" <?php echo $expire_filter === '7' ? 'selected' : ''; ?>>7天内到期</option>
                <option value="15" <?php echo $expire_filter === '15' ? 'selected' : ''; ?>>15天内到期</option>
                <option value="30" <?php echo $expire_filter === '30' ? 'selected' : ''; ?>>30天内到期</option>
                <option value="365" <?php echo $expire_filter === '365' ? 'selected' : ''; ?>>1年内到期</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="margin-right: 10px;">搜索</button>
            <a href="tokens.php" class="btn">清除筛选</a>
        </div>
    </div>
    <?php if (!empty($search) || !empty($expire_filter)): ?>
    <div style="margin-top: 10px; color: #666; font-size: 14px;">
        当前筛选条件: 
        <?php if (!empty($search)): ?>
            Token包含 "<strong><?php echo htmlspecialchars($search); ?></strong>"
        <?php endif; ?>
        <?php if (!empty($expire_filter)): ?>
            <?php if (!empty($search)): ?> + <?php endif; ?>
            <?php
            $filter_text = [
                'expired' => '已过期',
                '3' => '3天内到期',
                '7' => '7天内到期', 
                '15' => '15天内到期',
                '30' => '30天内到期',
                '365' => '1年内到期'
            ];
            echo '<strong>' . $filter_text[$expire_filter] . '</strong>';
            ?>
        <?php endif; ?>
        (共 <?php echo $total_tokens; ?> 条记录)
    </div>
    <?php endif; ?>
</form>

<?php if (count($tokens) > 0): ?>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Token</th>
            <th>过期时间</th>
            <th>剩余天数</th>
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
            <td><?php echo calculate_remaining_days($token['expire_at']); ?></td>
            <td><?php echo $token['usage_count']; ?></td>
            <td><?php echo $token['max_usage'] > 0 ? $token['max_usage'] : '∞'; ?></td>
            <td><?php echo htmlspecialchars($token['note']); ?></td>
            <td><?php echo format_timestamp($token['created_at']); ?></td>
            <td>
                <a href="token_edit.php?id=<?php echo $token['id']; ?>" class="btn btn-primary btn-sm">编辑</a>
                <a href="token_delete.php?id=<?php echo $token['id']; ?>" class="btn btn-danger btn-sm confirm-delete">删除</a>
                <a href="logs.php?token=<?php echo urlencode($token['token']); ?>" class="btn btn-sm">查看日志</a>
                <button onclick="showLinks(<?php echo $token['id']; ?>)" class="btn btn-sm">复制链接</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// 构建分页URL，保持搜索参数
$pagination_params = [];
if (!empty($search)) {
    $pagination_params[] = 'search=' . urlencode($search);
}
if (!empty($expire_filter)) {
    $pagination_params[] = 'expire_filter=' . urlencode($expire_filter);
}
$pagination_query = !empty($pagination_params) ? '&' . implode('&', $pagination_params) : '';
$pagination_url = 'tokens.php?page=%d' . $pagination_query;

echo generate_pagination($total_tokens, $per_page, $page, $pagination_url);
?>

<?php else: ?>
<div class="alert info">
    <p>暂无 Token 数据。<a href="token_add.php">点击此处</a>创建一个新的 Token</p>
</div>
<?php endif; ?>

<!-- 链接弹窗 -->
<div id="linksModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; max-width: 80%; max-height: 80%; overflow-y: auto;">
        <h3>播放列表链接</h3>
        <div id="linksList"></div>
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="closeLinksModal()" class="btn">关闭</button>
            <button onclick="copyAllLinks()" class="btn btn-success">复制全部链接</button>
        </div>
    </div>
</div>

<!-- Toast通知 -->
<div id="toast" style="display: none; position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 12px 24px; border-radius: 4px; z-index: 9999; box-shadow: 0 4px 8px rgba(0,0,0,0.2); font-size: 14px;">
    <span id="toastMessage"></span>
</div>

<div class="usage-guide">
    <h3>使用说明</h3>
    <p>1. Token 访问链接: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/live.php?token=YOUR_TOKEN&p=PLAYLIST_ID&c=CHANNEL</code></p>
    <p>2. 过期时间为空表示永不过期，限制次数为0表示无限制</p>
    <p>3. 参数 p 表示播放列表ID，c 表示渠道信息</p>
    <p>4. 点击"复制链接"可以获取该Token有权限的所有播放列表URL</p>
</div>

<script>
// Token和播放列表数据
const tokensData = <?php echo json_encode($tokens); ?>;
const baseUrl = '<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>';

// 显示链接
function showLinks(tokenId) {
    const token = tokensData.find(t => t.id == tokenId);
    if (!token) return;

    const linksList = document.getElementById('linksList');
    linksList.innerHTML = '';
    document.getElementById('linksModal').setAttribute('data-token-id', tokenId);

    // 使用Token自己的播放列表数据
    const tokenPlaylists = token.playlists || [];

    if (tokenPlaylists.length === 0) {
        linksList.innerHTML = '<p>该Token暂无播放列表权限，请先编辑Token添加播放列表权限</p>';
    } else {
        tokenPlaylists.forEach(playlist => {
            const url = `${baseUrl}/live.php?token=${encodeURIComponent(token.token)}&p=${encodeURIComponent(playlist.id)}&c=${encodeURIComponent(token.channel || '')}`;

            const linkDiv = document.createElement('div');
            linkDiv.style.cssText = 'margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 3px;';
            linkDiv.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 5px;">${playlist.name}</div>
                <div style="background: #f5f5f5; padding: 5px; font-family: monospace; font-size: 12px; word-break: break-all;">${url}</div>
                <button onclick="copyToClipboard('${url.replace(/'/g, "\\'")}')" class="btn btn-sm" style="margin-top: 5px;">复制此链接</button>
            `;
            linksList.appendChild(linkDiv);
        });
    }
    document.getElementById('linksModal').style.display = 'block';
}

function closeLinksModal() {
    document.getElementById('linksModal').style.display = 'none';
}

// Toast
function showToast(message) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMessage').textContent = message;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

//
//  修复复制功能（完全兼容移动端和桌面端）
//
function copyToClipboard(text) {
    // 检测是否为移动设备
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (navigator.clipboard && window.isSecureContext && !isMobile) {
        // 桌面端使用现代API
        navigator.clipboard.writeText(text)
            .then(() => showToast('链接已复制到剪贴板'))
            .catch(() => fallbackCopyText(text));
    } else {
        // 移动端或不支持现代API时使用兼容方案
        fallbackCopyText(text);
    }
}

function fallbackCopyText(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    
    // 移动端兼容性样式设置
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.width = '2em';
    textarea.style.height = '2em';
    textarea.style.padding = '0';
    textarea.style.border = 'none';
    textarea.style.outline = 'none';
    textarea.style.boxShadow = 'none';
    textarea.style.background = 'transparent';
    textarea.style.fontSize = '16px'; // 防止iOS缩放

    document.body.appendChild(textarea);
    
    // 移动端需要这些步骤
    textarea.focus();
    textarea.select();
    
    // iOS设备需要setSelectionRange
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        textarea.setSelectionRange(0, textarea.value.length);
    }

    let success = false;
    try {
        success = document.execCommand('copy');
        if (success) {
            showToast('链接已复制到剪贴板');
        } else {
            throw new Error('execCommand failed');
        }
    } catch (err) {
        // 如果自动复制失败，显示内容让用户手动复制
        showCopyModal(text);
    }

    document.body.removeChild(textarea);
}

// 显示手动复制弹窗（适用于复制失败时）
function showCopyModal(text) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    const content = document.createElement('div');
    content.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 90%;
        max-height: 80%;
        overflow-y: auto;
    `;
    
    content.innerHTML = `
        <h3>请手动复制以下内容：</h3>
        <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">${text}</textarea>
        <div style="text-align: center; margin-top: 15px;">
            <button onclick="this.closest('[style*=position]').remove()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px;">关闭</button>
        </div>
    `;
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    // 点击外部关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// 复制全部链接
function copyAllLinks() {
    const currentTokenId = document.getElementById('linksModal').getAttribute('data-token-id');
    const token = tokensData.find(t => t.id == currentTokenId);

    if (!token) {
        alert('找不到Token数据');
        return;
    }

    const tokenPlaylists = token.playlists || [];

    if (!tokenPlaylists || tokenPlaylists.length === 0) {
        alert('该Token暂无播放列表权限');
        return;
    }

    let expireText = '永不过期';
    if (token.expire_at && token.expire_at > 0) {
        const d = new Date(token.expire_at * 1000);
        expireText =
            `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ` +
            `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    }

    // 使用说明
    let explanation = "💡 为什么提供多个链接？\n";
    explanation += "━".repeat(25) + "\n";
    explanation += "🚀 性能优化：总频道数超过8000+，单一链接加载会很慢\n";
    explanation += "🎯 按需订阅：不同播放列表包含不同类型的频道内容\n";
    explanation += "⚡ 灵活选择：用户可根据需要选择特定的播放列表\n";
    explanation += "🔄 稳定流畅：分散加载，提升观看体验\n";
    explanation += "━".repeat(25) + "\n";
    explanation += "📌 使用建议：根据观看需求选择对应的播放列表，也可以同时订阅所有链接\n\n";

    let header = '━'.repeat(25) + "\n";
    header += `【用户ID: ${token.id}】\n`;
    header += `【到期时间: ${expireText}】\n`;
    header += '━'.repeat(25) + "\n\n";

    // 只生成有权限的播放列表链接
    const list = [];
    tokenPlaylists.forEach(playlist => {
        const url = `${baseUrl}/live.php?token=${encodeURIComponent(token.token)}&p=${encodeURIComponent(playlist.id)}&c=${encodeURIComponent(token.channel || '')}`;
        list.push(`📺 ${playlist.name}\n🔗 ${url}`);
    });

    const output =  header + list.join("\n\n") + "\n\n" + "━".repeat(25) + "\n\n" + explanation;
    copyToClipboard(output);
}

// 点击弹窗外部关闭
document.getElementById('linksModal').addEventListener('click', function(e) {
    if (e.target === this) closeLinksModal();
});
</script>

<?php require_once '../templates/footer.php'; ?>
