<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$event_id = intval($_GET['id']);
$manager_id = $_SESSION['user_id'];

// --- 处理删除活动的请求 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    // 1. 先删除与该活动关联的所有报名记录 (防止外键约束报错)
    $del_part_stmt = $conn->prepare("DELETE FROM participations WHERE event_id = ?");
    $del_part_stmt->bind_param("i", $event_id);
    $del_part_stmt->execute();
    
    // 2. 删除活动本身
    $del_evt_stmt = $conn->prepare("DELETE FROM events WHERE event_id = ? AND manager_id = ?");
    $del_evt_stmt->bind_param("ii", $event_id, $manager_id);
    
    if ($del_evt_stmt->execute()) {
        header("Location: manager_dashboard.php?status=event_deleted");
        exit();
    } else {
        $error_msg = "Failed to delete event.";
    }
}

// 1. 获取活动详细信息
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND manager_id = ?");
$stmt->bind_param("ii", $event_id, $manager_id);
$stmt->execute();
$evt = $stmt->get_result()->fetch_assoc();
if (!$evt) die("Challenge not found or unauthorized.");

$is_virtual = ($evt['event_type'] === 'virtual');

// 2. 只有 Physical 模式需要排行榜数据
$finishers = null;
if (!$is_virtual) {
    $leader_finished_sql = "SELECT u.name AS username, u.avatar, p.status, 
                            TIMESTAMPDIFF(SECOND, p.nfc_verified_at, p.finish_time) as duration_sec
                            FROM participations p 
                            JOIN users u ON p.user_id = u.user_id 
                            WHERE p.event_id = ? AND p.status = 'completed'
                            ORDER BY duration_sec ASC LIMIT 5";
    $l_stmt = $conn->prepare($leader_finished_sql);
    $l_stmt->bind_param("i", $event_id);
    $l_stmt->execute();
    $finishers = $l_stmt->get_result();
}

// 3. 获取参与者列表
$part_sql = "SELECT u.name AS username, u.email, u.avatar, p.status, p.current_km, p.joined_at 
             FROM participations p 
             JOIN users u ON p.user_id = u.user_id 
             WHERE p.event_id = ? 
             ORDER BY p.current_km DESC";
$part_stmt = $conn->prepare($part_sql);
$part_stmt->bind_param("i", $event_id);
$part_stmt->execute();
$participants = $part_stmt->get_result();

