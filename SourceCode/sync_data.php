<?php
// 1. 环境配置
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur'); 

session_start();
include('db_connect.php');
require_once 'config_api.php'; // 👈 引入安全配置

// 2. 配置 Google API 信息 (从 config_api.php 安全读取)
$client_id     = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET; 

// 3. 获取当前用户 ID
$user_id = $_SESSION['user_id'] ?? 1; 

// 查询该用户的 Refresh Token
$sql = "SELECT refresh_token FROM google_fit_tokens WHERE user_id = ?";
$stmt_token = $conn->prepare($sql);
$stmt_token->bind_param("i", $user_id);
$stmt_token->execute();
$res_token = $stmt_token->get_result();
$row = $res_token->fetch_assoc();

if (!$row) {
    die(json_encode(["status" => "error", "message" => "User not linked. Please link your Google Fit first."]));
}
$refresh_token = $row['refresh_token'];

// 4. 刷新 Access Token
$ch = curl_init("https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $refresh_token,
    'grant_type'    => 'refresh_token'
]));
$response = curl_exec($ch);
$token_data = json_decode($response, true);
curl_close($ch);

$new_access_token = $token_data['access_token'] ?? null;

if (!$new_access_token) {
    header('Content-Type: application/json');
    die(json_encode([
        "status" => "error", 
        "message" => "Token refresh failed",
        "details" => $token_data['error'] ?? 'Unknown Google Error',
        "advice" => "Your token might be expired. Please re-authorize Google Fit in your profile settings."
    ]));
}

// 5. 定义查询时间范围 (从今日 00:00:00 到 此时此刻)
$startTimeMillis = strtotime("today 00:00:00") * 1000;
$endTimeMillis   = time() * 1000; 

// 6. 构造请求体：移除 dataSourceId，让 Google 自动合并所有设备的数据
$query = [
    "aggregateBy" => [[
        "dataTypeName" => "com.google.step_count.delta"
    ]],
    "bucketByTime" => ["durationMillis" => 86400000], 
    "startTimeMillis" => $startTimeMillis,
    "endTimeMillis" => $endTimeMillis
];

// 7. 发起 API 请求
$ch = curl_init("https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $new_access_token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));

$response_json = curl_exec($ch);
$fit_data = json_decode($response_json, true);
curl_close($ch);

// 8. 解析步数：采用累加逻辑
$steps = 0;
if (isset($fit_data['bucket'])) {
    foreach ($fit_data['bucket'] as $bucket) {
        if (isset($bucket['dataset'][0]['point'])) {
            foreach ($bucket['dataset'][0]['point'] as $point) {
                if (isset($point['value'][0]['intVal'])) {
                    $steps += $point['value'][0]['intVal'];
                }
            }
        }
    }
}

// 9. 处理并同步到数据库
$current_date = date("Y-m-d");
$distance_km = round($steps * 0.00075, 2); 
$calories    = round($steps * 0.04, 2);   

$sync_sql = "INSERT INTO daily_summaries (user_id, summary_date, total_steps, total_km, calories_burned, last_sync) 
             VALUES (?, ?, ?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE 
             total_steps = VALUES(total_steps), 
             total_km = VALUES(total_km), 
             calories_burned = VALUES(calories_burned),
             last_sync = NOW()";

$stmt = $conn->prepare($sync_sql);
$stmt->bind_param("isidd", $user_id, $current_date, $steps, $distance_km, $calories);

// 10. 输出结果
header('Content-Type: application/json');
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "date" => $current_date,
        "steps" => $steps,
        "km" => $distance_km,
        "kcal" => $calories,
        "last_sync" => date("Y-m-d H:i:s")
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>