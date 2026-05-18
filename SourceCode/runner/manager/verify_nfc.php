<?php
require_once '../auth_check.php';
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'event_manager') { 
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit; 
}

$tag_data = isset($_POST['tag_data']) ? trim($_POST['tag_data']) : ''; 
$event_id = intval($_POST['event_id'] ?? 0);
$user_id  = intval($_POST['user_id'] ?? 0);
$mode     = $_POST['mode'] ?? 'checkin'; // 获取前端模式

if (empty($tag_data) || $user_id === 0 || $event_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit;
}

// 1. 获取选手当前状态
$p_stmt = $conn->prepare("SELECT status FROM participations WHERE user_id = ? AND event_id = ?");
$p_stmt->bind_param("ii", $user_id, $event_id);
$p_stmt->execute();
$participation = $p_stmt->get_result()->fetch_assoc();

if (!$participation) {
    echo json_encode(['status' => 'error', 'message' => 'Runner not registered.']);
    exit;
}

$current_status = trim($participation['status']);
$valid_tag = (strpos($tag_data, "PRT_USER_") !== false);

if ($valid_tag) {

    // --- 逻辑 A：注册/领卡模式 (Check-in) ---
    if ($mode === 'checkin') {
        // 在注册模式扫码，状态永远只更新为 ready，不会触发完赛
        if ($current_status === 'completed' || $current_status === 'verified') {
            echo json_encode(['status' => 'error', 'message' => 'Runner has already finished.']);
            exit;
        }

        $update_sql = "UPDATE participations SET 
                        status = 'ready', 
                        nfc_verified_at = NOW(), 
                        chip_id = ? 
                       WHERE user_id = ? AND event_id = ?";
        $u_stmt = $conn->prepare($update_sql);
        $u_stmt->bind_param("sii", $tag_data, $user_id, $event_id);
        $success_msg = "✅ Tag Linked! Status: READY";
    } 

    // --- 逻辑 B：终点线模式 (Finish Line) ---
    else if ($mode === 'finish') {
        // 只有状态是 ready (已领卡), started (跑步中) 才能完赛
        if (in_array($current_status, ['ready', 'started', 'at_checkpoint'])) {
            $update_sql = "UPDATE participations SET 
                            status = 'completed', 
                            finish_time = NOW() 
                           WHERE user_id = ? AND event_id = ?";
            $u_stmt = $conn->prepare($update_sql);
            $u_stmt->bind_param("ii", $user_id, $event_id);
            $success_msg = "🏁 FINISH recorded! Status: COMPLETED";
        } else if ($current_status === 'completed' || $current_status === 'verified') {
            echo json_encode(['status' => 'error', 'message' => 'Runner already finished.']);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Runner must be READY to finish.']);
            exit;
        }
    }

    if ($u_stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => $success_msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Sync Failed.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Tag Format']);
}