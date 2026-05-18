<?php
require_once '../auth_check.php'; 
require_once '../db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: manager_dashboard.php");
    exit();
}

$event_id = intval($_GET['id']);
$manager_id = $_SESSION['user_id'];
$msg = "";
$err = "";

// 2. Fetch Existing Data (包含新增的 route 字段)
$sql = "SELECT * FROM events WHERE event_id = ? AND manager_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $event_id, $manager_id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
}

if (!$evt) { die("Event not found or access denied."); }

// 3. Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $dist = floatval($_POST['distance']);
    $e_date = $_POST['event_date'];
    $s_time = $_POST['start_time'];
    $e_time = $_POST['end_time'];
    $route_instructions = trim($_POST['route_instructions']);
    $img_url = $_POST['image_url']; 

    // 默认保持原有路线文件
    $route_filename = $evt['route_url'];

    // 处理新的 GPX 文件上传 (如果有)
    if (isset($_FILES['gpx_file']) && $_FILES['gpx_file']['error'] == 0) {
        $allowed = ['gpx'];
        $filename = $_FILES['gpx_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_name = "route_" . time() . "_" . uniqid() . ".gpx";
            $upload_dir = "../uploads/routes/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            if (move_uploaded_file($_FILES['gpx_file']['tmp_name'], $upload_dir . $new_name)) {
                // 如果上传成功，删除旧文件 (可选)
                if (!empty($evt['route_url']) && file_exists($upload_dir . $evt['route_url'])) {
                    unlink($upload_dir . $evt['route_url']);
                }
                $route_filename = $new_name;
            }
        } else {
            $err = "Invalid GPX file type.";
        }
    }

    if (empty($err)) {
        // 更新 SQL，包含 route_url 和 route_instructions
        $update_sql = "UPDATE events SET title=?, description=?, target_distance_km=?, event_date=?, start_time=?, end_time=?, banner_image=?, route_url=?, route_instructions=? WHERE event_id=? AND manager_id=?";
        
        if ($stmt = $conn->prepare($update_sql)) {
            // 参数顺序: title(s), desc(s), dist(d), date(s), start(s), end(s), img(s), route_url(s), route_ins(s), event_id(i), manager_id(i)
            $stmt->bind_param("ssdssssssii", $title, $desc, $dist, $e_date, $s_time, $e_time, $img_url, $route_filename, $route_instructions, $event_id, $manager_id);
            
            if ($stmt->execute()) {
                header("Location: event_details.php?id=$event_id&msg=updated");
                exit();
            } else {
                $err = "Error updating database: " . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit Challenge - PRT Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #2563EB; 
            --primary-hover: #1D4ED8;
            --bg: #F1F5F9; 
            --card: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
        }
        body { background: var(--bg); margin: 0; padding: 20px 20px 60px; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); }
        
        /* 顶部导航 */
        .top-nav { max-width: 700px; margin: 0 auto 20px; display: flex; align-items: center; gap: 15px; }
        .back-btn { width: 40px; height: 40px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-main); text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s; }
        .back-btn:hover { background: #F8FAFC; transform: translateY(-2px); }
        .page-title { font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px; }

        .container { max-width: 700px; margin: 0 auto; background: var(--card); padding: 40px; border-radius: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); border: 1px solid rgba(255,255,255,0.5); }
        
        .section-header { margin-bottom: 30px; }
        .section-header p { color: var(--text-muted); font-size: 14px; font-weight: 600; margin: 5px 0 0; }

        /* 表单控件 */
        .form-group { margin-bottom: 24px; }
        .label { display: block; font-weight: 800; font-size: 11px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .input, .textarea { 
            width: 100%; padding: 15px 18px; border: 2px solid transparent; 
            border-radius: 16px; box-sizing: border-box; font-size: 15px; font-family: inherit; font-weight: 600; color: var(--text-main);
            background: #F8FAFC; transition: all 0.3s ease; 
        }
        .input:focus, .textarea:focus { border-color: var(--primary); background: #FFFFFF; outline: none; box-shadow: 0 4px 15px rgba(37,99,235,0.08); }
        
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        @media (max-width: 600px) { .row-grid, .row-3 { grid-template-columns: 1fr; gap: 15px; } }

        /* Route Box 突出显示 */
        .route-edit-card { background: #EFF6FF; border: 2px dashed #93C5FD; padding: 25px; border-radius: 20px; margin-top: 15px; margin-bottom: 25px; }
        .route-edit-card .label { color: #1E3A8A; }
        .file-info { background: white; padding: 12px 15px; border-radius: 12px; font-size: 13px; font-weight: 700; color: #2563EB; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; border: 1px solid #DBEAFE; }
        .file-input-wrapper input[type=file] { background: white; padding: 10px; border-radius: 12px; width: 100%; font-size: 13px; font-weight: 600; color: var(--text-muted); cursor: pointer; }

        /* 按钮 */
        .btn-save { width: 100%; padding: 18px; background: var(--primary); color: white; border: none; border-radius: 16px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; margin-top: 10px; letter-spacing: 0.5px; box-shadow: 0 8px 20px rgba(37,99,235,0.2); }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 12px 25px rgba(37,99,235,0.3); }
        .btn-cancel { display: block; text-align: center; margin-top: 20px; color: var(--text-muted); text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.2s; }
        .btn-cancel:hover { color: var(--text-main); }
        
        .preview-banner { width: 100%; height: 160px; object-fit: cover; border-radius: 16px; margin-bottom: 10px; border: 1px solid #E2E8F0; }
        .alert-error { background: #FEF2F2; color: #EF4444; padding: 15px; border-radius: 14px; margin-bottom: 25px; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px; border: 1px solid #FECACA; }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="event_details.php?id=<?php echo $event_id; ?>" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1 class="page-title">Edit Challenge</h1>
    </div>

    <div class="container">
        <div class="section-header">
            <p>Update your event details, schedule, and GPX route file.</p>
        </div>

        <?php if ($err): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $err; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="label">Challenge Title</label>
                <input type="text" name="title" class="input" value="<?php echo htmlspecialchars($evt['title']); ?>" required>
            </div>

            <div class="form-group">
                <label class="label">Description</label>
                <textarea name="description" class="textarea" rows="4" required><?php echo htmlspecialchars($evt['description']); ?></textarea>
            </div>

            <div class="row-3">
                <div class="form-group">
                    <label class="label">Date</label>
                    <input type="date" name="event_date" class="input" value="<?php echo $evt['event_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label class="label">Start Time</label>
                    <input type="time" name="start_time" class="input" value="<?php echo $evt['start_time']; ?>" required>
                </div>
                <div class="form-group">
                    <label class="label">End Time</label>
                    <input type="time" name="end_time" class="input" value="<?php echo $evt['end_time']; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="label">Distance (KM)</label>
                <input type="number" step="0.01" name="distance" class="input" value="<?php echo $evt['target_distance_km']; ?>" required>
            </div>

            <div class="route-edit-card">
                <label class="label"><i class="fas fa-map-marked-alt"></i> Route Management</label>
                <div class="file-info">
                    <i class="fas fa-file-code"></i> 
                    Current: <?php echo !empty($evt['route_url']) ? $evt['route_url'] : "No route uploaded"; ?>
                </div>
                <div class="file-input-wrapper">
                    <input type="file" name="gpx_file" class="input" accept=".gpx">
                </div>
                <p style="font-size:11px; color:#60A5FA; margin-top:10px; font-weight:600;">Upload a new .gpx file to replace the current route, or leave empty to keep existing.</p>
                
                <div style="margin-top:20px;">
                    <label class="label">Route Instructions</label>
                    <textarea name="route_instructions" class="textarea" rows="3" placeholder="Update turn-by-turn guidance or safety notes..."><?php echo htmlspecialchars($evt['route_instructions']); ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="label">Event Banner</label>
                <img src="<?php echo $evt['banner_image']; ?>" class="preview-banner" onerror="this.src='../assets/placeholder.jpg'">
                <input type="hidden" name="image_url" value="<?php echo $evt['banner_image']; ?>">
                <p style="font-size:11px; color:var(--text-muted); text-align:center; font-weight:600; margin-top:5px;"><i class="fas fa-lock"></i> Banner update is disabled in Edit mode.</p>
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
            <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn-cancel">Cancel</a>
        </form>
    </div>
</body>
</html>