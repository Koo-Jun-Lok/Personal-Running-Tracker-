<?php
 // --- 1. Configuration & Setup ---
require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

 if (!isset($_GET['id'])) {
     header("Location: history.php");
     exit();
 }

 $run_id = $_GET['id'];
 $user_id = $_SESSION['user_id'];

 // --- 2. Fetch Run Details ---
 $sql = "SELECT * FROM run_activities WHERE run_id = ? AND user_id = ?";
 $run = null;
 if ($stmt = $conn->prepare($sql)) {
     $stmt->bind_param("ii", $run_id, $user_id);
     $stmt->execute();
     $result = $stmt->get_result();
     $run = $result->fetch_assoc();
     $stmt->close();
 }

 if (!$run) {
     echo "Run not found.";
     exit();
 }

// --- 3. 处理分享 Token (增加错误检查) ---
if (empty($run['share_token'])) {
    try {
        $newToken = bin2hex(random_bytes(16)); 
        $updateSql = "UPDATE run_activities SET share_token = ? WHERE run_id = ?";
        if ($upStmt = $conn->prepare($updateSql)) {
            $upStmt->bind_param("si", $newToken, $run_id);
            if ($upStmt->execute()) {
                $run['share_token'] = $newToken;
            }
            $upStmt->close();
        }
    } catch (Exception $e) {
        // 如果生成失败，先给一个临时值，防止页面 500
        $run['share_token'] = ""; 
    }
}

