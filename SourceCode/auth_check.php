<?php
session_start();

$session_lifetime = 900; // 15 Minutes

// 1. 检查用户是否根本没登录
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // 假设你在子目录，所以用 ../
    exit();
}

// 2. 检查 Session 是否由于太久没操作而过期
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_lifetime)) {
    // 超过 15 分钟没刷新过页面
    session_unset();
    session_destroy();
    header("Location: ../login.php?reason=timeout");
    exit();
}

// 3. 核心：只要用户访问此页面，就更新最后活动时间，顺延 15 分钟
$_SESSION['last_activity'] = time();
?>