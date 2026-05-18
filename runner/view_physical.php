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

// --- 智能捕捉上一页 URL ---
// 如果存在来源页面，并且不是当前页面（防止刷新导致死循环），就记录下来
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], basename($_SERVER['PHP_SELF'])) === false) {
    $_SESSION['last_visited_page'] = $_SERVER['HTTP_REFERER'];
}
// 使用记录的上一页，如果没有则默认回 events.php
$back_url = isset($_SESSION['last_visited_page']) ? $_SESSION['last_visited_page'] : "events.php";


// --- 2. Fetch Event Info ---
$sql = "SELECT * FROM events WHERE event_id = ? AND status = 'active'";
$evt = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
}

if (!$evt) {
    die("Event not found or not active.");
}

$is_virtual = ($evt['event_type'] === 'virtual');

// --- 3. Check Participation ---
$check_participation = "SELECT status, nfc_verified_at, finish_time FROM participations WHERE user_id = ? AND event_id = ?";
$participation = null;
if ($stmt = $conn->prepare($check_participation)) {
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $participation = $stmt->get_result()->fetch_assoc();
}

// --- 4. Fetch Ranking (Only if user has completed the physical event) ---
$show_ranking = ($participation && $participation['status'] === 'completed' && !$is_virtual);
$finishers = [];

if ($show_ranking) {
    $rank_sql = "SELECT u.user_id, u.name AS username, u.avatar, 
                        TIMESTAMPDIFF(SECOND, p.nfc_verified_at, p.finish_time) as duration_sec
                 FROM participations p 
                 JOIN users u ON p.user_id = u.user_id 
                 WHERE p.event_id = ? AND p.status = 'completed'
                 ORDER BY duration_sec ASC LIMIT 10";
                 
    if ($r_stmt = $conn->prepare($rank_sql)) {
        $r_stmt->bind_param("i", $event_id);
        $r_stmt->execute();
        $res = $r_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $finishers[] = $row;
        }
    }
}

