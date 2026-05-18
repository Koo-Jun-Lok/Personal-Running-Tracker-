<?php
require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (!isset($_GET['token'])) {
    die("Invalid share link.");
}

$token = $_GET['token'];

// --- 2. Fetch Run Details & User Name (根据你的表结构修正) ---
// 修正：连接条件改为 r.user_id = u.user_id，名字字段为 u.name
$sql = "SELECT r.*, u.name as username 
        FROM run_activities r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.share_token = ?";

$run = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $run = $result->fetch_assoc();
    $stmt->close();
}

if (!$run) {
    die("Activity not found or link expired.");
}

// 记录 ID 供 UI 显示
$run_id_display = $run['run_id'];

// --- 3. Calculate Pace & Achievement ---
$pace_min = 0; $pace_sec = 0;
if ($run['distance_km'] > 0) {
    $pace_total_sec = $run['duration_seconds'] / $run['distance_km'];
    $pace_total_int = round($pace_total_sec); 
    $pace_min = floor($pace_total_int / 60);
    $pace_sec = $pace_total_int % 60;
}

// 创意功能：根据距离定义成就等级
$achievement = "Daily Runner";
$badge_color = "#3B82F6";
if ($run['distance_km'] >= 10) { $achievement = "Endurance King"; $badge_color = "#8B5CF6"; }
elseif ($run['distance_km'] >= 5) { $achievement = "Fitness Pro"; $badge_color = "#10B981"; }
elseif ($run['distance_km'] >= 3) { $achievement = "Active Striver"; $badge_color = "#F59E0B"; }

$gps_data_json = $run['gps_data']; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($run['username']); ?>'s Running Record</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { 
            background: #F9FAFB; 
            height: 100vh; height: 100dvh; 
            margin: 0; overflow: hidden; 
            display: flex; flex-direction: column; 
            font-family: 'Inter', -apple-system, sans-serif;
        }
        /* 顶部个性化 Header */
        .page-header {
            position: absolute; top: 0; left: 0; width: 100%;
            padding: 20px; z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            box-sizing: border-box;
            padding-top: calc(20px + env(safe-area-inset-top));
            pointer-events: none;
        }
        .user-badge {
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 10px;
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }
        .user-avatar {
            width: 24px; height: 24px;
            background: #2563EB; color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 12px;
        }
        .user-name { font-weight: 700; color: #1F2937; font-size: 14px; }

        #map { width: 100%; height: 55%; background: #eee; z-index: 1; flex-shrink: 0; }
        
        .details-panel {
            height: 45%; background: white; margin-top: -50px;
            border-top-left-radius: 35px; border-top-right-radius: 35px;
            position: relative; z-index: 10;
            padding: 35px 25px 20px;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.08);
            display: flex; flex-direction: column;
            box-sizing: border-box;
        }
        .run-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .run-title { margin: 0; font-size: 24px; font-weight: 900; color: #111827; letter-spacing: -0.5px; }
        .run-date { font-size: 13px; color: #9CA3AF; font-weight: 500; margin-top: 4px; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .stat-box {
            background: #F8FAFC; padding: 18px 10px; border-radius: 20px; text-align: center;
            border: 1px solid #F1F5F9;
        }
        .stat-val { font-size: 26px; font-weight: 800; color: #1E293B; }
        .stat-lbl { font-size: 10px; color: #94A3B8; text-transform: uppercase; font-weight: 700; margin-top: 4px; letter-spacing: 0.5px; }

        /* 创意底部区域 */
        .achievement-zone {
            margin-top: auto;
            background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
            padding: 15px; border-radius: 20px;
            display: flex; align-items: center; justify-content: space-between;
            border: 1px dashed #BAE6FD;
        }
        .achieve-info { display: flex; align-items: center; gap: 12px; }
        .achieve-icon {
            width: 40px; height: 40px; background: white;
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: <?php echo $badge_color; ?>;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .achieve-text h4 { margin: 0; font-size: 14px; color: #0369A1; }
        .achieve-text p { margin: 0; font-size: 11px; color: #0EA5E9; font-weight: 600; }
        
        .brand-footer {
            text-align: center; margin-top: 15px;
            font-size: 10px; color: #CBD5E1; font-weight: 700;
            text-transform: uppercase; letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="user-badge">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($run['username']); ?>'s Run</div>
        </div>
    </div>

    <div id="map"></div>

    <div class="details-panel">
        <div class="run-header">
            <div>
                <h1 class="run-title"><?php echo number_format($run['distance_km'], 2); ?> <span style="font-size: 16px;">KM</span></h1>
                <div class="run-date">
                    <i class="far fa-clock"></i> 
                    <?php echo date("l, d M Y • H:i", strtotime($run['start_time'])); ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 20px; font-weight: 800; color: #2563EB;">#<?php echo $run_id_display; ?></div>
                <div style="font-size: 10px; color: #9CA3AF; font-weight: 700;">ACTIVITY ID</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-val"><?php 
                    $h = floor($run['duration_seconds'] / 3600);
                    $m = floor(($run['duration_seconds'] % 3600) / 60);
                    $s = $run['duration_seconds'] % 60;
                    echo ($h > 0) ? sprintf("%d:%02d", $h, $m) : sprintf("%02d:%02d", $m, $s);
                ?></div>
                <div class="stat-lbl">Duration</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?php echo $pace_min . "'" . sprintf("%02d", $pace_sec) . '"'; ?></div>
                <div class="stat-lbl">Avg Pace</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?php echo round($run['calories_burned']); ?></div>
                <div class="stat-lbl">Calories</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?php 
                    $hours = $run['duration_seconds'] / 3600;
                    echo ($hours > 0) ? number_format($run['distance_km'] / $hours, 1) : "0.0"; 
                ?></div>
                <div class="stat-lbl">Avg Speed (km/h)</div>
            </div>
        </div>

        <div class="achievement-zone">
            <div class="achieve-info">
                <div class="achieve-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="achieve-text">
                    <h4><?php echo $achievement; ?></h4>
                    <p>Milestone Unlocked</p>
                </div>
            </div>
            <i class="fas fa-chevron-right" style="color: #BAE6FD;"></i>
        </div>

        <div class="brand-footer">
            Tracked with Your Tracker App
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map', { zoomControl: false, dragging: true, scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);

        const rawData = <?php echo $gps_data_json ? $gps_data_json : '[]'; ?>;
        if (rawData.length > 0) {
            const polyline = L.polyline(rawData, { color: '#2563EB', weight: 6, opacity: 0.9, lineCap: 'round' }).addTo(map);
            L.circleMarker(rawData[0], { radius: 6, color: 'white', fillColor: '#10B981', fillOpacity: 1, weight: 3 }).addTo(map);
            L.circleMarker(rawData[rawData.length - 1], { radius: 6, color: 'white', fillColor: '#EF4444', fillOpacity: 1, weight: 3 }).addTo(map);
            map.fitBounds(polyline.getBounds(), { padding: [60, 60] });
        }
    </script>
</body>
</html>