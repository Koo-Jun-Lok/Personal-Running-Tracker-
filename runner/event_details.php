<?php
// --- 1. Setup ---
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id'])) {
    header("Location: events.php");
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// --- 2. Fetch Event Info ---
$sql = "SELECT * FROM events WHERE event_id = ? AND status = 'active'";
$evt = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
}

if (!$evt) die("Event not found or not active.");

$is_virtual = ($evt['event_type'] === 'virtual');

// --- 3. Check Participation & Progress ---
$participation = null;
$current_km = 0.00;

if ($is_virtual) {
    // Virtual 逻辑：从 run_activities 累加
    $sql_p = "SELECT p.status, p.joined_at, 
              (SELECT COALESCE(SUM(distance_km), 0) FROM run_activities 
               WHERE user_id = p.user_id AND start_time >= p.joined_at AND start_time >= ?) as real_km
              FROM participations p WHERE p.user_id = ? AND p.event_id = ?";
    if ($stmt = $conn->prepare($sql_p)) {
        $stmt->bind_param("sii", $evt['event_date'], $user_id, $event_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) { $participation = $res; $current_km = (float)$res['real_km']; }
    }
} else {
    // Physical 逻辑：读取静态值
    $sql_p = "SELECT status, current_km FROM participations WHERE user_id = ? AND event_id = ?";
    if ($stmt = $conn->prepare($sql_p)) {
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $participation = $stmt->get_result()->fetch_assoc();
        if ($participation) { $current_km = (float)$participation['current_km']; }
    }
}

$target_km = (float)$evt['target_distance_km'];
$progress_percent = ($target_km > 0) ? min(100, ($current_km / $target_km) * 100) : 0;

// --- 4. 智能识别返回路径 ---
$back_url = 'events.php'; // 默认返回 Explore 页面
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    // 如果来源地址包含 my_events.php，则返回路径设为 my_events.php
    if (strpos($referer, 'my_events.php') !== false) {
        $back_url = 'my_events.php';
    }
}

// --- 5. 分发视图 ---
if ($is_virtual) {
    include 'view_virtual.php';
} else {
    include 'view_physical.php';
}