// Helper for avatars
function getAvatarUrl($avatar) {
    if (empty($avatar) || $avatar == 'default_avatar.jpg') return '../assets/default_avatar.jpg';
    return (filter_var($avatar, FILTER_VALIDATE_URL)) ? $avatar : '../uploads/avatars/' . $avatar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($evt['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { 
            --theme-color: #10B981; 
            --bg: #F8FAFC; 
            --text-main: #0F172A;
            --text-muted: #64748B;
            --gold: #F59E0B;
            --silver: #94A3B8;
            --bronze: #B45309;
        }
        
        body { background: var(--bg); margin: 0; font-family: 'Inter', sans-serif; color: var(--text-main); padding-bottom: 100px; }
        .container { max-width: 900px; margin: 0 auto; padding: 15px; }

        /* Banner Section */
        .event-card { background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.04); margin-bottom: 20px; border: 1px solid #F1F5F9; }
        .event-banner { width: 100%; height: 240px; object-fit: cover; }
        .event-content { padding: 30px; position: relative; }
        
        .type-badge {
            position: absolute; top: -15px; left: 30px;
            background: var(--theme-color); color: white;
            padding: 6px 16px; border-radius: 12px; font-weight: 800; font-size: 11px;
            text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .title { font-size: 28px; font-weight: 900; margin: 10px 0; letter-spacing: -0.5px; }
        
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 25px 0; }
        .meta-item { display: flex; align-items: center; gap: 12px; color: var(--text-muted); font-weight: 600; font-size: 14px; }
        .meta-item i { color: var(--theme-color); font-size: 18px; width: 24px; text-align: center; }

        /* Map & Instructions */
        .map-wrapper { padding: 10px; background: white; border-radius: 28px; border: 1px solid #F1F5F9; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        #map { height: 350px; width: 100%; border-radius: 22px; z-index: 1; }
        
        .instruction-box { 
            margin-top: 15px; padding: 20px; background: #F8FAFC; 
            border-radius: 20px; border-left: 5px solid var(--theme-color); 
        }
        .instruction-box h4 { margin: 0 0 8px; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px; }

        /* Leaderboard Section */
        .ranking-wrapper { background: white; border-radius: 28px; padding: 25px; border: 1px solid #F1F5F9; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .ranking-header { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 900; margin-bottom: 20px; color: var(--text-main); }
        .ranking-header i { color: var(--gold); font-size: 22px; }
        
        .rank-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #F1F5F9; transition: 0.2s; border-radius: 12px; }
        .rank-row:hover { background: #F8FAFC; }
        .rank-row:last-child { border-bottom: none; }
        .rank-row.is-me { background: #ECFDF5; border: 1px solid #A7F3D0; }
        
        .rank-left { display: flex; align-items: center; gap: 15px; }
        .rank-num { font-size: 16px; font-weight: 900; width: 25px; text-align: center; color: var(--text-muted); }
        .rank-1 { color: var(--gold); font-size: 20px; }
        .rank-2 { color: var(--silver); font-size: 18px; }
        .rank-3 { color: var(--bronze); font-size: 18px; }
        
        .rank-avatar { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; }
        .rank-name { font-weight: 700; font-size: 14px; color: var(--text-main); }
        .rank-time { font-weight: 800; font-size: 13px; color: var(--theme-color); font-family: monospace; }

        /* Fixed Action Bar */
        .action-bar { 
            position: fixed; bottom: 0; left: 0; right: 0; 
            background: rgba(255,255,255,0.9); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 20px 30px; box-shadow: 0 -10px 40px rgba(0,0,0,0.08); 
            display: flex; justify-content: space-between; align-items: center; z-index: 1000;
        }
        .btn-main { 
            background: var(--theme-color); color: white; 
            padding: 15px 35px; border-radius: 18px; 
            text-decoration: none; font-weight: 800; font-size: 16px; border: none;
            transition: 0.3s;
        }
        .btn-main:active { transform: scale(0.95); }
        .btn-status { background: #E2E8F0; color: #64748B; padding: 12px 20px; border-radius: 14px; font-weight: 700; font-size: 13px; }

        @media (min-width: 768px) { .event-banner { height: 400px; } }
        .leaflet-tooltip.label-style { background: transparent; border: none; box-shadow: none; padding: 0; }
        .leaflet-tooltip-top:before { border-top-color: transparent; }
    </style>
</head>
<body>

<div class="container">
    <div class="event-card">
        <img src="<?php echo !empty($evt['banner_image']) ? $evt['banner_image'] : '../assets/placeholder.jpg'; ?>" class="event-banner">
        <div class="event-content">
            <div class="type-badge"><?php echo $is_virtual ? 'VIRTUAL RUN' : 'PHYSICAL RUN'; ?></div>
            <h1 class="title"><?php echo htmlspecialchars($evt['title']); ?></h1>
            
            <div class="meta-grid">
                <div class="meta-item"><i class="far fa-calendar-alt"></i> <?php echo date("d M Y", strtotime($evt['event_date'])); ?></div>
                <div class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo $is_virtual ? 'Anywhere' : 'UUM Campus'; ?></div>
                <div class="meta-item"><i class="fas fa-flag-checkered"></i> <?php echo $evt['target_distance_km']; ?> KM Challenge</div>
            </div>

            <p style="color: #475569; font-size: 15px; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($evt['description'])); ?></p>
        </div>
    </div>

    <?php if ($show_ranking && count($finishers) > 0): ?>
    <div class="ranking-wrapper">
        <div class="ranking-header">
            <i class="fas fa-trophy"></i> Official Leaderboard
        </div>
        <div class="ranking-list">
            <?php 
            $rank = 1;
            foreach ($finishers as $finisher): 
                $is_me = ($finisher['user_id'] == $user_id);
                $rank_class = ($rank <= 3) ? "rank-{$rank}" : "";
            ?>
                <div class="rank-row <?php echo $is_me ? 'is-me' : ''; ?>">
                    <div class="rank-left">
                        <div class="rank-num <?php echo $rank_class; ?>">
                            <?php echo ($rank <= 3) ? '<i class="fas fa-medal"></i>' : "#".$rank; ?>
                        </div>
                        <img src="<?php echo getAvatarUrl($finisher['avatar']); ?>" class="rank-avatar">
                        <div class="rank-name">
                            <?php echo htmlspecialchars($finisher['username']); ?>
                            <?php if($is_me) echo '<span style="font-size:10px; background:var(--theme-color); color:white; padding:2px 6px; border-radius:6px; margin-left:5px;">YOU</span>'; ?>
                        </div>
                    </div>
                    <div class="rank-time">
                        <?php echo gmdate("H:i:s", $finisher['duration_sec']); ?>
                    </div>
                </div>
            <?php $rank++; endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!$is_virtual): ?>
    <div class="map-wrapper">
        <div style="padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 900;"><i class="fas fa-map-location-dot text-primary me-2"></i> Official Race Route</h3>
            <span style="font-size: 11px; font-weight: 700; color: #94A3B8;">GPS VERIFIED</span>
        </div>
        
        <div id="map"></div>
        
        <?php if(!empty($evt['route_instructions'])): ?>
        <div class="instruction-box">
            <h4><i class="fas fa-quote-left" style="color: var(--theme-color);"></i> Route Notes</h4>
            <p style="margin:0; font-size: 14px; color: #475569;"><?php echo nl2br(htmlspecialchars($evt['route_instructions'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="action-bar">
    <a href="<?php echo htmlspecialchars($back_url); ?>" style="color: #64748B; text-decoration: none; font-weight: 700;">
        <i class="fas fa-arrow-left-long me-2"></i>Back
    </a>
    
    <?php if (!$participation): ?>
        <a href="../checkout.php?event_id=<?php echo $event_id; ?>" class="btn-main" style="text-decoration: none;">
            Join & Pay RM 50
        </a>
    <?php else: ?>
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="btn-status"><?php echo strtoupper($participation['status']); ?></span>
            
            <?php 
            $p_status = $participation['status'];
            $is_tagged = ($p_status === 'ready' || !empty($participation['nfc_verified_at']));

            if (!$is_tagged && $p_status === 'joined' && !$is_virtual): ?>
                <button class="btn-main" style="background: #94A3B8; cursor: not-allowed; opacity: 0.8;" onclick="alert('Tag Required: Please scan your NFC tag at the manager station.')">
                    <i class="fas fa-lock me-2"></i>Waiting for Tag
                </button>

            <?php elseif (in_array($p_status, ['ready', 'started', 'checkpoint_passed'])): ?>
                <a href="track_run.php?id=<?php echo $event_id; ?>" class="btn-main" style="background: #3B82F6; text-decoration: none;">
                    <?php echo ($p_status === 'ready') ? 'Start Race' : 'Continue Race'; ?>
                </a>

            <?php elseif ($p_status === 'completed' || $p_status === 'verified'): ?>
                <button class="btn-main" style="background: #10B981; cursor: default;">
                    <i class="fas fa-check-circle me-2"></i>Finished
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script>
    <?php if(!$is_virtual): ?>
    var map = L.map('map', { 
        zoomControl: false, 
        scrollWheelZoom: false 
    }).setView([6.4582, 100.5041], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var createCircleMarker = function(color) {
        return L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color:${color}; width:15px; height:15px; border:3px solid white; border-radius:50%; box-shadow:0 0 10px rgba(0,0,0,0.3);"></div>`,
            iconSize: [15, 15],
            iconAnchor: [7, 7]
        });
    };

    <?php if(!empty($evt['route_url'])): ?>
    var gpxUrl = "../uploads/routes/<?php echo $evt['route_url']; ?>";
    
    new L.GPX(gpxUrl, {
        async: true,
        marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null },
        polyline_options: { color: '#2563EB', weight: 6, opacity: 0.85 }
    }).on('loaded', function(e) {
        var gpx = e.target;
        map.fitBounds(gpx.getBounds(), { padding: [50, 50] });

        var layers = gpx.getLayers();
        layers.forEach(function(layer) {
            if (layer instanceof L.Polyline) {
                var latlngs = layer.getLatLngs();
                if (latlngs.length > 0) {
                    var startPoint = latlngs[0];
                    var endPoint = latlngs[latlngs.length - 1];

                    L.marker(startPoint, { icon: createCircleMarker('#10B981') }).addTo(map)
                        .bindTooltip("<div style='background:#10B981; color:white; padding:4px 8px; border-radius:5px; font-weight:900;'>START POINT</div>", { 
                            permanent: true, direction: 'top', offset: [0, -10], className: 'label-style' 
                        });

                    L.marker(endPoint, { icon: createCircleMarker('#EF4444') }).addTo(map)
                        .bindTooltip("<div style='background:#EF4444; color:white; padding:4px 8px; border-radius:5px; font-weight:900;'>FINISH POINT</div>", { 
                            permanent: true, direction: 'top', offset: [0, -10], className: 'label-style' 
                        });
                }
            }
        });
    }).addTo(map);
    <?php endif; ?>
    <?php endif; ?>
</script>
</body>
</html>