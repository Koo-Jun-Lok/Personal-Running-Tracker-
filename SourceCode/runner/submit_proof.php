<?php
// --- 1. Setup ---
require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pid = $_GET['pid'] ?? 0;
$stage = $_GET['stage'] ?? 'start';
$event_id = 0;

// 获取所属 event_id 用于后续跳转
if ($pid > 0) {
    $stmt = $conn->prepare("SELECT event_id FROM participations WHERE participation_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $event_id = $res['event_id'] ?? 0;
}

$instructions = "Take a quick selfie to start!";
$cameraMode = "user"; 

if ($stage == 'checkpoint') {
    $instructions = "Capture the required landmark.";
    $cameraMode = "environment"; 
} elseif ($stage == 'end') {
    $instructions = "Smile! You've reached the finish line.";
    $cameraMode = "user";
}

// POST 处理逻辑
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $img_url = $_POST['img_url'];
    $current_stage = $_POST['stage'];
    $pid = $_POST['pid'];

    if (!empty($img_url)) {
        $sql = "";
        if ($current_stage == 'start') {
            $sql = "UPDATE participations SET proof_start = ?, status = 'started' WHERE participation_id = ?";
        } elseif ($current_stage == 'checkpoint') {
            $sql = "UPDATE participations SET proof_checkpoint = ?, status = 'at_checkpoint' WHERE participation_id = ?";
        } elseif ($current_stage == 'end') {
            $sql = "UPDATE participations SET proof_image = ?, status = 'completed' WHERE participation_id = ?";
        }

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $img_url, $pid);
            if ($stmt->execute()) {
                // 如果是起点拍照，直接进入实时地图导航
                if ($current_stage == 'start') {
                    echo "<script>window.location.href='track_run.php?id=$event_id';</script>";
                } else {
                    echo "<script>window.location.href='my_events.php';</script>";
                }
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Upload Proof - PRT System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --dark: #0F172A; }
        body { background: #000; color: white; font-family: 'Inter', sans-serif; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        
        .header { padding: 40px 25px 20px; text-align: center; }
        .header h2 { font-size: 24px; font-weight: 900; margin: 0; letter-spacing: -0.5px; }
        .header p { color: #94A3B8; font-size: 14px; margin-top: 8px; font-weight: 500; }

        .camera-area { flex: 1; display: flex; align-items: center; justify-content: center; padding: 0 20px; }
        
        #preview-box { 
            width: 100%; max-width: 350px; height: 450px; 
            background: #1E293B; border-radius: 30px; 
            overflow: hidden; border: 2px dashed #334155;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        #preview-img { width: 100%; height: 100%; object-fit: cover; display: none; }
        
        .camera-btn-circle {
            width: 85px; height: 85px; background: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 30px rgba(255,255,255,0.2); cursor: pointer;
            transition: 0.3s;
        }
        .camera-btn-circle:active { transform: scale(0.9); }
        .camera-btn-circle i { font-size: 28px; color: black; }

        .controls { padding: 30px 25px 60px; text-align: center; }
        
        .btn-main {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: white; border: none; width: 100%; max-width: 300px;
            padding: 18px; border-radius: 20px; font-weight: 800;
            font-size: 16px; text-transform: uppercase; letter-spacing: 1px;
            display: none; cursor: pointer; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }

        .loading { display: none; color: #60A5FA; font-weight: 700; margin-top: 15px; }
        .btn-cancel { color: #64748B; text-decoration: none; font-size: 13px; font-weight: 700; display: block; margin-top: 25px; }

        #file-input { display: none; }
    </style>
</head>
<body>

    <div class="header">
        <h2><?php echo strtoupper($stage); ?> PROOF</h2>
        <p><?php echo $instructions; ?></p>
    </div>

    <div class="camera-area">
        <div id="preview-box">
            <i class="fas fa-image" id="placeholder-icon" style="font-size: 50px; color: #334155;"></i>
            <img id="preview-img">
        </div>
    </div>

    <div class="controls">
        <form id="uploadForm" method="POST">
            <input type="hidden" name="pid" value="<?php echo $pid; ?>">
            <input type="hidden" name="stage" value="<?php echo $stage; ?>">
            <input type="hidden" name="img_url" id="inp-img-url">

            <label for="file-input" class="camera-btn-circle" id="cam-btn-ui">
                <i class="fas fa-camera"></i>
            </label>
            <input type="file" id="file-input" accept="image/*" capture="<?php echo $cameraMode; ?>" onchange="handleFile(this)">

            <button type="submit" class="btn-main" id="submit-btn">
                Confirm & Start
            </button>
        </form>

        <div class="loading" id="loading-msg">
            <i class="fas fa-spinner fa-spin"></i> Processing...
        </div>

        <a href="my_events.php" class="btn-cancel">CANCEL</a>
    </div>

    <script>
        const IMGBB_API_KEY = 'f8bbc81c32e4ebae4619166a269ad997'; 
        let selectedFile = null;

        function handleFile(input) {
            if (input.files && input.files[0]) {
                selectedFile = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('placeholder-icon').style.display = 'none';
                    const preview = document.getElementById('preview-img');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    document.getElementById('cam-btn-ui').style.display = 'none'; 
                    document.getElementById('submit-btn').style.display = 'inline-block'; 
                }
                reader.readAsDataURL(selectedFile);
            }
        }

        function compressImage(file, maxWidth, quality, callback) {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = event => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => callback(blob), 'image/jpeg', quality);
                };
            };
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!selectedFile) return;
            e.preventDefault(); 
            
            const btn = document.getElementById('submit-btn');
            const msg = document.getElementById('loading-msg');
            btn.disabled = true;
            btn.style.opacity = '0.5';
            msg.style.display = 'block';

            compressImage(selectedFile, 800, 0.7, function(blob) {
                const formData = new FormData();
                formData.append('image', blob, 'proof.jpg'); 
                formData.append('key', IMGBB_API_KEY);

                fetch('https://api.imgbb.com/1/upload', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('inp-img-url').value = data.data.url;
                        document.getElementById('uploadForm').submit();
                    } else {
                        alert('Upload Error');
                        btn.disabled = false;
                        msg.style.display = 'none';
                    }
                })
                .catch(() => {
                    alert('Network error');
                    btn.disabled = false;
                    msg.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>