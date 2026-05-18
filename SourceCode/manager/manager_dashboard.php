<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['name'];
$default_avatar = "../assets/default_avatar.jpg"; 

// --- 2. 获取 Manager 的活动列表 ---
$sql = "SELECT e.*, (SELECT COUNT(*) FROM participations WHERE event_id = e.event_id) as runner_count 
        FROM events e WHERE e.manager_id = ? ORDER BY e.event_date DESC";
$events = [];
$total_runners = 0;
$active_events = 0;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
        $total_runners += $row['runner_count'];
        if($row['status'] === 'active') {
            $active_events++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manager Dashboard | PRT System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 统一使用 Admin 的颜色规范，把 Primary 改为原本 Manager 的绿色调以作区分 */
        :root { --primary: #10B981; --primary-dark: #059669; --dark: #0F172A; --bg: #F8FAFC; --sidebar: #1E293B; --success: #10B981; --warning: #F59E0B; --danger: #EF4444; --virtual: #3B82F6; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dark); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* Sidebar & Overlay (与 Admin 完全一致) */
        .sidebar { width: 280px; background: var(--sidebar); color: white; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2000; position: fixed; top: 0; bottom: 0; left: 0; }
        .sidebar-header { padding: 30px 25px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-links { padding: 20px; list-style: none; margin: 0; flex: 1; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: #94A3B8; text-decoration: none; border-radius: 12px; font-weight: 600; margin-bottom: 8px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; backdrop-filter: blur(2px); }
        .sidebar-overlay.active { display: block; }

        /* Mobile Header */
        .mobile-header { display: none; background: white; padding: 15px 20px; width: 100%; position: fixed; top: 0; left: 0; z-index: 1500; box-shadow: 0 2px 10px rgba(0,0,0,0.05); align-items: center; justify-content: space-between; height: 65px; }
        .menu-toggle { font-size: 22px; cursor: pointer; color: var(--sidebar); }

        /* Main Content */
        .main-content { flex: 1; padding: 40px; margin-left: 280px; transition: 0.3s; width: 100%; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.5px; }

        /* Stats Grid (与 Admin 一致) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid #F1F5F9; display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 55px; height: 55px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Actions */
        .btn-action { padding: 12px 24px; border-radius: 14px; font-size: 14px; font-weight: 800; border: none; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2); }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }

        /* Event Cards (适配 Admin 边框风格) */
        .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px; }
        .event-card { background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid #F1F5F9; transition: 0.3s; display: flex; flex-direction: column; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.05); }
        .card-img-wrapper { height: 180px; position: relative; overflow: hidden; }
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .event-card:hover .card-img-wrapper img { transform: scale(1.05); }
        
        .badge-container { position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
        .type-pill { padding: 6px 12px; border-radius: 10px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2); }
        .type-physical { background: rgba(16, 185, 129, 0.9); color: white; }
        .type-virtual { background: rgba(59, 130, 246, 0.9); color: white; }

        .card-body { padding: 25px; flex: 1; }
        .evt-title { font-size: 18px; font-weight: 800; margin: 0 0 12px; line-height: 1.3; }
        .evt-info { font-size: 14px; color: #64748B; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .evt-info i { color: var(--primary); width: 16px; text-align: center; }
        .evt-info.virtual-mode i { color: var(--virtual); }

        .card-actions { padding: 20px 25px 25px; display: flex; gap: 10px; border-top: 1px solid #F1F5F9; }
        .btn-sm { flex: 1; padding: 12px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 800; text-align: center; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-manage { background: #F1F5F9; color: var(--dark); }
        .btn-manage:hover { background: #E2E8F0; }
        .btn-nfc { background: #8B5CF6; color: white; }
        .btn-nfc:hover { background: #7C3AED; }
        .btn-virtual-info { background: #EFF6FF; color: var(--virtual); border: 1px dashed rgba(59, 130, 246, 0.3); }

        /* Profile Sidebar Element */
        .avatar-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 100px 20px 40px; }
            .mobile-header { display: flex; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 24px; }
        }
        @media (max-width: 480px) {
            .header-bar { flex-direction: column; align-items: flex-start; }
            .btn-action { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="mobile-header">
        <div style="font-weight: 800; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-bolt text-primary" style="color: var(--primary);"></i> MANAGER
        </div>
        <div class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars-staggered"></i></div>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><i class="fas fa-bolt me-2" style="color: var(--primary);"></i> PRT Manager</div>
        <ul class="nav-links">
            <li><a href="manager_dashboard.php" class="nav-link active"><i class="fas fa-house-chimney-window"></i> Dashboard</a></li>
            <li><a href="create_event.php" class="nav-link"><i class="fas fa-circle-plus"></i> New Event</a></li>
        </ul>
        
        <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); margin-top: auto;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div class="avatar-img" style="border: 2px solid rgba(255,255,255,0.1);">
                    <?= strtoupper(substr($manager_name, 0, 1)); ?>
                </div>
                <div style="font-size: 13px; overflow: hidden;">
                    <div style="font-weight: 700; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?= htmlspecialchars($manager_name) ?></div>
                    <div style="opacity: 0.5; font-size: 11px;">Event Lead</div>
                </div>
            </div>
            <a href="../logout.php" class="nav-link" style="color: var(--danger);"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="header-bar">
            <div>
                <h1 class="page-title">Hello, <?= explode(' ', trim($manager_name))[0]; ?>! 👋</h1>
                <p style="color: #64748B; margin: 8px 0 0; font-weight: 600; font-size: 14px;">Manage your deployments and runners.</p>
            </div>
            <a href="create_event.php" class="btn-action btn-primary">
                <i class="fas fa-plus"></i> Create Event
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #F0FDF4; color: var(--primary);"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3 style="margin:0; font-size: 24px; font-weight: 800;"><?= $total_runners ?></h3>
                    <p style="margin: 4px 0 0; color: #64748B; font-size: 13px; font-weight: 600;">Total Runners</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #EFF6FF; color: var(--virtual);"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3 style="margin:0; font-size: 24px; font-weight: 800;"><?= $active_events ?></h3>
                    <p style="margin: 4px 0 0; color: #64748B; font-size: 13px; font-weight: 600;">Active Deployments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #F8FAFC; color: #64748B;"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3 style="margin:0; font-size: 24px; font-weight: 800;"><?= count($events) ?></h3>
                    <p style="margin: 4px 0 0; color: #64748B; font-size: 13px; font-weight: 600;">Total Events</p>
                </div>
            </div>
        </div>

        <h2 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fas fa-rocket text-primary me-2"></i> Your Event Deployments</h2>
        
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div style="grid-column: 1 / -1; padding: 40px; text-align: center; background: white; border-radius: 24px; border: 1px solid #F1F5F9; color: #94A3B8;">
                    No events found. Start by creating a new event!
                </div>
            <?php else: ?>
                <?php foreach ($events as $index => $evt): 
                    $is_virtual = ($evt['event_type'] === 'virtual');
                ?>
                    <div class="event-card" style="animation: fadeIn 0.5s ease forwards <?= $index * 0.1 ?>s; opacity: 0;">
                        <div class="card-img-wrapper">
                            <?php if(!empty($evt['banner_image'])): ?>
                                <img src="<?php echo $evt['banner_image']; ?>" alt="Banner">
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#F8FAFC; color:#CBD5E1;">
                                    <i class="fas fa-image fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="badge-container">
                                <div class="type-pill <?php echo $is_virtual ? 'type-virtual' : 'type-physical'; ?>">
                                    <i class="<?php echo $is_virtual ? 'fas fa-cloud' : 'fas fa-location-dot'; ?> me-1"></i>
                                    <?php echo $is_virtual ? 'Virtual' : 'Physical'; ?>
                                </div>
                                <?php if($evt['status'] === 'active'): ?>
                                    <div class="type-pill" style="background: white; color: var(--primary);">ACTIVE</div>
                                <?php elseif($evt['status'] === 'pending'): ?>
                                    <div class="type-pill" style="background: white; color: var(--warning);">PENDING</div>
                                <?php else: ?>
                                    <div class="type-pill" style="background: white; color: #64748B;"><?= strtoupper($evt['status']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <h3 class="evt-title"><?php echo htmlspecialchars($evt['title']); ?></h3>
                            <div class="evt-info"><i class="far fa-calendar-check"></i> <?php echo date("d M Y", strtotime($evt['event_date'])); ?></div>
                            <div class="evt-info <?php echo $is_virtual ? 'virtual-mode' : ''; ?>">
                                <i class="fas fa-users"></i> <b><?php echo $evt['runner_count']; ?></b> Participants Joined
                            </div>
                            <div class="evt-info <?php echo $is_virtual ? 'virtual-mode' : ''; ?>">
                                <i class="<?php echo $is_virtual ? 'fas fa-microchip' : 'fas fa-flag-checkered'; ?>"></i> 
                                <?php echo $evt['target_distance_km']; ?> KM <?php echo $is_virtual ? 'GPS Auto-Log' : 'Track Distance'; ?>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="event_details.php?id=<?php echo $evt['event_id']; ?>" class="btn-sm btn-manage">
                                <i class="fas fa-gear"></i> Manage
                            </a>
                            
                            <?php if (!$is_virtual): ?>
                                <a href="nfc_manager.php?event_id=<?php echo $evt['event_id']; ?>" class="btn-sm btn-nfc">
                                    <i class="fas fa-rss"></i> NFC
                                </a>
                            <?php else: ?>
                                <div class="btn-sm btn-virtual-info">
                                    <i class="fas fa-satellite-dish"></i> Virtual
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>