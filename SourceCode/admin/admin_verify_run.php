<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized_admin");
    exit();
}

$admin_name = $_SESSION['name'];
$msg = "";
$err = "";

if (!isset($_GET['pid'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$pid = intval($_GET['pid']);

// --- 1. 处理审核操作 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'];
    $new_status = ($decision === 'verify') ? 'verified' : 'ready'; // 拒绝则退回到 ready 让其重新跑或找 Manager
    
    $stmt = $conn->prepare("UPDATE participations SET status = ? WHERE participation_id = ?");
    $stmt->bind_param("si", $new_status, $pid);
    if ($stmt->execute()) {
        $msg = ($decision === 'verify') ? "✅ Run Verified Successfully!" : "❌ Run Rejected.";
    } else {
        $err = "Database error occurred.";
    }
}

// --- 2. 获取详细数据 (包含时间戳和 NFC 信息) ---
$sql = "SELECT p.*, u.name as runner_name, u.email as runner_email, u.avatar,
               e.title as event_title, e.target_distance_km, e.event_type
        FROM participations p 
        JOIN users u ON p.user_id = u.user_id 
        JOIN events e ON p.event_id = e.event_id 
        WHERE p.participation_id = ?";
$data = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
}

if (!$data) die("Record not found.");

// --- 3. 智能作弊分析逻辑 ---
$duration_mins = 0;
$avg_pace = 0;
$is_suspicious = false;

if (!empty($data['nfc_verified_at']) && !empty($data['finish_time'])) {
    $start = strtotime($data['nfc_verified_at']);
    $end = strtotime($data['finish_time']);
    $duration_seconds = $end - $start;
    $duration_mins = round($duration_seconds / 60, 2);
    
    if ($data['current_km'] > 0) {
        $avg_pace = $duration_mins / $data['current_km'];
        // 如果配速快于 3分钟/KM (职业运动员水平)，标记为可疑
        if ($avg_pace < 3.0) $is_suspicious = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Verify Run | PRT Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --bg: #F8FAFC; }
        body { background: var(--bg); margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; color: #1E293B; padding-bottom: 50px; }
        
        .top-nav { background: white; padding: 15px 25px; border-bottom: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .btn-back { text-decoration: none; color: #64748B; font-weight: 700; font-size: 14px; }

        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        
        /* 选手概览卡片 */
        .card { background: white; border-radius: 24px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid #F1F5F9; margin-bottom: 20px; }
        .runner-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; background: #F1F5F9; }
        
        /* 数据网格 */
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
        .data-item { background: #F8FAFC; padding: 15px; border-radius: 16px; border: 1px solid #F1F5F9; }
        .data-label { font-size: 11px; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-bottom: 5px; }
        .data-value { font-size: 16px; font-weight: 700; color: #1E293B; }

        /* 状态指示器 */
        .badge { padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 800; }
        .bg-success { background: #DCFCE7; color: #166534; }
        .bg-warning { background: #FFF7ED; color: #C2410C; }

        /* 预警样式 */
        .suspicious-box { background: #FEF2F2; border: 2px solid #FEE2E2; padding: 20px; border-radius: 20px; margin-bottom: 25px; display: flex; gap: 15px; align-items: center; color: #991B1B; }
        
        .audit-timeline { position: relative; padding-left: 30px; margin: 20px 0; }
        .timeline-item { position: relative; padding-bottom: 25px; }
        .timeline-item::before { content: ""; position: absolute; left: -21px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 3px solid white; box-shadow: 0 0 0 2px var(--primary); }
        .timeline-item:not(:last-child)::after { content: ""; position: absolute; left: -16px; top: 20px; width: 2px; height: 100%; background: #E2E8F0; }

        .action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 30px; }
        .btn { padding: 18px; border-radius: 18px; border: none; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; }
        .btn-approve { background: var(--primary); color: white; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2); }
        .btn-reject { background: #F1F5F9; color: var(--danger); }

        @media (max-width: 600px) { .data-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-2"></i> Back</a>
    <div style="font-weight: 800;">Digital Run Audit</div>
    <div style="width: 40px;"></div>
</nav>

<div class="container">
    <?php if($msg): ?>
        <div class="card" style="background: var(--success); color: white; text-align: center; font-weight: 800;"><?= $msg ?></div>
    <?php endif; ?>

   <div class="card">
    <div class="runner-profile">
        <?php 
            // 智能头像路径判断
            $avatar_path = '../assets/default_avatar.jpg'; // 默认头像
            if (!empty($data['avatar'])) {
                // 如果数据库里存的是完整的 http 链接
                if (strpos($data['avatar'], 'http') === 0) {
                    $avatar_path = $data['avatar'];
                } else {
                    // 如果存的是文件名，拼接上传文件夹路径
                    $avatar_path = '../uploads/avatars/' . $data['avatar'];
                }
            }
        ?>
        <img src="<?= $avatar_path ?>" class="avatar" onerror="this.src='../assets/default_avatar.jpg'">
        <div>
            <div style="font-weight: 800; font-size: 18px;"><?= htmlspecialchars($data['runner_name']) ?></div>
            <div style="font-size: 13px; color: #64748B;"><?= htmlspecialchars($data['runner_email']) ?></div>
        </div>
    </div>
    <div style="display: flex; gap: 10px;">
        <span class="badge bg-warning"><?= strtoupper($data['event_type']) ?></span>
        <span class="badge bg-success"><?= strtoupper($data['status']) ?></span>
    </div>
</div>

    <?php if($is_suspicious): ?>
    <div class="suspicious-box">
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        <div>
            <div style="font-weight: 800;">High Speed Detected!</div>
            <div style="font-size: 13px; opacity: 0.8;">The average pace is <strong><?= number_format($avg_pace, 2) ?>' /km</strong>. This may indicate the use of a vehicle.</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin: 0 0 20px; font-size: 16px; font-weight: 800;"><i class="fas fa-fingerprint text-primary me-2"></i> System Digital Audit</h3>
        
        <div class="data-grid">
            <div class="data-item">
                <div class="data-label">Total Distance</div>
                <div class="data-value"><?= number_format($data['current_km'], 2) ?> KM</div>
            </div>
            <div class="data-item">
                <div class="data-label">Target Goal</div>
                <div class="data-value"><?= $data['target_distance_km'] ?> KM</div>
            </div>
            <div class="data-item">
                <div class="data-label">Avg Pace</div>
                <div class="data-value"><?= $avg_pace > 0 ? number_format($avg_pace, 2)."' /km" : "--" ?></div>
            </div>
            <div class="data-item">
                <div class="data-label">NFC Chip ID</div>
                <div class="data-value"><?= $data['chip_id'] ?? 'N/A' ?></div>
            </div>
        </div>

        <div class="audit-timeline">
            <div class="timeline-item">
                <div class="data-label">NFC Tag Linked (Start)</div>
                <div class="data-value" style="font-size: 14px;"><?= !empty($data['nfc_verified_at']) ? date("d M Y, H:i:s", strtotime($data['nfc_verified_at'])) : 'Not Recorded' ?></div>
            </div>
            <div class="timeline-item">
                <div class="data-label">NFC Tag Scanned (Finish)</div>
                <div class="data-value" style="font-size: 14px;"><?= !empty($data['finish_time']) ? date("d M Y, H:i:s", strtotime($data['finish_time'])) : 'Not Recorded' ?></div>
            </div>
            <div class="timeline-item" style="padding-bottom: 0;">
                <div class="data-label">Total Active Duration</div>
                <div class="data-value" style="font-size: 14px;"><?= $duration_mins ?> Minutes</div>
            </div>
        </div>
    </div>

    <?php if ($data['status'] === 'completed'): ?>
        <form method="POST" class="action-btns">
            <button type="submit" name="decision" value="reject" class="btn btn-reject" onclick="return confirm('Reject this run? Status will return to Ready.')">
                <i class="fas fa-times-circle me-2"></i> Reject
            </button>
            <button type="submit" name="decision" value="verify" class="btn btn-approve" onclick="return confirm('Approve this run? Runner will receive their digital medal.')">
                <i class="fas fa-check-circle me-2"></i> Approve Run
            </button>
        </form>
    <?php else: ?>
        <div style="text-align: center; color: #94A3B8; font-weight: 700; margin-top: 30px;">
            <i class="fas fa-info-circle me-2"></i> This record is already <strong><?= strtoupper($data['status']) ?></strong>.
        </div>
    <?php endif; ?>
</div>

</body>
</html>