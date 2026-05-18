<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// 1. 获取完赛总数据 (仅限已完赛的活动)
$total_dist = 0; 
$total_cal = 0;
$total_events = 0;

$stats_sql = "SELECT SUM(r.distance_km) as dist, SUM(r.calories_burned) as cal 
              FROM run_activities r
              JOIN participations p ON r.user_id = p.user_id 
              WHERE p.user_id = ? AND p.status = 'completed'";
if ($stmt = $conn->prepare($stats_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $total_dist = $row['dist'] ?? 0;
        $total_cal = $row['cal'] ?? 0;
    }
}

// 2. 获取所有已完赛的活动详情
$finished_events = [];
$rew_sql = "SELECT e.event_id, e.title, e.event_type, e.banner_image, e.target_distance_km, p.finish_time,
                   TIMESTAMPDIFF(SECOND, p.nfc_verified_at, p.finish_time) as duration_sec
            FROM participations p 
            JOIN events e ON p.event_id = e.event_id 
            WHERE p.user_id = ? AND p.status = 'completed'
            ORDER BY p.finish_time DESC";
if ($stmt = $conn->prepare($rew_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { 
        $finished_events[] = $row; 
    }
}
$total_events = count($finished_events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Finished Events - PRT System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-blue: #2563EB; 
            --gold: #F59E0B; 
            --bg-light: #F8FAFC; 
            --text-main: #0F172A;
            --text-muted: #64748B;
        }
        /* 修改了 padding-bottom，因为不再需要给 Footer 留出空间 */
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; padding-bottom: 40px; overflow-x: hidden; }

        /* Top Navigation */
        .top-nav { padding: 20px; display: flex; align-items: center; justify-content: space-between; background: var(--bg-light); position: sticky; top: 0; z-index: 100; }
        .back-btn { width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-main); text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s; }
        .back-btn:active { transform: scale(0.95); }
        .page-title { font-weight: 800; font-size: 18px; margin: 0; }

        /* Summary Card */
        .summary-card {
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
            border-radius: 24px; padding: 25px; margin: 0 20px 25px;
            color: white; box-shadow: 0 15px 30px rgba(37, 99, 235, 0.2);
            position: relative; overflow: hidden;
        }
        .summary-card::after { content: '\f091'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; right: -20px; bottom: -20px; font-size: 120px; color: rgba(255,255,255,0.05); transform: rotate(-15deg); }
        .stat-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.7); }
        .stat-value { font-size: 28px; font-weight: 900; letter-spacing: -1px; margin-bottom: 5px; }

        /* Event List */
        .container { padding: 0 20px; }
        .event-card {
            background: white; border-radius: 20px; margin-bottom: 15px;
            display: flex; overflow: hidden; text-decoration: none; color: inherit;
            box-shadow: 0 10px 20px rgba(0,0,0,0.03); border: 1px solid #F1F5F9;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .event-card:hover, .event-card:active { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        
        .event-img { width: 100px; height: 100px; object-fit: cover; flex-shrink: 0; background: #eee; }
        .event-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
        
        .event-type { font-size: 9px; font-weight: 900; padding: 3px 8px; border-radius: 6px; display: inline-block; margin-bottom: 6px; letter-spacing: 0.5px; }
        .type-physical { background: #FEF3C7; color: #D97706; }
        .type-virtual { background: #EFF6FF; color: #2563EB; }
        
        .event-title { font-size: 15px; font-weight: 800; margin: 0 0 5px; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .event-meta { font-size: 11px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 10px; }
        
        .finish-time-badge { background: #ECFDF5; color: #10B981; padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 800; font-family: monospace; display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; align-self: flex-start; }

        /* Empty State */
        .empty-state { text-align: center; padding: 50px 20px; }
        .empty-state i { font-size: 50px; color: #CBD5E1; margin-bottom: 15px; }
        .empty-state h3 { font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; }
        .empty-state p { font-size: 13px; color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="profile.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title">Finished Events</h1>
        <div style="width: 40px;"></div> </div>

    <div class="summary-card">
        <div class="row">
            <div class="col-6 mb-3">
                <div class="stat-label">Total Medals</div>
                <div class="stat-value"><?php echo $total_events; ?></div>
            </div>
            <div class="col-6 mb-3">
                <div class="stat-label">Distance</div>
                <div class="stat-value"><?php echo number_format($total_dist, 1); ?><span style="font-size:14px; font-weight:600; margin-left:2px;">KM</span></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-fire" style="color: #F87171;"></i>
            <span style="font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.9);">Burned <?php echo number_format($total_cal); ?> Kcal across all races</span>
        </div>
    </div>

    <div class="container">
        <h4 style="font-size: 14px; font-weight: 900; text-transform: uppercase; color: #64748B; letter-spacing: 1px; margin-bottom: 15px;">Your Hall of Fame</h4>

        <?php if ($total_events > 0): ?>
            <?php foreach ($finished_events as $evt): 
                $is_virtual = ($evt['event_type'] == 'virtual');
            ?>
                <a href="event_details.php?id=<?php echo $evt['event_id']; ?>" class="event-card">
                    <img src="<?php echo !empty($evt['banner_image']) ? $evt['banner_image'] : '../assets/placeholder.jpg'; ?>" class="event-img" onerror="this.src='../assets/placeholder.jpg'">
                    <div class="event-info">
                        <div>
                            <span class="event-type <?php echo $is_virtual ? 'type-virtual' : 'type-physical'; ?>">
                                <?php echo $is_virtual ? 'VIRTUAL' : 'PHYSICAL'; ?>
                            </span>
                        </div>
                        <h3 class="event-title"><?php echo htmlspecialchars($evt['title']); ?></h3>
                        <div class="event-meta">
                            <span><i class="far fa-calendar-check me-1"></i> <?php echo date("d M Y", strtotime($evt['finish_time'])); ?></span>
                            <span><i class="fas fa-route me-1"></i> <?php echo $evt['target_distance_km']; ?> KM</span>
                        </div>
                        
                        <?php if (!$is_virtual && $evt['duration_sec'] !== null): ?>
                            <div class="finish-time-badge">
                                <i class="fas fa-stopwatch"></i> <?php echo gmdate("H:i:s", $evt['duration_sec']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No finished events yet</h3>
                <p>Join a challenge, hit the finish line, and your glorious records will appear right here.</p>
                <a href="events.php" class="btn btn-primary fw-bold mt-3 rounded-pill px-4">Explore Events</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>