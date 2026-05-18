<?php
// --- 1. 权限与安全检查 ---
// 引入 auth_check.php (假设 auth_check.php 在 prt_system/ 根目录下，而 home.php 在 runner/ 目录下)
require_once '../auth_check.php'; 

// 引入数据库连接
require_once '../db_connect.php';

// 开启错误报告（开发调试用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 从 Session 中获取已验证的用户信息
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// --- 2. 页面逻辑 ---
date_default_timezone_set('Asia/Kuala_Lumpur');
$hour = date('H');
$greeting = ($hour < 12) ? "Good Morning" : (($hour < 18) ? "Good Afternoon" : "Good Evening");


// --- 2. Fetch User Avatar ---
$avatar_url = "../assets/default_avatar.jpg"; 
$user_sql = "SELECT avatar FROM users WHERE user_id = ?";
if ($stmt = $conn->prepare($user_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['avatar'])) { $avatar_url = $row['avatar']; }
    }
    $stmt->close();
}

// --- 3. 读取今日详细数据 (daily_summaries) ---
$today_steps = 0;
$today_km = 0;
$today_cal = 0;
$today_goal = 5000; 

$summary_sql = "SELECT total_steps, total_km, calories_burned FROM daily_summaries WHERE user_id = ? AND summary_date = CURDATE()";
if ($stmt = $conn->prepare($summary_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $today_steps = $row['total_steps'];
        $today_km    = $row['total_km'];      // 这里已修改为获取今日公里数
        $today_cal   = $row['calories_burned']; // 这里已修改为获取今日消耗
    }
    $stmt->close();
}
$step_percent = ($today_steps > 0) ? ($today_steps / $today_goal) * 100 : 0;
if ($step_percent > 100) $step_percent = 100;
$steps_left = max(0, $today_goal - $today_steps);

