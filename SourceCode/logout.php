<?php
// 1. 启动 Session，以便我们可以访问它
session_start();

// 2. 清空所有 Session 变量
$_SESSION = array();

// 3. 如果是用 Cookie 存 Session ID 的，连 Cookie 也一起销毁（最安全的做法）
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. 彻底销毁这个会话
session_destroy();

// 5. 跳转回登录页面
header("Location: login.php");
exit();
?>