// 头像辅助函数
function getAvatarUrl($avatar) {
    if (empty($avatar) || $avatar == 'default_avatar.jpg') return '../assets/default_avatar.jpg';
    return (filter_var($avatar, FILTER_VALIDATE_URL)) ? $avatar : '../uploads/avatars/' . $avatar;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Event: <?php echo htmlspecialchars($evt['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        :root { 
            --primary: #<?php echo $is_virtual ? '3B82F6' : '10B981'; ?>; 
            --gold: #F59E0B; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-dim: #64748B; 
        }
        body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); padding-bottom: 40px; }

        /* HERO SECTION */
        .hero { position: relative; height: 320px; overflow: hidden; }
        .hero img { width: 100%; height: 100%; object-fit: cover; }
        .hero-overlay { position: absolute; inset: 0; background: linear-gradient(0deg, var(--bg) 0%, rgba(248,250,252,0.2) 100%); }
        .hero-content { position: absolute; bottom: 30px; left: 20px; right: 20px; }
        .hero h1 { font-size: 32px; font-weight: 800; margin: 0; color: #000; letter-spacing: -1px; }
        .badge-type { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; display: inline-block; }

        .back-btn { position: absolute; top: 20px; left: 20px; width: 40px; height: 40px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; text-decoration: none; z-index: 10; }

        /* GRID LAYOUT FIX */
        .content-grid { 
            display: grid; 
            grid-template-columns: <?php echo $is_virtual ? '1fr' : 'minmax(0, 1fr) 350px'; ?>; 
            gap: 20px; padding: 0 20px; margin-top: -30px; position: relative; z-index: 5; 
        }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr !important; } }

        .card { background: var(--card); border-radius: 24px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); }
        .section-title { font-size: 14px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .title-left { display: flex; align-items: center; gap: 10px; }

        /* --- 管理操作按钮组 --- */
        .action-group { display: flex; gap: 8px; }
        
        .btn-edit {
            background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;
            padding: 8px 14px; border-radius: 12px; font-size: 11px; font-weight: 800;
            cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px;
            text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none;
        }
        .btn-edit:hover { background: #DBEAFE; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15); }

        .btn-delete {
            background: #FEF2F2; color: #EF4444; border: 1px solid #FECACA;
            padding: 8px 14px; border-radius: 12px; font-size: 11px; font-weight: 800;
            cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-delete:hover { background: #FEE2E2; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.15); }
        /* -------------------------- */

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-item { background: #F1F5F9; padding: 15px; border-radius: 18px; text-align: center; }
        .stat-val { font-size: 18px; font-weight: 800; color: #000; display: block; }
        .stat-lbl { font-size: 10px; color: var(--text-dim); text-transform: uppercase; }

        /* USER LIST DESIGN */
        .user-row { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #F1F5F9; }
        .user-info { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .avatar { width: 44px; height: 44px; border-radius: 14px; object-fit: cover; background: #eee; flex-shrink: 0; }
        .user-text { min-width: 0; }
        .user-text .name { font-size: 14px; font-weight: 700; color: #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-text .sub { font-size: 11px; color: var(--text-dim); }

        .status-pill { font-size: 10px; padding: 4px 12px; border-radius: 8px; font-weight: 800; text-transform: uppercase; flex-shrink: 0; }
        .status-pill.ready { background: #ECFDF5; color: #10B981; }
        .status-pill.completed { background: #FFF7ED; color: var(--gold); }

        /* VIRTUAL PROGRESS */
        .progress-container { width: 100%; max-width: 150px; margin-top: 4px; }
        .progress-bar-bg { width: 100%; height: 5px; background: #E2E8F0; border-radius: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: 0.5s; }

        /* NFC BUTTON */
        .btn-nfc-container { text-align: center; margin-top: 15px; }
        .btn-nfc { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 70%; max-width: 200px; background: #8B5CF6; color: white; 
            border: none; padding: 12px; border-radius: 15px; 
            font-weight: 800; font-size: 11px; cursor: pointer; 
            text-decoration: none; transition: 0.3s;
            box-shadow: 0 8px 15px rgba(139, 92, 246, 0.2);
        }
        .btn-nfc:hover { transform: translateY(-2px); filter: brightness(1.1); }

        /* MAP LABEL STYLE */
        #map { height: 280px; border-radius: 20px; background: #eee; border: 1px solid #E2E8F0; }
        .leaflet-tooltip.label-style {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }
    </style>
</head>
<body>

<a href="manager_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>

<div class="hero">
    <img src="<?php echo !empty($evt['banner_image']) ? $evt['banner_image'] : '../assets/placeholder.jpg'; ?>">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <span class="badge-type"><?php echo strtoupper($evt['event_type']); ?> RUN</span>
        <h1><?php echo htmlspecialchars($evt['title']); ?></h1>
    </div>
</div>

<div class="content-grid">
    <div class="main-col">
        <div class="card" style="margin-bottom: 20px;">
            <div class="section-title">
                <div class="title-left"><i class="fas fa-info-circle"></i> Event Overview</div>
                
                <div class="action-group">
                    <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    
                    <form method="POST" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to delete this event?\n\nThis will permanently delete the event and remove all runners currently joined. This action CANNOT be undone.');" style="margin: 0;">
                        <input type="hidden" name="action" value="delete_event">
                        <button type="submit" class="btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                    </form>
                </div>
                </div>
            
            <?php if(isset($error_msg)): ?>
                <div style="background: #FEF2F2; color: #EF4444; padding: 10px; border-radius: 10px; font-size: 12px; font-weight: 600; margin-bottom: 15px;">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-item"><span class="stat-val"><?php echo $evt['target_distance_km']; ?></span><span class="stat-lbl">KM Goal</span></div>
                <div class="stat-item"><span class="stat-val"><?php echo $participants->num_rows; ?></span><span class="stat-lbl">Runners</span></div>
                <div class="stat-item">
                    <span class="stat-val"><?php echo $is_virtual ? 'GPS' : date("H:i", strtotime($evt['start_time'])); ?></span>
                    <span class="stat-lbl"><?php echo $is_virtual ? 'Live' : 'Starts'; ?></span>
                </div>
            </div>
            <p style="font-size: 14px; color: var(--text-dim); line-height: 1.6; margin: 0;">
                <?php echo nl2br(htmlspecialchars($evt['description'])); ?>
            </p>
        </div>

        <div class="card">
            <div class="section-title">
                <div class="title-left"><i class="fas fa-users"></i> <?php echo $is_virtual ? 'Live Progress' : 'Participant Status'; ?></div>
            </div>
            <?php if ($participants->num_rows > 0): ?>
                <?php while($p = $participants->fetch_assoc()): 
                    $percent = ($evt['target_distance_km'] > 0) ? min(100, ($p['current_km'] / $evt['target_distance_km']) * 100) : 0;
                ?>
                    <div class="user-row">
                        <div class="user-info">
                            <img src="<?php echo getAvatarUrl($p['avatar']); ?>" class="avatar">
                            <div class="user-text">
                                <div class="name"><?php echo htmlspecialchars($p['username']); ?></div>
                                <?php if($is_virtual): ?>
                                    <div class="progress-container">
                                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                                        <span style="font-size: 9px; color: var(--text-dim); font-weight: 700;">PROGESS: <?php echo number_format($percent, 1); ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <div class="sub"><?php echo $p['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-pill <?php echo ($p['status'] == 'completed') ? 'completed' : 'ready'; ?>">
                                <?php echo $p['status']; ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-dim); text-align: center;">No participants yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!$is_virtual): ?>
    <div class="side-col">
        <div class="card" style="margin-bottom: 20px; padding: 15px;">
            <div class="section-title" style="margin: 10px;">
                <div class="title-left"><i class="fas fa-map-marked-alt"></i> Route</div>
            </div>
            <div id="map"></div>
            <div class="btn-nfc-container">
                <a href="nfc_manager.php?event_id=<?php echo $event_id; ?>" class="btn-nfc">
                    <i class="fas fa-rss"></i> NFC STATION
                </a>
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <div class="title-left"><i class="fas fa-trophy"></i> Rankings</div>
            </div>
            <div style="margin-bottom: 25px;">
                <h4 style="font-size: 10px; color: var(--gold); margin-bottom: 12px; font-weight: 800;">🏆 FINISHED</h4>
                <?php if ($finishers && $finishers->num_rows > 0): $rank = 1; while($l = $finishers->fetch_assoc()): ?>
                    <div class="user-row">
                        <div class="user-info">
                            <span style="font-weight: 800; color: var(--gold); width: 20px; font-size: 12px;">#<?php echo $rank++; ?></span>
                            <img src="<?php echo getAvatarUrl($l['avatar']); ?>" style="width:28px; height:28px; border-radius:8px;">
                            <span style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($l['username']); ?></span>
                        </div>
                        <span style="font-size: 12px; font-weight: 800; color: var(--gold);"><?php echo gmdate("H:i:s", $l['duration_sec']); ?></span>
                    </div>
                <?php endwhile; else: echo "<p style='font-size:11px; color:var(--text-dim)'>Waiting...</p>"; endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script>
<?php if(!$is_virtual && !empty($evt['route_url'])): ?>
    var map = L.map('map', { zoomControl: false, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var createCircleMarker = function(color) {
        return L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color:${color}; width:12px; height:12px; border:2px solid white; border-radius:50%; box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });
    };

    new L.GPX("../uploads/routes/<?php echo $evt['route_url']; ?>", {
        async: true,
        marker_options: { startIconUrl: null, endIconUrl: null },
        polyline_options: { color: '#10B981', weight: 5, opacity: 0.8 }
    }).on('loaded', function(e) {
        var gpx = e.target;
        map.fitBounds(gpx.getBounds(), { padding: [40, 40] });

        // 获取路径坐标
        var layers = gpx.getLayers();
        layers.forEach(function(layer) {
            if (layer instanceof L.Polyline) {
                var latlngs = layer.getLatLngs();
                if (latlngs.length > 0) {
                    var startPoint = latlngs[0];
                    var endPoint = latlngs[latlngs.length - 1];

                    // 添加起点标记
                    L.marker(startPoint, { icon: createCircleMarker('#10B981') }).addTo(map)
                        .bindTooltip("<div style='background:#10B981; color:white; padding:4px 8px; border-radius:5px; font-weight:900; font-size:10px;'>START</div>", { 
                            permanent: true, direction: 'top', offset: [0, -10], className: 'label-style' 
                        });

                    // 添加终点标记
                    L.marker(endPoint, { icon: createCircleMarker('#EF4444') }).addTo(map)
                        .bindTooltip("<div style='background:#EF4444; color:white; padding:4px 8px; border-radius:5px; font-weight:900; font-size:10px;'>FINISH</div>", { 
                            permanent: true, direction: 'top', offset: [0, -10], className: 'label-style' 
                        });
                }
            }
        });
    }).addTo(map);
<?php endif; ?>
</script>
</body>
</html>