<?php
// 检查是否已登录（在需要登录的页面使用）
function check_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

// 当前页面路径（用于导航高亮）
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Token 管理系统</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>IPTV Token 管理系统</h1>
        </header>
        
        <?php if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
        <nav>
            <ul>
                <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">首页</a></li>
                <li><a href="tokens.php" class="<?php echo $current_page == 'tokens.php' ? 'active' : ''; ?>">Token 管理</a></li>
                <li><a href="playlists.php" class="<?php echo $current_page == 'playlists.php' ? 'active' : ''; ?>">播放列表</a></li>
                <li><a href="logs.php" class="<?php echo $current_page == 'logs.php' ? 'active' : ''; ?>">访问日志</a></li>
                <li><a href="logout.php">退出登录</a></li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <main>
            <?php if(isset($_SESSION['flash_message'])): ?>
                <div class="alert <?php echo isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info'; ?>">
                    <?php 
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                    ?>
                </div>
            <?php endif; ?>
