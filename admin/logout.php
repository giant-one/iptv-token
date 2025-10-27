<?php
session_start();

// 清除所有会话数据
$_SESSION = array();

// 如果使用了会话 Cookie，则清除它
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 重定向回登录页
header('Location: login.php');
exit;
?>
