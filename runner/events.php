<?php
// --- 1. Setup ---
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);
$user_id = $_SESSION['user_id'];

// --- 2. Fetch Events (Only those NOT joined by the user) ---
// 使用 NOT EXISTS 过滤掉用户已经参加的活动
$sql = "SELECT e.* FROM events e 
        WHERE e.event_date >= CURDATE() 
        AND e.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM participations p 
            WHERE p.event_id = e.event_id 
            AND p.user_id = ?
        )
        ORDER BY e.event_date ASC";

$events = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Explore Events - PRT System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Lexend:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-blue: #2563EB; 
            --virtual-purple: #8B5CF6; 
            --bg-light: #F1F5F9; 
            --text-main: #0F172A;
            --text-muted: #64748B;
        }
        
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; padding-bottom: 110px; overflow-x: hidden; }

        /* Header */
        .home-header {
            background: linear-gradient(-45deg, #2563EB, #1D4ED8, #3B82F6, #1E40AF);
            background-size: 400% 400%;
            animation: gradientMove 8s ease infinite;
            color: white; padding: 60px 25px 120px;
            border-bottom-left-radius: 45px; border-bottom-right-radius: 45px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
            position: relative;
        }
        @keyframes gradientMove { 0% {background-position:0% 50%} 50% {background-position:100% 50%} 100% {background-position:0% 50%} }

        .toggle-filter-btn {
            position: absolute; top: 60px; right: 25px;
            background: white; padding: 8px 16px; border-radius: 20px;
            display: flex; align-items: center; gap: 8px; border: none;
            font-size: 11px; font-weight: 900; color: var(--primary-blue);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-transform: uppercase; letter-spacing: 1px; z-index: 100;
        }
        .toggle-filter-btn:active { transform: scale(0.9); }
        .toggle-filter-btn.is-virtual { background: var(--virtual-purple); color: white; }

        /* Tabs Card */
        .tabs-wrapper { margin-top: -90px; padding: 0 20px; margin-bottom: 25px; }
        .glass-tab-card {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 25px; padding: 6px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid rgba(255,255,255,0.3);
            display: flex; gap: 8px;
        }
        .tab-btn { flex: 1; text-align: center; padding: 12px; border-radius: 20px; font-size: 14px; font-weight: 800; text-decoration: none; color: #94A3B8; transition: 0.3s; }
        .tab-btn.active { background: var(--primary-blue); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }

        .section-divider-container { display: flex; align-items: center; margin-top: 20px; margin-bottom: 25px; padding-left: 5px; }
        .dynamic-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; transition: color 0.3s ease; flex-shrink: 0; color: var(--primary-blue); }
        .title-line { flex-grow: 1; height: 1px; margin-left: 15px; background-color: #E2E8F0; transition: background-color 0.3s ease; }

        .container { padding: 0 20px; display: flex; flex-direction: column; gap: 40px; }
        .event-node { display: flex; gap: 15px; text-decoration: none; color: inherit; position: relative; transition: 0.4s; }

        /* 左侧指示器 */
        .event-sidebar { width: 55px; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; padding-top: 10px; }
        .side-date { font-family: 'Lexend', sans-serif; font-weight: 800; font-size: 26px; color: var(--text-main); line-height: 1; }
        .side-month { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-top: 4px; }
        .side-line { width: 2px; flex-grow: 1; background: #E2E8F0; margin: 12px 0; border-radius: 1px; min-height: 50px; }
        .side-type-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: white; }
        .icon-physical { background: var(--primary-blue); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2); }
        .icon-virtual { background: var(--virtual-purple); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2); }

        /* 右侧大图卡片 */
        .event-hero { flex-grow: 1; position: relative; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.04); }
        .hero-img-box { width: 100%; height: 230px; position: relative; overflow: hidden; }
        .event-img { width: 100%; height: 100%; object-fit: cover; }
        .hero-overlay { position: absolute; bottom: 0; left: 0; right: 0; height: 75%; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 50%, transparent 100%); z-index: 1; }
        .hero-info-stream { position: absolute; bottom: 0; left: 0; right: 0; padding: 22px 25px; z-index: 2; color: white; }
        .evt-title { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2; }
        .meta-row { display: flex; align-items: center; gap: 15px; margin-top: 8px; font-size: 11px; font-weight: 600; opacity: 0.9; }
        .badge-dist { position: absolute; top: 15px; right: 20px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px); color: var(--text-main); padding: 5px 14px; border-radius: 12px; font-size: 12px; font-weight: 900; z-index: 2; }

        /* Bottom Nav */
        .bottom-nav { position: fixed; bottom: 0; width: 100%; height: 85px; background: white; display: flex; justify-content: space-around; align-items: center; z-index: 1000; border-radius: 30px 30px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.05); }
        .nav-item { text-decoration: none; color: #94A3B8; text-align: center; font-size: 11px; }
        .nav-item.active { color: var(--primary-blue); font-weight: 800; }
        .nav-item i { font-size: 22px; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>

    <div class="home-header">
        <h1 class="fw-black mb-0" style="font-size: 28px; letter-spacing: -1px;">Events Hub</h1>
        <p class="mb-0 opacity-75 fw-medium">Available challenges for you</p>
        
        <button id="main-toggle" class="toggle-filter-btn" onclick="toggleEvents()">
            <i id="toggle-icon" class="fas fa-location-arrow"></i>
            <span id="toggle-text">Physical </span>
        </button>
    </div>

    <div class="tabs-wrapper">
        <div class="glass-tab-card">
            <a href="events.php" class="tab-btn active">Explore</a>
            <a href="my_events.php" class="tab-btn">My Progress</a>
        </div>
    </div>

    <div class="container" id="event-container">
        <div class="section-divider-container">
            <div id="dynamic-title" class="dynamic-title">Explore Physical Runs</div>
            <div id="title-line" class="title-line"></div>
        </div>

        <?php if (count($events) > 0): ?>
            <?php foreach ($events as $evt): 
                $is_virtual = (isset($evt['event_type']) && $evt['event_type'] == 'virtual');
                $type_class = $is_virtual ? 'virtual' : 'physical';
            ?>
                <a href="event_details.php?id=<?php echo $evt['event_id']; ?>" class="event-node" data-type="<?php echo $type_class; ?>">
                    <div class="event-sidebar">
                        <div class="side-date"><?php echo date("d", strtotime($evt['event_date'])); ?></div>
                        <div class="side-month"><?php echo date("M", strtotime($evt['event_date'])); ?></div>
                        <div class="side-line"></div>
                        <div class="side-type-icon icon-<?php echo $type_class; ?>">
                            <i class="fas <?php echo $is_virtual ? 'fa-bolt' : 'fa-location-arrow'; ?>"></i>
                        </div>
                    </div>

                    <div class="event-hero">
                        <div class="badge-dist"><?php echo $evt['target_distance_km']; ?> KM</div>
                        <div class="hero-img-box">
                            <?php if(!empty($evt['banner_image'])): ?>
                                <img src="<?php echo $evt['banner_image']; ?>" class="event-img" loading="lazy">
                            <?php else: ?>
                                <div class="event-img d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-image text-muted opacity-25 fs-1"></i>
                                </div>
                            <?php endif; ?>
                            <div class="hero-overlay"></div>
                        </div>

                        <div class="hero-info-stream">
                            <h3 class="evt-title"><?php echo htmlspecialchars($evt['title']); ?></h3>
                            <div class="meta-row">
                                <div class="meta-item"><i class="far fa-clock"></i> <?php echo date("H:i", strtotime($evt['start_time'])); ?></div>
                                <div class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo $is_virtual ? 'Remote Anywhere' : 'UUM Campus'; ?></div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
             <div class="text-center py-5 w-100">
                <i class="fas fa-check-circle mb-3 opacity-10" style="font-size: 60px; color: var(--primary-blue);"></i>
                <p class="text-muted fw-bold">You've joined all available events!</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bottom-nav">
        <a href="home.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="events.php" class="nav-item active"><i class="fas fa-trophy"></i><span>Events</span></a>
        <a href="history.php" class="nav-item"><i class="fas fa-history"></i><span>History</span></a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </div>

    <script>
        let currentType = 'physical';
        
        function updateDisplay() {
            const nodes = document.querySelectorAll('.event-node');
            const btn = document.getElementById('main-toggle');
            const icon = document.getElementById('toggle-icon');
            const text = document.getElementById('toggle-text');
            const dynamicTitle = document.getElementById('dynamic-title');
            const titleLine = document.getElementById('title-line');

            if(currentType === 'physical') {
                btn.classList.remove('is-virtual');
                icon.className = 'fas fa-location-arrow';
                text.innerText = 'Physical ';
                dynamicTitle.innerText = 'Explore Physical Runs';
                dynamicTitle.style.color = 'var(--primary-blue)';
                titleLine.style.backgroundColor = '#E2E8F0';
            } else {
                btn.classList.add('is-virtual');
                icon.className = 'fas fa-bolt';
                text.innerText = 'Virtual ';
                dynamicTitle.innerText = 'Active Virtual Challenges';
                dynamicTitle.style.color = 'var(--virtual-purple)';
                titleLine.style.backgroundColor = '#DDD6FE';
            }

            let found = false;
            nodes.forEach(node => {
                if (node.getAttribute('data-type') === currentType) {
                    node.style.display = 'flex';
                    setTimeout(() => { node.style.opacity = '1'; node.style.transform = 'translateY(0)'; }, 50);
                    found = true;
                } else {
                    node.style.opacity = '0';
                    node.style.transform = 'translateY(10px)';
                    setTimeout(() => { node.style.display = 'none'; }, 300);
                }
            });
        }

        function toggleEvents() {
            currentType = (currentType === 'physical') ? 'virtual' : 'physical';
            updateDisplay();
        }

        window.onload = updateDisplay;
    </script>
</body>
</html>