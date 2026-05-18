<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. 权限拦截
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized_admin");
    exit();
}

$admin_name = $_SESSION['name'];
$msg = $_GET['msg'] ?? "";
$tab = $_GET['tab'] ?? 'dashboard'; 
$default_avatar = "../assets/default_avatar.jpg"; 

// --- 2. 数据抓取 ---
if ($tab == 'dashboard') {
    $stats = [
        'users' => $conn->query("SELECT COUNT(*) FROM users WHERE role='runner'")->fetch_row()[0],
        'active_events' => $conn->query("SELECT COUNT(*) FROM events WHERE status='active'")->fetch_row()[0],
        'pending_events' => $conn->query("SELECT COUNT(*) FROM events WHERE status='pending'")->fetch_row()[0]
    ];
    // 待审核赛事
    $pending_events = $conn->query("SELECT e.*, u.name as manager_name FROM events e JOIN users u ON e.manager_id = u.user_id WHERE e.status = 'pending' ORDER BY e.created_at ASC");
    // 待审核完赛记录
    $pending_runs = $conn->query("SELECT p.*, u.name as runner_name, u.avatar as runner_avatar, e.title as event_title FROM participations p JOIN users u ON p.user_id = u.user_id JOIN events e ON p.event_id = e.event_id WHERE p.status = 'completed' ORDER BY p.updated_at DESC");
}

if ($tab == 'users') {
    $all_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
}

