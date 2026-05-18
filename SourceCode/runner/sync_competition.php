<?php
require_once '../auth_check.php';
require_once '../db_connect.php';

// 设置返回格式为 JSON
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// 确保参数存在
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 0;
$dist = isset($_GET['dist']) ? floatval($_GET['dist']) : 0;

if ($event_id === 0) {
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

// 1. 更新当前用户的最新位置、距离和时间戳
// 这里的 updated_at 会因为 SQL 的 ON UPDATE CURRENT_TIMESTAMP 自动更新
$update_sql = "UPDATE participations SET last_lat = ?, last_lng = ?, current_km = ? 
               WHERE user_id = ? AND event_id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("dddii", $lat, $lng, $dist, $user_id, $event_id);
$stmt->execute();

// 2. 获取该赛事中其他活跃用户
// 状态包含 joined, ready, started 以便测试。排除自己，且只取最近 3 分钟更新过的用户
$competitors_sql = "SELECT p.user_id, u.username, p.last_lat, p.last_lng, p.current_km 
                    FROM participations p 
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.event_id = ? 
                    AND p.user_id != ? 
                    AND p.status IN ('joined', 'ready', 'started') 
                    AND p.updated_at >= NOW() - INTERVAL 3 MINUTE";
$stmt = $conn->prepare($competitors_sql);
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$competitors = [];
while ($row = $result->fetch_assoc()) {
    $competitors[] = [
        'user_id' => $row['user_id'],
        'username' => htmlspecialchars($row['username']),
        'last_lat' => floatval($row['last_lat']),
        'last_lng' => floatval($row['last_lng']),
        'current_km' => floatval($row['current_km'])
    ];
}

// 3. 计算我的实时排名 (基于累计距离 current_km)
$rank_sql = "SELECT COUNT(*) + 1 as my_rank FROM participations 
             WHERE event_id = ? AND current_km > ?";
$stmt = $conn->prepare($rank_sql);
$stmt->bind_param("id", $event_id, $dist);
$stmt->execute();
$rank_data = $stmt->get_result()->fetch_assoc();
$my_rank = $rank_data['my_rank'];

// 4. 返回最终数据
echo json_encode([
    'my_rank' => $my_rank,
    'competitors' => $competitors
]);