<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized_admin");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$event_id = intval($_GET['id']);
$default_avatar = "../assets/default_avatar.jpg"; 

// --- 2. Handle Approve/Reject Actions ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action']; 
    $status = ($action === 'approve') ? 'active' : 'rejected';
    $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
    $stmt->bind_param("si", $status, $event_id);
    if ($stmt->execute()) {
        $msg = ($status == 'active') ? "✅ Event Approved successfully!" : "❌ Event Rejected.";
        header("Location: admin_dashboard.php?msg=" . urlencode($msg));
        exit();
    }
}

// --- 3. Fetch Event Details ---
$sql = "SELECT e.*, u.name as manager_name, u.email as manager_email, u.avatar as manager_avatar
        FROM events e 
        JOIN users u ON e.manager_id = u.user_id 
        WHERE e.event_id = ?";
$evt = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
}

if (!$evt) die("Event not found.");

// 头像路径处理逻辑
$m_avatar = $default_avatar;
if (!empty($evt['manager_avatar'])) {
    $m_avatar = (strpos($evt['manager_avatar'], 'http') === 0) 
                ? $evt['manager_avatar'] 
                : "../uploads/avatars/" . $evt['manager_avatar'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Review Event | <?= htmlspecialchars($evt['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary: #2563EB; --success: #10B981; --warning: #F59E0B; --danger: #EF4444; --bg: #F1F5F9; }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; }
        
        .container { width: 100%; max-width: 900px; padding-bottom: 50px; }
        .card { background: white; border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #E2E8F0; }
        
        /* Banner & Status Overlay */
        .banner-container { position: relative; height: 350px; background: #CBD5E1; }
        .banner-img { width: 100%; height: 100%; object-fit: cover; }
        .status-overlay { position: absolute; top: 20px; right: 20px; padding: 10px 20px; border-radius: 15px; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; backdrop-filter: blur(10px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .st-pending { background: rgba(255, 247, 237, 0.9); color: #C2410C; border: 1px solid #FFEDD5; }
        .st-active { background: rgba(240, 253, 244, 0.9); color: #15803D; border: 1px solid #DCFCE7; }
        .st-rejected { background: rgba(254, 242, 242, 0.9); color: #991B1B; border: 1px solid #FEE2E2; }

        .content { padding: 40px; }
        .title { font-size: 36px; font-weight: 800; color: #0F172A; margin: 0 0 25px 0; letter-spacing: -1.5px; line-height: 1.1; }

        /* Meta Info Grid */
        .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 35px; }
        .meta-box { background: #F8FAFC; padding: 20px; border-radius: 20px; border: 1px solid #F1F5F9; text-align: left; }
        .meta-label { display: block; font-size: 10px; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .meta-value { font-size: 15px; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 8px; }

        /* Map UI */
        #map { height: 400px; width: 100%; border-radius: 25px; margin: 25px 0; border: 2px solid #F1F5F9; z-index: 1; }
        .label-content { background: white; color: black; padding: 5px 12px; border-radius: 10px; font-weight: 800; font-size: 10px; border: 2px solid #000; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        .section-title { font-size: 18px; font-weight: 800; color: #1E293B; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .description { font-size: 16px; line-height: 1.8; color: #475569; background: #F8FAFC; padding: 25px; border-radius: 20px; border: 1px solid #F1F5F9; margin-bottom: 35px; }

        /* Manager Section */
        .manager-card { display: flex; align-items: center; gap: 18px; padding: 20px; background: white; border: 2px solid #F1F5F9; border-radius: 22px; margin-bottom: 40px; }
        .manager-avatar { width: 55px; height: 55px; border-radius: 50%; object-fit: cover; border: 3px solid #F1F5F9; }

        /* Action Buttons */
        .actions { display: grid; grid-template-columns: 1fr 2fr 2fr; gap: 15px; }
        .btn { padding: 18px; border-radius: 20px; border: none; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
        .btn-back { background: #F1F5F9; color: #64748B; }
        .btn-back:hover { background: #E2E8F0; }
        .btn-approve { background: var(--primary); color: white; box-shadow: 0 10px 25px rgba(37,99,235,0.25); }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(37,99,235,0.3); }
        .btn-reject { background: #FEF2F2; color: var(--danger); border: 1px solid #FEE2E2; }
        .btn-reject:hover { background: var(--danger); color: white; }
        
        .processed-msg { grid-column: span 3; padding: 25px; text-align: center; background: #F8FAFC; border-radius: 22px; color: #64748B; font-weight: 700; border: 1px dashed #CBD5E1; }

        @media (max-width: 768px) {
            .meta-grid { grid-template-columns: 1fr; }
            .actions { grid-template-columns: 1fr; }
            .content { padding: 25px; }
            .banner-container { height: 220px; }
            .title { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="banner-container">
            <?php 
                $banner = (!empty($evt['banner_image'])) ? $evt['banner_image'] : "";
            ?>
            <?php if($banner): ?>
                <img src="<?= $banner ?>" class="banner-img">
            <?php else: ?>
                <div class="banner-img" style="display:flex; align-items:center; justify-content:center; background:#E2E8F0; color:#94A3B8;">
                    <i class="fas fa-image fa-4x"></i>
                </div>
            <?php endif; ?>
            
            <div class="status-overlay st-<?= $evt['status'] ?>">
                <i class="fas fa-dot-circle me-1"></i> <?= strtoupper($evt['status']) ?>
            </div>
        </div>

        <div class="content">
            <h1 class="title"><?= htmlspecialchars($evt['title']) ?></h1>

            <div class="meta-grid">
                <div class="meta-box">
                    <span class="meta-label">Event Date</span>
                    <span class="meta-value"><i class="far fa-calendar-alt text-primary"></i> <?= date("d M Y", strtotime($evt['event_date'])) ?></span>
                </div>
                <div class="meta-box">
                    <span class="meta-label">Time Window</span>
                    <span class="meta-value"><i class="far fa-clock text-primary"></i> <?= date("H:i", strtotime($evt['start_time'])) ?> - <?= date("H:i", strtotime($evt['end_time'])) ?></span>
                </div>
                <div class="meta-box">
                    <span class="meta-label">Distance</span>
                    <span class="meta-value"><i class="fas fa-route text-primary"></i> <?= $evt['target_distance_km'] ?> KM</span>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-map-marked-alt text-primary"></i> Route Map Audit</div>
            <div id="map"></div>

            <div class="section-title"><i class="fas fa-info-circle text-primary"></i> Event Description</div>
            <div class="description"><?= nl2br(htmlspecialchars($evt['description'])) ?></div>

            <div class="section-title"><i class="fas fa-user-shield text-primary"></i> Organizer Info</div>
            <div class="manager-card">
                <img src="<?= $m_avatar ?>" class="manager-avatar" onerror="this.src='<?= $default_avatar ?>'">
                <div>
                    <div style="font-weight: 800; color: #0F172A; font-size: 17px;"><?= htmlspecialchars($evt['manager_name']) ?></div>
                    <div style="font-size: 13px; color: #64748B;"><?= htmlspecialchars($evt['manager_email']) ?></div>
                </div>
            </div>

            <?php if ($evt['status'] === 'pending'): ?>
                <form method="POST" class="actions">
                    <a href="admin_dashboard.php" class="btn btn-back">Back</a>
                    <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Reject this event? The organizer will be notified.')">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                        <i class="fas fa-check-circle"></i> Approve Event
                    </button>
                </form>
            <?php else: ?>
                <div class="actions">
                    <div class="processed-msg">
                        <i class="fas fa-shield-check text-success me-2"></i> This event is currently <strong><?= strtoupper($evt['status']) ?></strong>
                    </div>
                    <a href="admin_dashboard.php" class="btn btn-back" style="grid-column: span 3;">Return to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script>
    // 初始化地图
    var map = L.map('map', { zoomControl: true, attributionControl: false }).setView([6.4582, 100.5041], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // 加载 GPX 轨迹
    <?php if(!empty($evt['route_url'])): ?>
    new L.GPX("../uploads/routes/<?= $evt['route_url'] ?>", {
        async: true,
        marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null },
        polyline_options: { color: '#2563EB', weight: 6, opacity: 0.85 }
    }).on('loaded', function(e) {
        var gpx = e.target;
        map.fitBounds(gpx.getBounds(), { padding: [40, 40] });
        
        var latlngs = gpx.getLayers()[0].getLatLngs();
        var startPos = latlngs[0];
        var endPos = latlngs[latlngs.length - 1];

        // 绘制起终点
        L.marker(startPos, { icon: L.divIcon({ className: '', html: '<div style="background:#10B981; width:14px; height:14px; border:3px solid white; border-radius:50%; box-shadow:0 0 10px rgba(0,0,0,0.2);"></div>', iconSize:[14,14], iconAnchor:[7,7] }) })
            .addTo(map).bindTooltip('<div class="label-content">START</div>', { permanent: true, direction: 'top', className: 'label-content', offset: [0, -10] });

        L.marker(endPos, { icon: L.divIcon({ className: '', html: '<div style="background:#EF4444; width:14px; height:14px; border:3px solid white; border-radius:50%; box-shadow:0 0 10px rgba(0,0,0,0.2);"></div>', iconSize:[14,14], iconAnchor:[7,7] }) })
            .addTo(map).bindTooltip('<div class="label-content" style="border-color:#EF4444;">FINISH</div>', { permanent: true, direction: 'top', className: 'label-content', offset: [0, -10] });
    }).addTo(map);
    <?php endif; ?>
</script>

</body>
</html>