// 只有在 token 存在时才生成 URL
$shareUrl = "";
if (!empty($run['share_token'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $shareUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/share.php?token=" . $run['share_token'];
}
 // --- 4. Calculate Pace ---
 $pace_min = 0;
 $pace_sec = 0;
 if ($run['distance_km'] > 0) {
     $pace_total_sec = $run['duration_seconds'] / $run['distance_km'];
     $pace_total_int = round($pace_total_sec); 
     $pace_min = floor($pace_total_int / 60);
     $pace_sec = $pace_total_int % 60;
 }
 $gps_data_json = $run['gps_data']; 
 ?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
     <title>Run Details</title>
     <link rel="stylesheet" href="../style.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
     <style>
         body { 
             background: #F9FAFB; 
             height: 100vh; 
             height: 100dvh; 
             margin: 0; 
             overflow: hidden; 
             display: flex; 
             flex-direction: column; 
         }
         .page-header {
             position: absolute; top: 0; left: 0; width: 100%;
             padding: 20px; z-index: 1000;
             display: flex; justify-content: space-between; align-items: center;
             box-sizing: border-box;
             padding-top: calc(20px + env(safe-area-inset-top));
             pointer-events: none;
         }
         .btn-back {
             width: 40px; height: 40px;
             background: white; border-radius: 50%;
             display: flex; align-items: center; justify-content: center;
             box-shadow: 0 4px 10px rgba(0,0,0,0.1);
             color: #333; text-decoration: none; font-size: 18px;
             pointer-events: auto;
         }
         #map {
             width: 100%; 
             height: 55%; 
             background: #eee;
             z-index: 1;
             flex-shrink: 0;
         }
         .details-panel {
             height: 45%; 
             background: white;
             margin-top: -50px;
             border-top-left-radius: 30px;
             border-top-right-radius: 30px;
             position: relative; 
             z-index: 10;
             padding: 30px 25px;
             box-shadow: 0 -5px 25px rgba(0,0,0,0.1);
             overflow-y: auto;
             box-sizing: border-box;
             padding-bottom: 50px;
         }
         .run-header {
             display: flex; justify-content: space-between; align-items: center;
             margin-bottom: 25px;
         }
         .run-title { margin: 0; font-size: 20px; font-weight: 800; color: #1F2937; }
         .run-date { font-size: 13px; color: #6B7280; font-weight: 500; }
         .stats-grid {
             display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
             margin-bottom: 30px;
         }
         .stat-box {
             background: #F9FAFB; padding: 15px; border-radius: 18px; text-align: center;
             border: 1px solid #F3F4F6;
         }
         .stat-val { font-size: 24px; font-weight: 800; color: #1F2937; line-height: 1.2; }
         .stat-lbl { font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; margin-top: 5px; }
         .stat-icon { font-size: 16px; margin-bottom: 5px; display: inline-block; }
         .icon-blue { color: #2563EB; }
         .icon-orange { color: #F59E0B; }
         .icon-red { color: #EF4444; }
         .icon-green { color: #10B981; }
         .btn-delete {
             width: 100%; padding: 15px;
             background: #FEF2F2; color: #EF4444;
             border: 1px solid #FEE2E2; border-radius: 15px;
             font-weight: 700; cursor: pointer;
             text-align: center; display: block; text-decoration: none;
             margin-top: 20px;
             box-sizing: border-box;
         }
         #shareBtn {
             width: 40px; height: 40px; 
             background: #F3F4F6; 
             border-radius: 50%; 
             display: flex; align-items: center; justify-content: center; 
             color: #6B7280;
             cursor: pointer;
             transition: all 0.2s ease;
             pointer-events: auto;
         }
         #shareBtn:active { background: #E5E7EB; transform: scale(0.9); }
     </style>
 </head>
 <body>
     <div class="page-header">
         <a href="history.php" class="btn-back">
             <i class="fas fa-arrow-left"></i>
         </a>
     </div>

     <div id="map"></div>

     <div class="details-panel">
         <div class="run-header">
             <div>
                 <h1 class="run-title">Run Details</h1>
                 <div class="run-date">
                     <i class="far fa-calendar-alt"></i> 
                     <?php echo date("l, d M Y • H:i", strtotime($run['start_time'])); ?>
                 </div>
             </div>
             <div id="shareBtn" title="Share Activity">
                 <i class="fas fa-share-alt"></i>
             </div>
         </div>

         <div class="stats-grid">
             <div class="stat-box">
                 <i class="fas fa-route stat-icon icon-blue"></i>
                 <div class="stat-val"><?php echo number_format($run['distance_km'], 2); ?></div>
                 <div class="stat-lbl">Kilometers</div>
             </div>
             <div class="stat-box">
                 <i class="fas fa-stopwatch stat-icon icon-orange"></i>
                 <div class="stat-val">
                     <?php 
                         $h = floor($run['duration_seconds'] / 3600);
                         $m = floor(($run['duration_seconds'] % 3600) / 60);
                         $s = $run['duration_seconds'] % 60;
                         echo ($h > 0) ? sprintf("%d:%02d", $h, $m) : sprintf("%02d:%02d", $m, $s);
                     ?>
                 </div>
                 <div class="stat-lbl">Duration</div>
             </div>
             <div class="stat-box">
                 <i class="fas fa-fire stat-icon icon-red"></i>
                 <div class="stat-val"><?php echo round($run['calories_burned']); ?></div>
                 <div class="stat-lbl">Calories</div>
             </div>
             <div class="stat-box">
                 <i class="fas fa-tachometer-alt stat-icon icon-green"></i>
                 <div class="stat-val">
                     <?php echo $pace_min . "'" . sprintf("%02d", $pace_sec) . '"'; ?>
                 </div>
                 <div class="stat-lbl">Avg Pace</div>
             </div>
         </div>

         <a href="delete_run.php?id=<?php echo $run_id; ?>" class="btn-delete" onclick="return confirm('Delete this run permanently?')">
             <i class="fas fa-trash-alt"></i> Delete Activity
         </a>
     </div>

     <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
     <script>
         // 1. 初始化地图
         const map = L.map('map', { 
             zoomControl: false, 
             dragging: true, 
             scrollWheelZoom: true 
         });

         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
             attribution: ''
         }).addTo(map);

         const rawData = <?php echo $gps_data_json ? $gps_data_json : '[]'; ?>;
         if (rawData.length > 0) {
             const polyline = L.polyline(rawData, {
                 color: '#2563EB',
                 weight: 5,
                 opacity: 0.8,
                 lineCap: 'round'
             }).addTo(map);

             L.circleMarker(rawData[0], { radius: 6, color: 'white', fillColor: '#10B981', fillOpacity: 1, weight: 2 }).addTo(map);
             L.circleMarker(rawData[rawData.length - 1], { radius: 6, color: 'white', fillColor: '#EF4444', fillOpacity: 1, weight: 2 }).addTo(map);

             map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
         } else {
             map.setView([3.1390, 101.6869], 13);
         }

         // 2. 分享 URL 功能逻辑
         document.getElementById('shareBtn').addEventListener('click', async function() {
             const shareData = {
                 title: 'My Running Record',
                 text: 'Check out my <?php echo number_format($run['distance_km'], 2); ?>km run today!',
                 url: '<?php echo $shareUrl; ?>'
             };

             try {
                 if (navigator.share) {
                     // 调用原生分享菜单
                     await navigator.share(shareData);
                 } else {
                     // 备选方案：自动复制链接
                     await navigator.clipboard.writeText(shareData.url);
                     alert('Share link copied to clipboard!');
                 }
             } catch (err) {
                 console.error('Error sharing:', err);
             }
         });
     </script>
 </body>
 </html>