if ($tab == 'events') {
    $all_events = $conn->query("SELECT e.*, u.name as manager_name FROM events e LEFT JOIN users u ON e.manager_id = u.user_id ORDER BY e.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Dashboard | PRT System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --dark: #0F172A; --bg: #F8FAFC; --sidebar: #1E293B; --success: #10B981; --warning: #F59E0B; --danger: #EF4444; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dark); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* Sidebar & Overlay */
        .sidebar { width: 280px; background: var(--sidebar); color: white; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2000; position: fixed; top: 0; bottom: 0; left: 0; }
        .sidebar-header { padding: 30px 25px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-links { padding: 20px; list-style: none; margin: 0; flex: 1; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: #94A3B8; text-decoration: none; border-radius: 12px; font-weight: 600; margin-bottom: 8px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3); }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; backdrop-filter: blur(2px); }
        .sidebar-overlay.active { display: block; }

        /* Mobile Header */
        .mobile-header { display: none; background: white; padding: 15px 20px; width: 100%; position: fixed; top: 0; left: 0; z-index: 1500; box-shadow: 0 2px 10px rgba(0,0,0,0.05); align-items: center; justify-content: space-between; height: 65px; }
        .menu-toggle { font-size: 22px; cursor: pointer; color: var(--sidebar); }

        .main-content { flex: 1; padding: 40px; margin-left: 280px; transition: 0.3s; width: 100%; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.5px; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid #F1F5F9; display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 55px; height: 55px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Table & Cards */
        .table-container { background: white; border-radius: 24px; border: 1px solid #F1F5F9; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: #F8FAFC; text-align: left; padding: 18px 25px; font-size: 11px; font-weight: 800; color: #64748B; text-transform: uppercase; }
        td { padding: 18px 25px; border-bottom: 1px solid #F1F5F9; font-size: 14px; font-weight: 600; }
        
        .avatar-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; background: #F1F5F9; flex-shrink: 0; }
        .badge { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        .badge-pending { background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5; }
        .badge-active { background: #F0FDF4; color: #15803D; border: 1px solid #DCFCE7; }
        
        /* Action Button Styling */
        .btn-action { padding: 10px 20px; border-radius: 12px; font-size: 12px; font-weight: 800; border: none; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-review { background: var(--primary); color: white; box-shadow: 0 8px 15px rgba(37, 99, 235, 0.15); }
        .btn-review:hover { background: #1D4ED8; transform: translateY(-2px); }
        .btn-view { background: #EFF6FF; color: var(--primary); }

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
            <i class="fas fa-shield-alt text-primary" style="color: var(--primary);"></i> ADMIN
        </div>
        <div class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars-staggered"></i></div>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><i class="fas fa-shield-alt me-2"></i> PRT Admin</div>
        <ul class="nav-links">
            <li><a href="?tab=dashboard" class="nav-link <?= ($tab=='dashboard')?'active':''; ?>"><i class="fas fa-house-chimney-window"></i> Dashboard</a></li>
            <li><a href="?tab=users" class="nav-link <?= ($tab=='users')?'active':''; ?>"><i class="fas fa-user-group"></i> Users</a></li>
            <li><a href="?tab=events" class="nav-link <?= ($tab=='events')?'active':''; ?>"><i class="fas fa-calendar-days"></i> Events</a></li>
        </ul>
        <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); margin-top: auto;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <img src="<?= $default_avatar ?>" class="avatar-img" style="border: 2px solid rgba(255,255,255,0.1);">
                <div style="font-size: 13px; overflow: hidden;">
                    <div style="font-weight: 700; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?= htmlspecialchars($admin_name) ?></div>
                    <div style="opacity: 0.5; font-size: 11px;">Super Admin</div>
                </div>
            </div>
            <a href="../logout.php" class="nav-link" style="color: var(--danger);"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($tab == 'dashboard'): ?>
            <div class="header-bar">
                <h1 class="page-title">Dashboard Overview</h1>
                <?php if($msg): ?><span class="badge badge-active" style="padding: 10px 15px;"><?= htmlspecialchars($msg) ?></span><?php endif; ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #EFF6FF; color: var(--primary);"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><h3><?= $stats['users'] ?></h3><p>Total Runners</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #F0FDF4; color: var(--success);"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info"><h3><?= $stats['active_events'] ?></h3><p>Active Events</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FFF7ED; color: var(--warning);"><i class="fas fa-clock-rotate-left"></i></div>
                    <div class="stat-info"><h3><?= $stats['pending_events'] ?></h3><p>Pending Review</p></div>
                </div>
            </div>

            <h2 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fas fa-clipboard-list text-warning me-2"></i> Pending Event Requests</h2>
            <div class="table-container">
                <div class="table-responsive">
                    <?php if ($pending_events->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>Event Title</th><th>Manager</th><th>Type</th><th>Review Status</th></tr></thead>
                            <tbody>
                                <?php while($row = $pending_events->fetch_assoc()): ?>
                                    <tr>
                                        <td><div style="font-weight:800;"><?= htmlspecialchars($row['title']) ?></div><small><?= $row['target_distance_km'] ?>KM</small></td>
                                        <td><?= htmlspecialchars($row['manager_name']) ?></td>
                                        <td><span class="badge" style="background:#F1F5F9;"><?= strtoupper($row['event_type']) ?></span></td>
                                        <td>
                                            <a href="event_details.php?id=<?= $row['event_id'] ?>" class="btn-action btn-review">
                                                <i class="fas fa-magnifying-glass"></i> Review & Audit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #94A3B8;">No new event requests to audit.</div>
                    <?php endif; ?>
                </div>
            </div>

            <h2 style="font-size: 18px; font-weight: 800; margin-top: 40px; margin-bottom: 20px;"><i class="fas fa-stopwatch text-primary me-2"></i> Recent Completions</h2>
            <div class="table-container">
                <div class="table-responsive">
                    <?php if ($pending_runs->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>Runner</th><th>Event Title</th><th>Status</th><th>Audit</th></tr></thead>
                            <tbody>
                                <?php while($p = $pending_runs->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <img src="<?= !empty($p['runner_avatar']) ? '../uploads/avatars/'.$p['runner_avatar'] : $default_avatar ?>" class="avatar-img" onerror="this.src='<?= $default_avatar ?>'">
                                                <div style="font-weight:800;"><?= htmlspecialchars($p['runner_name']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($p['event_title']) ?></td>
                                        <td><span class="badge" style="background:#FEF3C7; color:#92400E;">COMPLETED</span></td>
                                        <td><a href="admin_verify_run.php?pid=<?= $p['participation_id'] ?>" class="btn-action btn-view"><i class="fas fa-file-invoice"></i> View Audit</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #94A3B8;">No runner completions waiting for review.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'users'): ?>
            <div class="header-bar">
                <h1 class="page-title">Users Directory</h1>
                <input type="text" id="userSearch" onkeyup="filterTable('userSearch', 'userTable')" style="padding: 12px 20px; border-radius: 12px; border: 1px solid #E2E8F0; width: 100%; max-width: 300px;" placeholder="Search runners...">
            </div>
            <div class="table-container">
                <div class="table-responsive">
                    <table id="userTable">
                        <thead><tr><th>User Info</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                        <tbody>
                            <?php while($u = $all_users->fetch_assoc()): 
                                $u_avatar = !empty($u['avatar']) ? (strpos($u['avatar'], 'http') === 0 ? $u['avatar'] : "../uploads/avatars/".$u['avatar']) : $default_avatar;
                            ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <img src="<?= $u_avatar ?>" class="avatar-img" onerror="this.src='<?= $default_avatar ?>'">
                                            <div style="font-weight:800;"><?= htmlspecialchars($u['name']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><span class="badge" style="background:#F1F5F9; color:#475569;"><?= strtoupper($u['role']) ?></span></td>
                                    <td><?= date("M Y", strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'events'): ?>
            <div class="header-bar">
                <h1 class="page-title">Events History</h1>
                <input type="text" id="eventSearch" onkeyup="filterTable('eventSearch', 'eventTable')" style="padding: 12px 20px; border-radius: 12px; border: 1px solid #E2E8F0; width: 100%; max-width: 300px;" placeholder="Search events...">
            </div>
            <div class="table-container">
                <div class="table-responsive">
                    <table id="eventTable">
                        <thead><tr><th>Title</th><th>Manager</th><th>Goal</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if ($all_events && $all_events->num_rows > 0): ?>
                                <?php while($e = $all_events->fetch_assoc()): ?>
                                    <tr>
                                        <td><div style="font-weight: 800;"><?= htmlspecialchars($e['title']) ?></div><small><?= strtoupper($e['event_type']) ?></small></td>
                                        <td><?= htmlspecialchars($e['manager_name'] ?? 'N/A') ?></td>
                                        <td><?= $e['target_distance_km'] ?> KM</td>
                                        <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                                        <td><a href="event_details.php?id=<?= $e['event_id'] ?>" class="btn-action btn-view">Review Detail</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="padding: 40px; text-align: center; color: #94A3B8;">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function toggleSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function filterTable(inputId, tableId) {
            let input = document.getElementById(inputId);
            let filter = input.value.toUpperCase();
            let table = document.getElementById(tableId);
            let tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                let txt = tr[i].textContent || tr[i].innerText;
                tr[i].style.display = (txt.toUpperCase().indexOf(filter) > -1) ? "" : "none";
            }
        }
    </script>
</body>
</html>