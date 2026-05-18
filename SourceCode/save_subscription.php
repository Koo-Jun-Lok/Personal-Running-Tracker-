<?php
// save_subscription.php
session_start();
require_once 'db_connect.php';

// 开启错误提示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 确保这是一个 POST 请求且用户已登录
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// 获取前端传过来的 JSON 数据
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (isset($data['endpoint'])) {
    $endpoint = $data['endpoint'];
    $p256dh = $data['keys']['p256dh'] ?? '';
    $auth = $data['keys']['auth'] ?? '';

    // 使用 INSERT ... ON DUPLICATE KEY UPDATE
    // 如果这个用户之前存过钥匙，就更新它；如果是第一次存，就插入新记录
    $sql = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE endpoint=?, p256dh=?, auth=?";
            
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("issssss", $user_id, $endpoint, $p256dh, $auth, $endpoint, $p256dh, $auth);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Subscription saved"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid subscription data"]);
}
?>