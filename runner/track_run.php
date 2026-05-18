<?php
// --- 1. Setup ---
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// 获取活动信息及参与状态
$stmt = $conn->prepare("SELECT e.*, p.participation_id, p.status, p.current_km FROM events e JOIN participations p ON e.event_id = p.event_id WHERE e.event_id = ? AND p.user_id = ?");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// 如果状态已经是 completed，说明已经扫过码了，直接打回
if (!$data || in_array($data['status'], ['completed', 'verified'])) {
    header("Location: my_events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Live Racing - <?php echo htmlspecialchars($data['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@700;900&family=Inter:wght@400;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary: #2563EB; --start: #10B981; --finish: #EF4444; }
        html, body { margin: 0; padding: 0; height: 100%; width: 100%; font-family: 'Inter', sans-serif; background: #000; overflow: hidden; }
        #map { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 0; background: #242424; }

        .dashboard {
            position: fixed; top: env(safe-area-inset-top, 15px); left: 15px; right: 15px;
            background: rgba(17, 24, 39, 0.95); backdrop-filter: blur(10px);
            border-radius: 24px; z-index: 1000; padding: 20px; display: grid; grid-template-columns: 1fr 1fr 1fr;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1);
        }
        .data-box { text-align: center; color: white; }
        .data-label { font-size: 9px; text-transform: uppercase; color: #94A3B8; font-weight: 800; letter-spacing: 1px; margin-bottom: 4px; }
        .data-value { font-family: 'JetBrains Mono', monospace; font-size: 20px; font-weight: 700; }
        .data-unit { font-size: 10px; margin-left: 2px; opacity: 0.6; }

        .status-bar {
            position: fixed; top: 125px; left: 50%; transform: translateX(-50%);
            background: var(--primary); color: white; padding: 6px 20px;
            border-radius: 50px; font-size: 11px; font-weight: 900; z-index: 1000;
            box-shadow: 0 10px 20px rgba(37,99,235,0.3); white-space: nowrap; transition: 0.3s;
        }

        .remaining-box {
            position: fixed; top: 165px; left: 50%; transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px);
            padding: 8px 18px; border-radius: 15px; z-index: 1000;
            color: white; display: flex; align-items: baseline; gap: 8px; border: 1px solid rgba(255,255,255,0.1);
        }
        .rem-value { font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700; color: #FB7185; }

        /* 底部布局优化：提示选手使用 NFC */
        .nav-footer { position: fixed; bottom: max(35px, env(safe-area-inset-bottom)); left: 20px; right: 20px; z-index: 1000; display: flex; gap: 15px; align-items: center; }
        .nfc-hint { flex: 1; background: rgba(0,0,0,0.8); color: white; border: 1px solid var(--primary); height: 64px; border-radius: 20px; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 10px; backdrop-filter: blur(5px); }
        .nfc-hint i { color: var(--primary); font-size: 20px; }
        .recenter-btn { width: 64px; height: 64px; background: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: none; }

        .user-dot { width: 16px; height: 16px; background: #3B82F6; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 15px rgba(59,130,246,0.8); }
        .competitor-dot { width: 12px; height: 12px; background: #F59E0B; border: 2px solid white; border-radius: 50%; box-shadow: 0 0 8px rgba(0,0,0,0.3); }
        .marker-label { background: transparent; border: none; box-shadow: none; padding: 0; }
        .label-content { background: white; color: #111827; padding: 4px 10px; border-radius: 8px; font-weight: 900; font-size: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: 2px solid #000; white-space: nowrap; }
    </style>
</head>
<body>

    <div class="dashboard">
        <div class="data-box"><div class="data-label">Time</div><div class="data-value" id="timer">00:00</div></div>
        <div class="data-box" style="border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1);"><div class="data-label">Distance</div><div class="data-value" id="dist-val">0.00<span class="data-unit">km</span></div></div>
        <div class="data-box"><div class="data-label">Avg Pace</div><div class="data-value" id="pace-val">--<span class="data-unit">/km</span></div></div>
    </div>

    <div class="status-bar" id="status-bar"><i class="fas fa-satellite-dish me-2"></i> Initializing GPS...</div>
    <div class="remaining-box"><span class="rem-label" style="font-size:10px; font-weight:800; color:#94A3B8;">REMAINING:</span><span class="rem-value" id="rem-val">--.--</span><span class="rem-unit" style="font-size:10px; color:white;">km</span></div>

    <div id="map"></div>

    <div class="nav-footer">
        <button class="recenter-btn" onclick="recenter()"><i class="fas fa-crosshairs"></i></button>
        <div class="nfc-hint" id="nfc-guide">
            <i class="fas fa-nfc-symbol"></i>
            <span>Scan NFC at Finish</span>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false, attributionControl: false }).setView([6.4582, 100.5041], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var userMarker, routeLine, finishPoint;
        var competitorMarkers = {};
        var firstLocation = true, lastLatLng = null, startTime = Date.now();

        // --- 初始化里程 ---
        var totalDistance = parseFloat(localStorage.getItem('run_dist_<?php echo $event_id; ?>')) || <?php echo floatval($data['current_km']); ?>;
        document.getElementById('dist-val').innerHTML = totalDistance.toFixed(2) + '<span class="data-unit">km</span>';

        // --- Web Worker 处理后台同步 ---
        var trackWorker = new Worker('track_worker.js');
        trackWorker.onmessage = function(e) { if (e.data.action === 'tick') syncCompetition(); };
        trackWorker.postMessage({ action: 'start' });

        // 计时器
        setInterval(() => {
            let elapsed = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('timer').innerText = `${Math.floor(elapsed / 60).toString().padStart(2, '0')}:${(elapsed % 60).toString().padStart(2, '0')}`;
            if (totalDistance > 0.05) {
                let pace = elapsed / totalDistance;
                document.getElementById('pace-val').innerHTML = `${Math.floor(pace / 60)}'${Math.floor(pace % 60).toString().padStart(2, '0')}"<span class="data-unit">/km</span>`;
            }
        }, 1000);

        function syncCompetition() {
            if (!userMarker) return;
            const pos = userMarker.getLatLng();
            fetch(`sync_competition.php?event_id=<?php echo $event_id; ?>&lat=${pos.lat}&lng=${pos.lng}&dist=${totalDistance.toFixed(2)}`)
                .then(res => res.json()).then(data => {
                    // --- 核心：实时检查状态是否被 Manager 改变 ---
                    if (data.status === 'completed' || data.status === 'verified') {
                        localStorage.removeItem('run_dist_<?php echo $event_id; ?>');
                        alert("🏁 Race Finished! NFC tag scanned by Manager.");
                        window.location.href = "my_events.php";
                        return;
                    }

                    const sb = document.getElementById('status-bar');
                    if (!sb.innerText.includes("ARRIVED")) sb.innerHTML = `<i class="fas fa-trophy me-2" style="color:#F59E0B"></i> RANK: #${data.my_rank}`;
                    
                    data.competitors.forEach(p => {
                        if (p.user_id == <?php echo $user_id; ?>) return;
                        if (competitorMarkers[p.user_id]) competitorMarkers[p.user_id].setLatLng([p.last_lat, p.last_lng]);
                        else competitorMarkers[p.user_id] = L.marker([p.last_lat, p.last_lng], { icon: L.divIcon({ className: 'competitor-dot', iconSize: [12, 12] }) }).addTo(map).bindTooltip(p.username, { direction: 'top', className: 'marker-label' });
                    });
                }).catch(err => console.error(err));
        }

        new L.GPX("../uploads/routes/<?php echo $data['route_url']; ?>", {
            async: true,
            marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null },
            polyline_options: { color: '#2563EB', weight: 8, opacity: 0.6 }
        }).on('loaded', function(e) {
            routeLine = e.target;
            map.fitBounds(routeLine.getBounds(), { padding: [80, 80] });
            var latlngs = routeLine.getLayers()[0].getLatLngs();
            finishPoint = latlngs[latlngs.length - 1];
            
            // 打点 Start 和 Finish (保留你要求的逻辑)
            L.marker(latlngs[0], { icon: L.divIcon({ className: '', html: '<div style="background-color:#10B981; width:15px; height:15px; border:3px solid white; border-radius:50%;"></div>', iconSize:[15,15], iconAnchor:[7,7] }) }).addTo(map).bindTooltip('<div class="label-content">START</div>', { permanent: true, direction: 'top', offset: [0, -10], className: 'marker-label' });
            L.marker(finishPoint, { icon: L.divIcon({ className: '', html: '<div style="background-color:#EF4444; width:15px; height:15px; border:3px solid white; border-radius:50%;"></div>', iconSize:[15,15], iconAnchor:[7,7] }) }).addTo(map).bindTooltip('<div class="label-content">FINISH</div>', { permanent: true, direction: 'top', offset: [0, -10], className: 'marker-label' });
        }).addTo(map);

        function onLocationFound(e) {
            const userLatLng = e.latlng;
            if (!userMarker) userMarker = L.marker(userLatLng, { icon: L.divIcon({ className: 'user-dot', iconSize: [16, 16], iconAnchor: [8, 8] }) }).addTo(map);
            else {
                if (lastLatLng) {
                    let move = lastLatLng.distanceTo(userLatLng);
                    if (move > 3) { 
                        totalDistance += (move / 1000); 
                        localStorage.setItem('run_dist_<?php echo $event_id; ?>', totalDistance);
                        document.getElementById('dist-val').innerHTML = totalDistance.toFixed(2) + '<span class="data-unit">km</span>';
                    }
                }
                userMarker.setLatLng(userLatLng);
            }
            lastLatLng = userLatLng;
            if (firstLocation) { map.setView(userLatLng, 18); firstLocation = false; }
            
            if (finishPoint) {
                let d = userLatLng.distanceTo(finishPoint);
                document.getElementById('rem-val').innerText = (d / 1000).toFixed(2);
                const sb = document.getElementById('status-bar');
                const ng = document.getElementById('nfc-guide');
                
                if (d < 40) { 
                    sb.innerText = "🏁 ARRIVED! FIND MANAGER TO SCAN"; 
                    sb.style.background = "#10B981"; 
                    ng.style.borderColor = "#10B981";
                    ng.style.background = "rgba(16, 185, 129, 0.2)";
                } else {
                    sb.style.background = "var(--primary)";
                    ng.style.background = "rgba(0,0,0,0.8)";
                }
            }
        }

        map.locate({ setView: false, watch: true, enableHighAccuracy: true });
        map.on('locationfound', onLocationFound);
        
        function recenter() { if (userMarker) map.setView(userMarker.getLatLng(), 18); }
        
        // 激活 WakeLock 防止息屏
        document.addEventListener('click', async () => {
            if ('wakeLock' in navigator) { try { await navigator.wakeLock.request('screen'); } catch (err) {} }
        });

        window.addEventListener('load', () => setTimeout(() => map.invalidateSize(), 800));
    </script>
</body>
</html>