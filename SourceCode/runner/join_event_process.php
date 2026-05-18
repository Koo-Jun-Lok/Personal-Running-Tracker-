<?php
require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 2. 处理加入逻辑 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $user_id = $_SESSION['user_id'];
    $event_id = intval($_POST['event_id']);

    // --- 3. 检查是否已经参加过 (防止刷新页面重复插入) ---
    $check_sql = "SELECT participation_id FROM participations WHERE user_id = ? AND event_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 已经参加过了，直接跳转到我的挑战页面
        header("Location: my_events.php");
        exit();
    }
    $stmt->close();

    // --- 4. 插入记录 ---
    // status 设为 'joined'，joined_at 设为当前时间
    $insert_sql = "INSERT INTO participations (user_id, event_id, status, joined_at) VALUES (?, ?, 'joined', NOW())";
    
    if ($stmt = $conn->prepare($insert_sql)) {
        $stmt->bind_param("ii", $user_id, $event_id);
        
        if ($stmt->execute()) {
            // 成功加入，跳转到 my_events.php
            header("Location: my_events.php?msg=joined_success");
            exit();
        } else {
            echo "Database Error: " . $conn->error;
        }
        $stmt->close();
    }
} else {
    // 如果不是 POST 访问，退回到活动列表
    header("Location: events.php");
}

$conn->close();
?>