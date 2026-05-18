<?php
// --- 1. Setup ---
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);
$user_id = $_SESSION['user_id'];

date_default_timezone_set('Asia/Kuala_Lumpur');

// --- 2. Fetch Data (实时计算 Virtual 进度) ---
// 核心修改：增加子查询，根据 run_activities.start_time 实时汇总里程
$sql = "SELECT p.*, e.event_id as eid, e.title, e.target_distance_km, e.event_date, e.end_date, 
               e.start_time, e.end_time, e.banner_image, e.event_type,
        (SELECT COALESCE(SUM(distance_km), 0) 
         FROM run_activities 
         WHERE user_id = p.user_id 
         AND start_time >= p.joined_at 
         AND start_time >= e.event_date) as real_km
        FROM participations p 
        JOIN events e ON p.event_id = e.event_id 
        WHERE p.user_id = ? 
        ORDER BY (p.status = 'verified' OR p.status = 'completed') ASC, p.joined_at DESC";

$my_events = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $my_events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>My Progress - PRT System</title>
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-transform: uppercase; letter-spacing: 1px; z-index: 100;
        }
        .toggle-filter-btn.is-virtual { background: var(--virtual-purple); color: white; }

        .tabs-wrapper { margin-top: -90px; padding: 0 20px; margin-bottom: 25px; }
        .glass-tab-card {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 25px; padding: 6px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid rgba(255,255,255,0.3);
            display: flex; gap: 8px;
        }
        .tab-btn { flex: 1; text-align: center; padding: 12px; border-radius: 20px; font-size: 14px; font-weight: 800; text-decoration: none; color: #94A3B8; transition: 0.3s; }
        .tab-btn.active { background: var(--primary-blue); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }

        .container { padding: 0 20px; display: flex; flex-direction: column; gap: 40px; }
        .event-node { display: flex; gap: 15px; text-decoration: none; color: inherit; position: relative; }
        
        .event-sidebar { width: 55px; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; padding-top: 10px; }
        .side-date { font-family: 'Lexend', sans-serif; font-weight: 800; font-size: 26px; color: var(--text-main); line-height: 1; }
        .side-month { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 800; margin-top: 4px; }
        .side-line { width: 2px; flex-grow: 1; background: #E2E8F0; margin: 12px 0; border-radius: 1px; min-height: 50px; }
        .side-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: white; }
        .icon-physical { background: var(--primary-blue); }
        .icon-virtual { background: var(--virtual-purple); }

        .event-hero { flex-grow: 1; position: relative; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.04); }
        .hero-img-box { width: 100%; height: 240px; position: relative; }
        .event-img { width: 100%; height: 100%; object-fit: cover; }
        .hero-overlay { position: absolute; bottom: 0; left: 0; right: 0; height: 70%; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); z-index: 1; }

        .hero-info-stream { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px 25px; z-index: 2; color: white; }
        .evt-title { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -0.5px; line-height: 1.3; }
        .evt-meta { font-size: 11px; opacity: 0.7; font-weight: 600; margin-top: 5px; }

        .status-badge { font-size: 10px; font-weight: 900; text-transform: uppercase; padding: 6px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(5px); }
        .st-joined { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); }
        .st-ready { background: rgba(56, 189, 248, 0.2); color: #38BDF8; } 
        .st-started { background: rgba(34, 197, 94, 0.2); color: #4ADE80; }
        .st-done { background: rgba(255,255,255,0.95); color: var(--primary-blue); }

        .v-blade-progress { flex-grow: 1; position: relative; margin-top: 10px; }
        .v-blade-bar { height: 4px; background: rgba(255,255,255,0.2); border-radius: 4px; overflow: hidden; }
        .v-blade-fill { height: 100%; background: var(--virtual-purple); border-radius: 4px; transition: 1.5s ease; }
        .v-blade-stats { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.7); margin-top: 5px; text-transform: uppercase; }
        
        .completed-task { opacity: 0.6; filter: grayscale(0.6); }

        .section-divider { display: flex; align-items: center; margin: 20px 0 10px; font-size: 11px; font-weight: 900; color: #CBD5E1; text-transform: uppercase; letter-spacing: 1.5px; }
        .section-divider::after { content: ""; flex: 1; height: 1px; background: #E2E8F0; margin-left: 15px; }

        .bottom-nav { position: fixed; bottom: 0; width: 100%; height: 85px; background: white; display: flex; justify-content: space-around; align-items: center; z-index: 1000; border-radius: 30px 30px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.05); }
        .nav-item { text-decoration: none; color: #94A3B8; text-align: center; font-size: 11px; }
        .nav-item.active { color: var(--primary-blue); font-weight: 800; }
        .nav-item i { font-size: 22px; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>

    <div class="home-header">
        <h1 class="fw-black mb-0" style="font-size: 28px; letter-spacing: -1px;">My Progress</h1>
        <p class="mb-0 opacity-75 fw-medium">Tracking your active race journey</p>
        
        <button id="main-toggle" class="toggle-filter-btn" onclick="toggleMode()">
            <i id="toggle-icon" class="fas fa-location-arrow"></i>
            <span id="toggle-text">Physical Run</span>
        </button>
    </div>

    <div class="tabs-wrapper">
        <div class="glass-tab-card">
            <a href="events.php" class="tab-btn">Explore</a>
            <a href="my_events.php" class="tab-btn active">My Progress</a>
        </div>
    </div>

    <div class="container" id="event-container">
        <?php if (empty($my_events)): ?>
            <div class="text-center py-5">
                <i class="fas fa-ghost mb-3 opacity-10" style="font-size: 60px;"></i>
                <p class="text-muted fw-bold">No active challenges.</p>
            </div>
        <?php else: ?>
            
            <div id="active-divider" class="section-divider">Current Goals</div>
            
            <?php foreach ($my_events as $evt): 
                $st = $evt['status']; 
                $eid = $evt['eid'];
                $is_virtual = ($evt['event_type'] === 'virtual');
                $is_completed = ($st == 'verified' || $st == 'completed');

                // 计算进度
                $curr_dist = (float)($evt['real_km'] ?? 0);
                $target = (float)$evt['target_distance_km'];
                $percent = ($target > 0) ? min(100, round(($curr_dist / $target) * 100, 1)) : 0;
                
                // 如果是 Virtual 且进度 100%，视为已完成
                $display_completed = $is_completed || ($is_virtual && $percent >= 100);
            ?>
                <div class="event-node progress-card <?php echo $display_completed ? 'completed-task' : ''; ?>" 
                     data-type="<?php echo $is_virtual ? 'virtual' : 'physical'; ?>" 
                     data-completed="<?php echo $display_completed ? 'yes' : 'no'; ?>"
                     onclick="location.href='event_details.php?id=<?php echo $eid; ?>'">
                    
                    <div class="event-sidebar">
                        <div class="side-date"><?php echo date("d", strtotime($evt['event_date'])); ?></div>
                        <div class="side-month"><?php echo date("M", strtotime($evt['event_date'])); ?></div>
                        <div class="side-line"></div>
                        <div class="side-icon <?php echo $is_virtual ? 'icon-virtual' : 'icon-physical'; ?>">
                            <i class="fas <?php echo $is_virtual ? 'fa-bolt' : 'fa-fingerprint'; ?>"></i>
                        </div>
                    </div>

                    <div class="event-hero">
                        <div class="hero-img-box">
                            <img src="<?php echo !empty($evt['banner_image']) ? $evt['banner_image'] : '../assets/placeholder.jpg'; ?>" class="event-img">
                            <div class="hero-overlay"></div>
                        </div>

                        <div class="hero-info-stream">
                            <h3 class="evt-title"><?php echo htmlspecialchars($evt['title']); ?></h3>
                            <div class="evt-meta">
                                <?php echo $evt['target_distance_km']; ?> KM Target
                                <?php if($is_virtual): ?>
                                    • ENDS <?php echo date("d M Y", strtotime($evt['end_date'] ?? $evt['event_date'])); ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$is_virtual): ?>
                                <div class="status-pane">
                                    <?php if ($st == 'joined'): ?>
                                        <div class="status-badge st-joined"><i class="fas fa-hourglass-start"></i> NFC Linking...</div>
                                    <?php elseif ($st == 'ready'): ?>
                                        <div class="status-badge st-ready"><i class="fas fa-check-circle"></i> NFC Active</div>
                                    <?php elseif ($st == 'started'): ?>
                                        <div class="status-badge st-started"><i class="fas fa-running"></i> Racing</div>
                                    <?php elseif ($is_completed): ?>
                                        <div class="status-badge st-done"><i class="fas fa-trophy"></i> Verified</div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="v-blade-progress">
                                    <div class="v-blade-bar">
                                        <div class="v-blade-fill" style="width:<?php echo $percent; ?>%"></div>
                                    </div>
                                    <div class="v-blade-stats">
                                        <?php echo ($percent >= 100) ? 'Challenge Completed!' : 'Syncing • '.$percent.'% Done ('.number_format($curr_dist, 2).'/'.$target.' KM)'; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="completed-divider" class="section-divider">Past Challenges</div>
        <?php endif; ?>
    </div>

    <div class="bottom-nav">
        <a href="home.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="events.php" class="nav-item active"><i class="fas fa-trophy"></i><span>Events</span></a>
        <a href="history.php" class="nav-item"><i class="fas fa-history"></i><span>History</span></a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </div>

    <script>
        let currentMode = 'physical';

        function updateDisplay() {
            const btn = document.getElementById('main-toggle');
            const icon = document.getElementById('toggle-icon');
            const text = document.getElementById('toggle-text');
            const cards = document.querySelectorAll('.progress-card');
            const activeDiv = document.getElementById('active-divider');
            const compDiv = document.getElementById('completed-divider');

            if (currentMode === 'physical') {
                btn.classList.remove('is-virtual');
                icon.className = 'fas fa-location-arrow';
                text.innerText = 'Physical';
            } else {
                btn.classList.add('is-virtual');
                icon.className = 'fas fa-bolt';
                text.innerText = 'Virtual';
            }

            let hasActive = false, hasComp = false;

            cards.forEach(card => {
                const isType = card.getAttribute('data-type') === currentMode;
                const isDone = card.getAttribute('data-completed') === 'yes';

                if (isType) {
                    card.style.display = 'flex';
                    if (isDone) {
                        card.parentNode.appendChild(card);
                        hasComp = true;
                    } else {
                        activeDiv.after(card);
                        hasActive = true;
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            if(activeDiv) activeDiv.style.display = hasActive ? 'flex' : 'none';
            if(compDiv) compDiv.style.display = hasComp ? 'flex' : 'none';
        }

        function toggleMode() {
            currentMode = (currentMode === 'physical') ? 'virtual' : 'physical';
            updateDisplay();
        }
        window.onload = updateDisplay;
    </script>
</body>
</html>