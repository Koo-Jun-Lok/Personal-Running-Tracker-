<?php
// --- 1. Setup ---
require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- 2. Helper Functions ---
function formatDuration($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    if ($h > 0) return sprintf("%d:%02d:%02d", $h, $m, $s);
    return sprintf("%02d:%02d", $m, $s);
}

// --- 3. 获取可选月份列表 ---
$month_sql = "SELECT DISTINCT DATE_FORMAT(start_time, '%Y-%m') as month_value, 
              DATE_FORMAT(start_time, '%M %Y') as month_label 
              FROM run_activities 
              WHERE user_id = ? 
              ORDER BY month_value DESC";
$available_months = [];
if ($stmt = $conn->prepare($month_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $available_months[] = $row; }
    $stmt->close();
}

// 确定当前选中的月份
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (empty($_GET['month']) && !empty($available_months)) {
    $selected_month = $available_months[0]['month_value'];
}

// --- 4. 获取该月的所有活动记录 ---
$sql = "SELECT run_id, start_time, distance_km, duration_seconds, calories_burned, avg_pace_min_km, gps_data 
        FROM run_activities 
        WHERE user_id = ? AND DATE_FORMAT(start_time, '%Y-%m') = ?
        ORDER BY start_time DESC";
$runs = [];
$total_km = 0; // 初始化总公里数
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("is", $user_id, $selected_month);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { 
        $runs[] = $row; 
        $total_km += $row['distance_km']; // 累加总公里数
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>History - PRT System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #2563EB; --bg-light: #F8FAFC; }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; padding-bottom: 110px; }
        
        /* Header 动态渐变 */
        .home-header {
            background: linear-gradient(-45deg, #2563EB, #1D4ED8, #3B82F6, #1E40AF);
            background-size: 400% 400%;
            animation: gradientMove 8s ease infinite;
            color: white; padding: 50px 25px 120px;
            border-bottom-left-radius: 45px; border-bottom-right-radius: 45px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
        }
        @keyframes gradientMove { 0% {background-position:0% 50%} 50% {background-position:100% 50%} 100% {background-position:0% 50%} }

        .filter-container { margin-top: -90px; padding: 0 20px; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 35px; padding: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08); border: 1px solid rgba(255,255,255,0.3);
        }

        .month-select {
            border: none; background: #F1F5F9; color: #111827;
            padding: 12px 20px; border-radius: 20px;
            font-size: 15px; font-weight: 800; outline: none; width: 100%;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='F19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 15px center; background-size: 18px;
        }

        .summary-box { 
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); 
            border-radius: 22px; padding: 15px; border: 1px solid #E2E8F0;
        }

        /* Activity Card 优化 */
        .activity-card { 
            border-radius: 25px; transition: all 0.2s; cursor: pointer; 
            border: 1px solid #F1F5F9; background: white; text-decoration: none; color: inherit;
            display: block;
        }
        .activity-card:active { transform: scale(0.97); background: #F0F7FF; }
        
        .icon-circle {
            width: 52px; height: 52px; border-radius: 18px;
            background: rgba(37, 99, 235, 0.1); color: var(--primary-blue);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        .nav-item { text-decoration: none; color: #94A3B8; text-align: center; font-size: 11px; }
        .nav-item.active { color: var(--primary-blue); font-weight: 800; }
        .nav-item i { font-size: 22px; display: block; margin-bottom: 2px; }

        .bottom-nav {
            position: fixed; bottom: 0; width: 100%; height: 85px; background: white;
            display: flex; justify-content: space-around; align-items: center; z-index: 1000;
            border-radius: 30px 30px 0 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <div class="home-header">
        <h1 class="fw-black mb-0" style="font-size: 28px; letter-spacing: -1px;">Activity History</h1>
        <p class="mb-0 opacity-75 fw-medium">Tracking your progress over time</p>
    </div>

    <div class="filter-container">
        <div class="glass-card">
            <div class="row g-3 align-items-center">
                <div class="col-7">
                    <form id="monthForm" method="GET" action="history.php">
                        <select name="month" class="month-select" onchange="this.form.submit()">
                            <?php if(empty($available_months)): ?>
                                <option value="">No Activity</option>
                            <?php else: ?>
                                <?php foreach($available_months as $m): ?>
                                    <option value="<?php echo $m['month_value']; ?>" 
                                        <?php echo ($m['month_value'] == $selected_month) ? 'selected' : ''; ?>>
                                        <?php echo $m['month_label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </form>
                </div>
                <div class="col-5">
                    <div class="summary-box text-center">
                        <div class="fw-black text-primary h5 mb-0"><?php echo number_format($total_km, 1); ?></div>
                        <small class="text-muted fw-bold" style="font-size: 9px; text-transform: uppercase;">Total KM</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-dark"><?php echo count($runs); ?> Activities Found</h6>
        </div>

        <?php if (empty($runs)): ?>
            <div class="text-center py-5 bg-white rounded-5 border-2 border-dashed" style="border-style: dashed !important; border-color: #E2E8F0 !important;">
                <i class="fas fa-calendar-day mb-3 opacity-25" style="font-size: 40px;"></i>
                <p class="text-muted small mb-0">No records found for this period.</p>
            </div>
        <?php else: ?>
            <?php foreach ($runs as $run): ?>
                <a href="view_run.php?id=<?php echo $run['run_id']; ?>" class="activity-card card border-0 mb-3 p-3 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle me-3">
                            <i class="fas <?php echo empty($run['gps_data']) ? 'fa-clock-rotate-left' : 'fa-person-running'; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold" style="font-size: 14px; color: #1E293B;">
                                <?php echo empty($run['gps_data']) ? 'Synced Activity' : 'GPS Run'; ?>
                            </h6>
                            <small class="text-muted fw-semibold" style="font-size: 11px;">
                                <?php echo date("D, d M • H:i", strtotime($run['start_time'])); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="d-block fw-black text-dark fs-5">
                                <?php echo number_format($run['distance_km'], 2); ?>
                                <small class="fw-normal text-muted" style="font-size: 11px;">km</small>
                            </span>
                            <div class="d-flex justify-content-end gap-2" style="font-size: 10px;">
                                <span class="text-primary fw-bold"><i class="fas fa-bolt me-1"></i><?php echo $run['avg_pace_min_km'] ?: '--:--'; ?></span>
                                <span class="text-muted fw-bold"><?php echo formatDuration($run['duration_seconds']); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="bottom-nav">
        <a href="home.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="events.php" class="nav-item"><i class="fas fa-trophy"></i><span>Events</span></a>
        <a href="history.php" class="nav-item active"><i class="fas fa-history"></i><span>History</span></a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </div>

</body>
</html>