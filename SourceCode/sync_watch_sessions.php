<?php
// --- 1. 环境配置 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur'); 

session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(["status" => "error", "message" => "Not logged in"]));
}
$user_id = $_SESSION['user_id'];

// --- 2. Google API 配置 ---
$client_id     = "241745161935-6qlqidb6b5f4vkudrh9f1fgnh95kds1u.apps.googleusercontent.com";
$client_secret = "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"; 

// 获取 Refresh Token
$sql = "SELECT refresh_token FROM google_fit_tokens WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    die(json_encode(["status" => "error", "message" => "Google Fit not linked"]));
}
$refresh_token = $row['refresh_token'];

// --- 3. 刷新 Access Token ---
$ch = curl_init("https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $refresh_token,
    'grant_type'    => 'refresh_token'
]));
$token_data = json_decode(curl_exec($ch), true);
$access_token = $token_data['access_token'] ?? null;
curl_close($ch);

if (!$access_token) {
    die(json_encode(["status" => "error", "message" => "Token refresh failed"]));
}

// --- 4. 定义追溯时间 (过去7天) ---
$startTime = strtotime("-7 days") * 1000;
$endTime   = time() * 1000;

// --- 5. 调用 Sessions API 获取运动列表 ---
$session_url = "https://www.googleapis.com/fitness/v1/users/me/sessions?startTime=$startTime&endTime=$endTime";
$ch = curl_init($session_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res_json = curl_exec($ch);
$session_data = json_decode($res_json, true);
curl_close($ch);

$new_records = 0;

if (isset($session_data['session'])) {
    foreach ($session_data['session'] as $sess) {
        // activityType 9 = Running
        if ($sess['activityType'] == 9) {
            
            $start_dt = date("Y-m-d H:i:s", $sess['startTimeMillis'] / 1000);
            $end_dt   = date("Y-m-d H:i:s", $sess['endTimeMillis'] / 1000);
            $duration = ($sess['endTimeMillis'] - $sess['startTimeMillis']) / 1000;
            
            if ($duration < 60) continue; // 忽略小于1分钟的记录

            // 6. 防重复检查
            $check = $conn->prepare("SELECT run_id FROM run_activities WHERE user_id = ? AND start_time = ?");
            $check->bind_param("is", $user_id, $start_dt);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                
                // 7. 计算与估算
                // 手表同步过来的 Session 有时直接带了距离描述，如果没有则按步频估算
                // 这里我们先进行标准估算：假设跑步速度 9km/h (约 6:40 pace)
                $dist_km = round(($duration / 3600) * 9, 2); 
                $calories = round($dist_km * 60, 1);
                
                // 计算配速 (Pace)
                $pace_min_float = ($duration / 60) / $dist_km;
                $p_min = floor($pace_min_float);
                $p_sec = str_pad(round(($pace_min_float - $p_min) * 60), 2, "0", STR_PAD_LEFT);
                $avg_pace = "$p_min:$p_sec";

                // 8. 插入数据库
                $ins_sql = "INSERT INTO run_activities 
                            (user_id, start_time, end_time, distance_km, duration_seconds, calories_burned, avg_pace_min_km) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $ins_stmt = $conn->prepare($ins_sql);
                $ins_stmt->bind_param("issdids", 
                    $user_id, 
                    $start_dt, 
                    $end_dt, 
                    $dist_km, 
                    $duration, 
                    $calories, 
                    $avg_pace
                );
                
                if ($ins_stmt->execute()) {
                    $new_records++;
                }
            }
        }
    }
}

// 9. 结果返回
header('Content-Type: application/json');
echo json_encode([
    "status" => "success",
    "added" => $new_records,
    "message" => "Synced $new_records watch sessions."
]);

$conn->close();
?>