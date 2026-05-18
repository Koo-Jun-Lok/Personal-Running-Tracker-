<?php
// --- 1. Configuration ---
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['name']; // 获取 Manager 名字用于侧边栏
$msg = "";
$err = "";

// --- 2. Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_type = $_POST['event_type'];
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $dist = floatval($_POST['distance']);
    $e_date = $_POST['event_date']; 
    $end_date = ($event_type === 'virtual') ? $_POST['end_date'] : $e_date; 
    $s_time = $_POST['start_time'];
    $e_time = $_POST['end_time'];
    $route_instructions = trim($_POST['route_instructions']);
    $final_image_url = !empty($_POST['image_url']) ? $_POST['image_url'] : 'https://images.unsplash.com/photo-1452626038306-9aae5e071dd3?auto=format&fit=crop&w=800&q=80';

    $route_filename = "";

    if ($event_type === 'physical' && isset($_FILES['gpx_file']) && $_FILES['gpx_file']['error'] == 0) {
        $allowed = ['gpx'];
        $filename = $_FILES['gpx_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $new_name = "route_" . time() . "_" . uniqid() . ".gpx";
            $upload_dir = "../uploads/routes/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($_FILES['gpx_file']['tmp_name'], $upload_dir . $new_name)) {
                $route_filename = $new_name;
            } else { $err = "Error saving GPX file."; }
        } else { $err = "Invalid file type."; }
    }

    if (empty($err)) {
        // 状态为 'pending'
        $sql = "INSERT INTO events (manager_id, title, description, target_distance_km, event_date, end_date, start_time, end_time, banner_image, route_url, route_instructions, status, event_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issdssssssss", $manager_id, $title, $desc, $dist, $e_date, $end_date, $s_time, $e_time, $final_image_url, $route_filename, $route_instructions, $event_type);
            if ($stmt->execute()) { 
                $msg = "Event submitted! Please wait for Admin approval before it goes live."; 
            } else { 
                $err = "Database Error: " . $stmt->error; 
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Create Event | PRT Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 统一使用 UI 规范颜色 */
        :root { --primary: #10B981; --primary-dark: #059669; --dark: #0F172A; --bg: #F8FAFC; --sidebar: #1E293B; --success: #10B981; --danger: #EF4444; --virtual: #3B82F6; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dark); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* Sidebar & Overlay */
        .sidebar { width: 280px; background: var(--sidebar); color: white; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2000; position: fixed; top: 0; bottom: 0; left: 0; }
        .sidebar-header { padding: 30px 25px; font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-links { padding: 20px; list-style: none; margin: 0; flex: 1; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: #94A3B8; text-decoration: none; border-radius: 12px; font-weight: 600; margin-bottom: 8px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; backdrop-filter: blur(2px); }
        .sidebar-overlay.active { display: block; }
        .avatar-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }

        /* Mobile Header */
        .mobile-header { display: none; background: white; padding: 15px 20px; width: 100%; position: fixed; top: 0; left: 0; z-index: 1500; box-shadow: 0 2px 10px rgba(0,0,0,0.05); align-items: center; justify-content: space-between; height: 65px; }
        .menu-toggle { font-size: 22px; cursor: pointer; color: var(--sidebar); }

        /* Main Content */
        .main-content { flex: 1; padding: 40px; margin-left: 280px; transition: 0.3s; width: 100%; }
        .header-bar { margin-bottom: 30px; }
        .page-title { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.5px; }

        /* Content Grid */
        .content-grid { display: grid; grid-template-columns: 480px 1fr; gap: 30px; align-items: start; }
        
        /* Form Card */
        .form-card { background: white; border-radius: 24px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid #F1F5F9; }

        /* Map Card */
        .map-section { display: flex; flex-direction: column; gap: 15px; }
        .map-card { background: white; border-radius: 24px; overflow: hidden; border: 1px solid #F1F5F9; box-shadow: 0 4px 20px rgba(0,0,0,0.02); height: 800px; }
        iframe { width: 100%; height: 100%; border: none; }

        /* UI Elements */
        .type-selector { display: flex; gap: 10px; margin-bottom: 30px; background: #F8FAFC; padding: 8px; border-radius: 16px; border: 1px solid #E2E8F0; }
        .type-opt { flex: 1; text-align: center; padding: 12px; border-radius: 12px; cursor: pointer; font-size: 13px; font-weight: 800; transition: 0.3s; color: #64748B; }
        .type-opt.active[data-type="physical"] { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); }
        .type-opt.active[data-type="virtual"] { background: var(--virtual); color: white; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, .form-textarea { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1.5px solid #E2E8F0; font-size: 14px; font-family: inherit; color: var(--dark); transition: 0.3s; background: #F8FAFC; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .btn-submit { background: var(--primary); color: white; width: 100%; padding: 18px; border: none; border-radius: 16px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .upload-card { border: 2px dashed #CBD5E1; border-radius: 16px; padding: 20px; text-align: center; cursor: pointer; background: #F8FAFC; transition: 0.3s; }
        .upload-card:hover { border-color: var(--primary); background: #F0FDF4; }

        .alert-box { background: #F0FDF4; color: #166534; padding: 16px 20px; border-radius: 14px; margin-bottom: 25px; font-size: 14px; font-weight: 600; border: 1px solid #DCFCE7; display: flex; align-items: center; gap: 10px; }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
            .content-grid { grid-template-columns: 400px 1fr; }
        }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 100px 20px 40px; }
            .mobile-header { display: flex; }
            .content-grid { grid-template-columns: 1fr; }
            .map-card { height: 600px; }
        }
        @media (max-width: 480px) {
            .row { grid-template-columns: 1fr; }
            .form-card { padding: 25px; }
        }

        .hidden { display: none !important; }
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
            <li><a href="manager_dashboard.php" class="nav-link"><i class="fas fa-house-chimney-window"></i> Dashboard</a></li>
            <li><a href="create_event.php" class="nav-link active"><i class="fas fa-circle-plus"></i> New Event</a></li>
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
            <h1 class="page-title">Deploy Event</h1>
            <p style="color: #64748B; margin: 8px 0 0; font-weight: 600; font-size: 14px;">Set up a new run for the community.</p>
        </div>

        <div class="content-grid">
            <div class="form-card">
                <div class="type-selector">
                    <div class="type-opt active" data-type="physical" onclick="setType('physical')">Physical Run</div>
                    <div class="type-opt" data-type="virtual" onclick="setType('virtual')">Virtual Event</div>
                </div>

                <?php if($msg) echo "<div class='alert-box'><i class='fas fa-check-circle'></i> $msg</div>"; ?>

                <form id="createEventForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="event_type" id="event_type" value="physical">

                    <div class="form-group">
                        <label class="form-label">Event Title</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g. UUM Merdeka Run" required>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label class="form-label" id="date-label">Event Date</label>
                            <input type="date" name="event_date" class="form-input" required>
                        </div>
                        <div class="form-group" id="end-date-group" style="display:none;">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Distance (KM)</label>
                            <input type="number" step="0.1" name="distance" class="form-input" placeholder="5.0" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-input" value="08:00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-input" value="23:59" required>
                        </div>
                    </div>

                    <div id="physical-only-fields">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-file-import text-primary"></i> GPS Route (.GPX)</label>
                            <div class="upload-card" onclick="document.getElementById('gpx_file').click()">
                                <i class="fas fa-cloud-arrow-up" style="color:#94A3B8; font-size: 24px; margin-bottom: 10px;"></i>
                                <div id="gpx-status" style="font-size:13px; font-weight:700; color: #64748B;">Click to upload GPX file</div>
                                <input type="file" name="gpx_file" id="gpx_file" accept=".gpx" style="display:none;" onchange="handleGpxSelect(this)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assembly Instructions</label>
                            <textarea name="route_instructions" class="form-textarea" rows="2" placeholder="Where to gather? e.g. Dataran Palapes"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Rules, rewards, and details..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Banner Poster (Image)</label>
                        <input type="file" id="image-input" accept="image/*" class="form-input" style="background: white; padding: 10px;">
                        <input type="hidden" name="image_url" id="inp-image-url">
                    </div>

                    <button type="submit" class="btn-submit" id="btn-submit">Publish Event</button>
                </form>
            </div>

            <div class="map-section" id="map-section">
                <div style="background: white; padding: 16px 20px; border-radius: 16px; border: 1px solid #F1F5F9; font-size: 13px; display: flex; align-items: center; gap: 12px; font-weight: 600; color: #475569; box-shadow: 0 4px 10px rgba(0,0,0,0.01);">
                    <i class="fas fa-map-marked-alt" style="color: var(--primary); font-size: 18px;"></i>
                    <span>Route Builder: Plan your course and export the GPX file.</span>
                </div>
                <div class="map-card">
                    <iframe src="https://brouter.de/brouter-web/#map=15/6.4582/100.5041/standard" allow="geolocation"></iframe>
                </div>
            </div>
        </div>
    </main>

    <script>
        const IMGBB_API_KEY = 'f8bbc81c32e4ebae4619166a269ad997';

        function toggleSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function setType(type) {
            document.getElementById('event_type').value = type;
            document.querySelectorAll('.type-opt').forEach(opt => opt.classList.remove('active'));
            document.querySelector(`.type-opt[data-type="${type}"]`).classList.add('active');

            const physicalFields = document.getElementById('physical-only-fields');
            const mapSection = document.getElementById('map-section');
            const endDateGroup = document.getElementById('end-date-group');
            const dateLabel = document.getElementById('date-label');
            const btn = document.getElementById('btn-submit');

            if (type === 'virtual') {
                physicalFields.style.display = 'none';
                mapSection.classList.add('hidden');
                endDateGroup.style.display = 'block';
                dateLabel.innerText = "Start Date";
                
                // Switch to Blue Virtual Theme
                btn.style.background = "var(--virtual)";
                btn.style.boxShadow = "0 10px 20px rgba(59, 130, 246, 0.2)";
                btn.onmouseover = () => btn.style.transform = "translateY(-2px)";
                btn.onmouseout = () => btn.style.transform = "translateY(0)";
            } else {
                physicalFields.style.display = 'block';
                mapSection.classList.remove('hidden');
                endDateGroup.style.display = 'none';
                dateLabel.innerText = "Event Date";
                
                // Switch back to Green Primary Theme
                btn.style.background = "var(--primary)";
                btn.style.boxShadow = "0 10px 20px rgba(16, 185, 129, 0.2)";
            }
        }

        function handleGpxSelect(input) {
            if (input.files && input.files[0]) {
                const statusDiv = document.getElementById('gpx-status');
                statusDiv.innerHTML = "<i class='fas fa-check'></i> " + input.files[0].name;
                statusDiv.style.color = "var(--primary)";
            }
        }

        document.getElementById('createEventForm').onsubmit = function(e) {
            const fileInput = document.getElementById('image-input');
            const btn = document.getElementById('btn-submit');
            const urlInput = document.getElementById('inp-image-url');
            
            if (fileInput.files && fileInput.files[0] && !urlInput.value) {
                e.preventDefault();
                btn.disabled = true; 
                btn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Uploading...";
                btn.style.opacity = "0.7";
                
                const formData = new FormData();
                formData.append('image', fileInput.files[0]);
                formData.append('key', IMGBB_API_KEY);
                
                fetch('https://api.imgbb.com/1/upload', { method: 'POST', body: formData })
                .then(res => res.json()).then(data => {
                    if (data.success) {
                        urlInput.value = data.data.url;
                        document.getElementById('createEventForm').submit();
                    } else { 
                        alert('Upload failed'); 
                        btn.disabled = false; 
                        btn.innerHTML = "Publish Challenge";
                        btn.style.opacity = "1";
                    }
                }).catch(err => {
                    alert('Network error during upload');
                    btn.disabled = false; 
                    btn.innerHTML = "Publish Challenge";
                    btn.style.opacity = "1";
                });
            }
        };
    </script>
</body>
</html>