// --- 4. Fetch Recent Activity ---
$recent_runs = [];
$sql_recent = "SELECT run_id, distance_km, duration_seconds, start_time FROM run_activities WHERE user_id = ? ORDER BY start_time DESC LIMIT 3";
if ($stmt = $conn->prepare($sql_recent)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $recent_runs[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Home - PRT System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #2563EB; --success-green: #22C55E; --bg-light: #F8FAFC; }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; padding-bottom: 110px; }
        
        .home-header {
            background: linear-gradient(-45deg, #2563EB, #1D4ED8, #3B82F6, #1E40AF);
            background-size: 400% 400%;
            animation: gradientMove 8s ease infinite;
            color: white; padding: 50px 25px 120px;
            border-bottom-left-radius: 45px; border-bottom-right-radius: 45px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
        }
        @keyframes gradientMove { 0% {background-position:0% 50%} 50% {background-position:100% 50%} 100% {background-position:0% 50%} }

        .step-main-container { margin-top: -90px; padding: 0 20px; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 35px; padding: 35px 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08); text-align: center; border: 1px solid rgba(255,255,255,0.3);
            transition: transform 0.3s ease;
        }
        .glass-card:hover { transform: translateY(-5px); }

        .progress-ring__circle { transition: stroke-dashoffset 1s ease-in-out; transform: rotate(-90deg); transform-origin: 50% 50%; }

        .stat-box { background: #F1F5F9; border-radius: 22px; padding: 18px 5px; text-align: center; border: 1px solid #E2E8F0; }
        .btn-run {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); color: white;
            border-radius: 25px; padding: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3); text-decoration: none; display: block; margin-top: 25px;
        }

        .activity-card { border-radius: 22px; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; }
        .activity-card:hover { border-color: var(--primary-blue); background: #F0F7FF !important; transform: scale(1.02); }

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
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <p class="mb-1 opacity-75 fw-medium"><?php echo $greeting; ?>,</p>
                <h1 class="fw-black mb-0" style="font-size: 28px; letter-spacing: -0.5px;"><?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            </div>
            <div style="width:55px; height:55px; border-radius:50%; overflow:hidden; border:3px solid rgba(255,255,255,0.4); box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                <img src="<?php echo $avatar_url; ?>" class="w-100 h-100" style="object-fit:cover;">
            </div>
        </div>
    </div>

    <div class="step-main-container">
        <div class="glass-card card border-0">
                    <div class="progress-ring-container" style="position: relative; width: 200px; height: 200px; margin: 0 auto;">
            <svg width="200" height="200" viewBox="0 0 200 200">
                <defs>
                    <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#2563EB" />
                        <stop offset="100%" stop-color="#22C55E" />
                    </linearGradient>
                </defs>
                <circle stroke="#F1F5F9" stroke-width="16" fill="transparent" r="85" cx="100" cy="100"/>
                <circle class="progress-ring__circle" stroke="url(#ringGrad)" stroke-width="16" 
                        stroke-dasharray="534" 
                        stroke-dashoffset="<?php echo 534 - (534 * $step_percent / 100); ?>" 
                        stroke-linecap="round" fill="transparent" r="85" cx="100" cy="100"/>
            </svg>
            
            <div class="position-absolute top-50 start-50 translate-middle text-center" style="width: 100%;">
                <h1 class="fw-black mb-0" style="font-size: 42px; letter-spacing: -2px; color: #111827;">
                    <?php echo number_format($today_steps); ?>
                </h1>
                <small class="text-muted fw-bold" style="font-size: 11px; letter-spacing: 1px; display: block; margin-top: -5px;">
                    STEPS TODAY
                </small>
            </div>
        </div>

            <p class="mt-3 small fw-semibold text-secondary">
                <?php echo ($steps_left > 0) ? "🎯 $steps_left steps left to goal" : "🏆 Goal Reached! Keep it up!"; ?>
            </p>

            <div class="row mt-4 g-3">
                <div class="col-6">
                    <div class="stat-box">
                        <i class="fas fa-fire-alt text-danger"></i>
                        <div class="fw-black h5 mb-0"><?php echo number_format($today_cal, 1); ?></div>
                        <small class="text-muted fw-bold" style="font-size: 10px;">KCAL TODAY</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-box">
                        <i class="fas fa-route text-primary"></i>
                        <div class="fw-black h5 mb-0"><?php echo number_format($today_km, 2); ?></div>
                        <small class="text-muted fw-bold" style="font-size: 10px;">KM TODAY</small>
                    </div>
                </div>
            </div>

            <a href="run.php" class="btn-run">Start Training</a>

            <button onclick="syncSteps()" id="syncBtn" class="btn btn-link text-decoration-none text-muted mt-3 py-0 fw-bold" style="font-size: 13px;">
                <i class="fas fa-sync-alt me-1"></i> <span id="syncText">Sync from Wearable</span>
            </button>
        </div>
    </div>

    <div class="container mt-5 px-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-dark">Recent Activity</h6>
            <a href="history.php" class="text-primary text-decoration-none fw-bold" style="font-size:12px;">View All</a>
        </div>

        <?php if (count($recent_runs) > 0): ?>
            <?php foreach ($recent_runs as $run): ?>
                <div class="activity-card card border-0 mb-3 p-3 shadow-sm bg-white" onclick="window.location='view_run.php?id=<?php echo $run['run_id']; ?>'">
                    <div class="d-flex align-items-center">
                        <div class="rounded-4 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:52px; height:52px;">
                            <i class="fas fa-running fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold" style="font-size: 14px;">Training Session</h6>
                            <small class="text-muted"><?php echo date("d M, H:i", strtotime($run['start_time'])); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="d-block fw-black text-dark fs-5"><?php echo number_format($run['distance_km'], 2); ?> <small class="fs-6 fw-normal text-muted">km</small></span>
                            <i class="fas fa-chevron-right text-muted" style="font-size: 10px;"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-5 border-2 border-dashed" style="border-color: #E2E8F0 !important;">
                <p class="text-muted mb-0 small">😴 No activity yet.<br>Start your first run today!</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bottom-nav">
        <a href="home.php" class="nav-item active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="events.php" class="nav-item"><i class="fas fa-trophy"></i><span>Events</span></a>
        <a href="history.php" class="nav-item"><i class="fas fa-history"></i><span>History</span></a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </div>

    <script>
    window.onload = function() { silentSync(); };

    async function silentSync() {
        try { await fetch('/prt_system/sync_data.php'); } catch (e) { console.error("Silent sync failed"); }
    }

    async function syncSteps() {
        const btn = document.getElementById('syncBtn');
        const text = document.getElementById('syncText');
        const icon = btn.querySelector('i');
        
        btn.style.pointerEvents = "none";
        icon.classList.add('fa-spin');
        text.innerText = "Syncing...";
        
        try {
            const response = await fetch('/prt_system/sync_data.php');
            if(response.ok) {
                text.innerText = "✅ Synced Successfully";
                setTimeout(() => { window.location.reload(); }, 1000);
            } else { throw new Error(); }
        } catch (e) {
            alert("Sync failed. Check connection.");
            icon.classList.remove('fa-spin');
            text.innerText = "Sync from Wearable";
            btn.style.pointerEvents = "auto";
        }
    }

    // --- Web Push 订阅逻辑 ---

// 1. 用于转换 VAPID Key 的辅助函数
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// 2. 向系统请求权限并订阅推送服务
async function subscribeUserToPush() {
    // 检查浏览器是否支持 Service Worker 和 Push Manager
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        try {
            // 等待 Service Worker 准备就绪
            const registration = await navigator.serviceWorker.ready;
            
            // 你的 VAPID Public Key
            const publicVapidKey = 'BJdrHY8CzAEQ6GDHtF2SAuwOnyAv03_QnbkeIHn744C6ib3xQqMFLqXVs-_K2ZNVokt98U37DkcjfpIWCPt9ULQ'; 
            
            console.log("正在请求推送订阅...");
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
            });

            console.log("订阅成功，正在将凭证发送给服务器...");
            
            // 将订阅信息发送到刚刚写的 PHP 后端保存
            const response = await fetch('../save_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                console.log('✅ Push subscription successfully saved in database!');
            } else {
                console.error('❌ Failed to save subscription in database:', result.message);
            }

        } catch (error) {
            console.error('⚠️ Push subscription failed:', error);
        }
    } else {
        console.warn('当前浏览器不支持推送通知。');
    }
}

// 3. 页面加载完成后，检查权限状态
window.addEventListener('load', () => {
    // 确保你的 Service Worker 已经注册了
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('../service-worker.js')
            .then(function(registration) {
                console.log('Service Worker 注册成功，Scope:', registration.scope);
                
                // 检查当前的通知权限状态
                if (Notification.permission === 'default') {
                    // 如果还没问过用户，就弹窗询问
                    console.log("正在向用户请求通知权限...");
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            console.log("用户点击了【允许】");
                            subscribeUserToPush();
                        } else {
                            console.log("用户拒绝了通知请求");
                        }
                    });
                } else if (Notification.permission === 'granted') {
                    // 如果用户以前点过允许，每次进入页面都静默更新一次凭证确保不过期
                    console.log("用户之前已授权，正在更新订阅...");
                    subscribeUserToPush(); 
                }
            })
            .catch(function(error) {
                console.log('Service Worker 注册失败:', error);
            });
    }
});
    </script>
</body>
</html>