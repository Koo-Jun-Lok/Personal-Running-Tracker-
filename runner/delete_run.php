<?php

require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);



// --- 2. 获取参数 ---

if (isset($_GET['id'])) {

    $run_id = intval($_GET['id']); // 确保是整数，防止注入

    $user_id = $_SESSION['user_id'];



    // --- 3. 执行删除 ---

    // ⚠️ 关键安全检查：SQL 必须包含 AND user_id = ? 

    // 这样防止用户通过修改 URL 里的 ID 来删除别人的跑步记录

    $sql = "DELETE FROM run_activities WHERE run_id = ? AND user_id = ?";



    if ($stmt = $conn->prepare($sql)) {

        $stmt->bind_param("ii", $run_id, $user_id);

        

        if ($stmt->execute()) {

            // 删除成功，跳回历史记录页

            header("Location: history.php");

            exit();

        } else {

            echo "Error deleting record: " . $conn->error;

        }

        $stmt->close();

    }

} else {

    // 如果没有 ID 参数，直接回首页

    header("Location: home.php");

    exit();

}

?>