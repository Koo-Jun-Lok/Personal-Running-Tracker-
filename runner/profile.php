<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role']; 
$email = ""; 
$joined_date = "";
$avatar_url = "../assets/default_avatar.jpg"; 

// 1. 获取用户基本信息
$u_sql = "SELECT email, created_at, avatar FROM users WHERE user_id = ?";
if ($stmt = $conn->prepare($u_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($r = $res->fetch_assoc()) {
        $email = $r['email'];
        $joined_date = date("M Y", strtotime($r['created_at']));
        if (!empty($r['avatar'])) { 
            $avatar_url = (filter_var($r['avatar'], FILTER_VALIDATE_URL)) ? $r['avatar'] : "../uploads/avatars/" . $r['avatar']; 
        }
    }
}

// 2. 计算跑步总数据
$total_dist = 0; $total_cal = 0;
$gps_sql = "SELECT SUM(distance_km) as dist, SUM(calories_burned) as cal FROM run_activities WHERE user_id = ?";
if ($stmt = $conn->prepare($gps_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $total_dist = $row['dist'] ?? 0;
        $total_cal = $row['cal'] ?? 0;
    }
}

// 3. 获取所有完赛奖励 (Physical 和 Virtual)
$rewards = [];
$rew_sql = "SELECT p.status, e.title, e.event_type, p.finish_time 
            FROM participations p 
            JOIN events e ON p.event_id = e.event_id 
            WHERE p.user_id = ? AND p.status = 'completed'
            ORDER BY p.finish_time DESC";
if ($stmt = $conn->prepare($rew_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $rewards[] = $row; }
}
$events_completed = count($rewards);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Profile - PRT System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #2563EB; --bg-light: #F8FAFC; --gold: #F59E0B; }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; padding-bottom: 110px; }

        /* Header 动画 */
        .home-header {
            background: linear-gradient(-45deg, #2563EB, #1D4ED8, #3B82F6, #1E40AF);
            background-size: 400% 400%;
            animation: gradientMove 8s ease infinite;
            color: white; padding: 60px 25px 120px;
            border-bottom-left-radius: 45px; border-bottom-right-radius: 45px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
            text-align: center; position: relative;
        }
        @keyframes gradientMove { 0% {background-position:0% 50%} 50% {background-position:100% 50%} 100% {background-position:0% 50%} }

        /* 磨砂玻璃 ID 标签 */
        .user-id-badge {
            background: rgba(255,255,255,0.2); color: white;
            padding: 6px 15px; border-radius: 12px;
            font-size: 11px; font-weight: 800; display: inline-block;
            backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3);
            margin-top: 10px; text-transform: uppercase;
        }

        .avatar-container {
            width: 100px; height: 100px;
            border-radius: 50%; border: 4px solid rgba(255,255,255,0.4);
            margin: 0 auto 15px; overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        /* 悬浮统计卡片 */
        .stats-main-container { margin-top: -80px; padding: 0 20px; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 35px; padding: 30px 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08); border: 1px solid rgba(255,255,255,0.3);
        }
        .stat-val { font-size: 24px; font-weight: 900; color: #111827; margin-bottom: 0; }
        .stat-lbl { font-size: 10px; font-weight: 800; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; }
        .v-line { width: 1px; height: 40px; background: #F1F5F9; }

        /* 奖励卡片横向滑动 */
        .reward-scroll { display: flex; overflow-x: auto; gap: 15px; padding: 5px 25px 20px; scrollbar-width: none; }
        .reward-scroll::-webkit-scrollbar { display: none; }
        .reward-item { min-width: 90px; text-align: center; }
        .reward-thumb {
            width: 75px; height: 75px; border-radius: 22px; background: white;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 8px; box-shadow: 0 8px 15px rgba(0,0,0,0.05);
            border: 1px solid #F1F5F9; font-size: 28px;
        }
        .reward-thumb img { width: 50px; height: 50px; }

        .section-label { font-size: 11px; font-weight: 900; color: #94A3B8; text-transform: uppercase; margin: 30px 25px 15px; letter-spacing: 1.5px; }

        /* 列表菜单项 */
        .menu-item {
            background: white; padding: 18px 22px; border-radius: 22px;
            margin: 0 25px 15px; border: 1px solid #F1F5F9;
            display: flex; align-items: center; justify-content: space-between;
            text-decoration: none; color: #1F2937; font-weight: 700;
        }
        .icon-box { width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px; }
        .bg-blue { background: #EFF6FF; color: #2563EB; }
        .bg-purple { background: #F5F3FF; color: #8B5CF6; }

        /* 底部导航栏 */
        .bottom-nav {
            position: fixed; bottom: 0; width: 100%; height: 85px; background: white;
            display: flex; justify-content: space-around; align-items: center; z-index: 1000;
            border-radius: 30px 30px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.05);
        }
        .nav-item { text-decoration: none; color: #94A3B8; text-align: center; font-size: 11px; }
        .nav-item.active { color: var(--primary-blue); font-weight: 800; }
        .nav-item i { font-size: 22px; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>

    <div class="home-header">
        <a href="edit_profile.php" style="position: absolute; top: 25px; right: 25px; color: white; opacity: 0.8;"><i class="fas fa-pen"></i></a>
        <div class="avatar-container">
            <img src="<?php echo $avatar_url; ?>" class="w-100 h-100" style="object-fit:cover;" onerror="this.src='../assets/default_avatar.jpg'">
        </div>
        <h2 class="fw-black mb-0"><?php echo htmlspecialchars($user_name); ?></h2>
        <p class="mb-1 opacity-75 small fw-medium"><?php echo htmlspecialchars($email); ?></p>
        <div>
            <div class="user-id-badge">RUNNER ID: #<?php echo $user_id; ?></div>
        </div>
    </div>

    <div class="stats-main-container">
        <div class="glass-card">
            <div class="d-flex justify-content-around align-items-center text-center">
                <div>
                    <p class="stat-val"><?php echo number_format($total_dist, 1); ?></p>
                    <span class="stat-lbl">Total KM</span>
                </div>
                <div class="v-line"></div>
                <div>
                    <p class="stat-val"><?php echo $events_completed; ?></p>
                    <span class="stat-lbl">Finished</span>
                </div>
                <div class="v-line"></div>
                <div>
                    <p class="stat-val"><?php echo number_format($total_cal); ?></p>
                    <span class="stat-lbl">Kcal</span>
                </div>
            </div>
        </div>
    </div>



    <div class="section-label">Account Menu</div>
    <div class="menu-container" style="padding: 0;">
        <a href="finished_events.php" class="menu-item">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-blue"><i class="fas fa-medal"></i></div>
                <span>Finished Events</span>
            </div>
            <i class="fas fa-chevron-right opacity-25"></i>
        </a>

        <?php if($user_role == 'event_manager'): ?>
            <a href="../manager/manager_dashboard.php" class="menu-item">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-purple" style="background:#ECFDF5; color:#10B981;"><i class="fas fa-briefcase"></i></div>
                    <span>Manager Portal</span>
                </div>
                <i class="fas fa-chevron-right opacity-25"></i>
            </a>
        <?php endif; ?>

        <a href="../logout.php" class="menu-item" style="border-color: #FEE2E2; color: #EF4444;">
            <div class="d-flex align-items-center">
                <div class="icon-box" style="background:#FEF2F2;"><i class="fas fa-sign-out-alt"></i></div>
                <span>Log Out</span>
            </div>
        </a>
    </div>

    <div class="modal fade" id="certModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 30px; border: none; overflow: hidden;">
                <div class="modal-body p-0">
                    <div id="cert-ui" style="padding: 40px 30px; background: white; text-align: center; border: 15px solid #2563EB;">
                        <i class="fas fa-medal" style="font-size: 50px; color: #F59E0B; margin-bottom: 20px;"></i>
                        <h4 style="font-weight: 900; letter-spacing: 1px;">CERTIFICATE</h4>
                        <p class="text-muted small">OF COMPLETION</p>
                        <hr>
                        <p class="small mb-1">Proudly presented to</p>
                        <h3 style="font-weight: 900; color: #2563EB; text-decoration: underline;"><?php echo strtoupper($user_name); ?></h3>
                        <p class="small mt-3 mb-1">For successfully finishing</p>
                        <h5 id="modal-event-title" style="font-weight: 800;">EVENT TITLE</h5>
                        <div class="mt-4 pt-3" style="border-top: 1px solid #eee;">
                            <p id="modal-event-date" class="small text-muted mb-0">DATE</p>
                            <small style="font-size: 8px; font-weight: 800;">OFFICIAL UUM PRT SYSTEM</small>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 p-3 fw-bold" style="border-radius: 0;" data-bs-dismiss="modal">CLOSE</button>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="home.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="events.php" class="nav-item"><i class="fas fa-trophy"></i><span>Events</span></a>
        <a href="history.php" class="nav-item"><i class="fas fa-history"></i><span>History</span></a>
        <a href="profile.php" class="nav-item active"><i class="fas fa-user"></i><span>Profile</span></a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openCert(title, date) {
            document.getElementById('modal-event-title').innerText = title;
            document.getElementById('modal-event-date').innerText = date;
            new bootstrap.Modal(document.getElementById('certModal')).show();
        }
        function openBadge(title) {
            alert("🏅 Virtual Badge: " + title + "\nThis digital honor has been added to your collection!");
        }
    </script>
</body>
</html>