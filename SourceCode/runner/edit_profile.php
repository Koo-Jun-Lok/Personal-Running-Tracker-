<?php

require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Security Check

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}



$user_id = $_SESSION['user_id'];

$msg = "";

$err = "";



// --- 2. Handle Form Submission ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = trim($_POST['name']);

    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;

    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;

    $avatar_url = $_POST['avatar_url']; // Hidden input



    if (empty($name)) {

        $err = "Name is required.";

    } else {

        $sql = "UPDATE users SET name=?, height=?, weight=?, avatar=? WHERE user_id=?";

        

        if ($stmt = $conn->prepare($sql)) {

            // Types: s=string, d=double, i=int

            $stmt->bind_param("sddsi", $name, $height, $weight, $avatar_url, $user_id);

            

            if ($stmt->execute()) {

                $msg = "Profile updated successfully!";

                $_SESSION['name'] = $name; // Update session name immediately

            } else {

                $err = "Error updating profile.";

            }

            $stmt->close();

        }

    }

}



// --- 3. Fetch Current Data ---

$sql = "SELECT name, email, avatar, height, weight FROM users WHERE user_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile</title>

    <link rel="stylesheet" href="../style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>

        body { background: #F8FAFC; padding: 20px; font-family: 'Segoe UI', sans-serif; }

        

        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }

        

        .header { text-align: center; margin-bottom: 30px; }

        .page-title { margin: 0; font-size: 24px; font-weight: 800; color: #1E293B; }



        /* Avatar Upload Section */

        .avatar-upload { position: relative; width: 110px; height: 110px; margin: 0 auto 25px; }

        .avatar-preview { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #F1F5F9; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        .avatar-edit-icon {

            position: absolute; bottom: 5px; right: 5px;

            background: #2563EB; color: white; width: 35px; height: 35px;

            border-radius: 50%; display: flex; align-items: center; justify-content: center;

            cursor: pointer; border: 3px solid white; transition: 0.2s;

        }

        .avatar-edit-icon:active { transform: scale(0.9); }

        .file-input { display: none; }



        /* Form Fields */

        .form-group { margin-bottom: 20px; }

        .label { display: block; font-size: 13px; font-weight: 700; color: #64748B; margin-bottom: 8px; text-transform: uppercase; }

        .input { width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #E2E8F0; box-sizing: border-box; font-size: 15px; transition: 0.2s; }

        .input:focus { border-color: #2563EB; outline: none; }



        /* Readonly Input Style */

        .input-readonly { 

            background-color: #F1F5F9; 

            color: #94A3B8; 

            border-color: #E2E8F0;

            cursor: not-allowed; 

        }



        /* 2-Column Grid */

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }



        .btn-save {

            width: 100%; padding: 15px; background: #2563EB; color: white; border: none;

            border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer;

            transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;

        }

        .btn-save:hover { background: #1D4ED8; }

        .btn-save:disabled { background: #94A3B8; cursor: not-allowed; }



        .btn-cancel {

            display: block; text-align: center; margin-top: 15px; color: #64748B; text-decoration: none; font-weight: 600;

        }

        

        #loading-msg { text-align: center; color: #2563EB; font-weight: bold; margin-top: 15px; display: none; font-size: 14px; }

    </style>

</head>

<body>



    <div class="container">

        <div class="header">

            <h1 class="page-title">Edit Profile</h1>

        </div>



        <?php if ($msg): ?>

            <div style="background:#DCFCE7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center; font-weight:bold;"><?php echo $msg; ?></div>

        <?php endif; ?>

        <?php if ($err): ?>

            <div style="background:#FEF2F2; color:#991B1B; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center; font-weight:bold;"><?php echo $err; ?></div>

        <?php endif; ?>



        <form id="profileForm" method="POST">

            

           <div class="avatar-upload">

                <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : '../assets/default_avatar.jpg'; ?>" 

                    id="avatar-preview" 

                    class="avatar-preview" 

                    onerror="this.src='../assets/default_avatar.jpg'">

                

                <label for="file-input" class="avatar-edit-icon">

                    <i class="fas fa-camera"></i>

                </label>

                <input type="file" id="file-input" class="file-input" accept="image/*" onchange="handleAvatarSelect(this)">

            </div>

            

            <input type="hidden" name="avatar_url" id="inp-avatar-url" value="<?php echo $user['avatar']; ?>">



            <div class="form-group">

                <label class="label">User Name</label>

                <input type="text" name="name" class="input" value="<?php echo htmlspecialchars($user['name']); ?>" required>

            </div>



            <div class="form-group">

                <label class="label">Email Address <i class="fas fa-lock" style="font-size:10px; margin-left:5px;"></i></label>

                <input type="email" class="input input-readonly" value="<?php echo htmlspecialchars($user['email']); ?>" readonly title="Email cannot be changed">

            </div>



            <div class="row-2">

                <div class="form-group">

                    <label class="label">Height (CM)</label>

                    <input type="number" step="0.01" name="height" class="input" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>" placeholder="e.g. 175">

                </div>

                <div class="form-group">

                    <label class="label">Weight (KG)</label>

                    <input type="number" step="0.01" name="weight" class="input" value="<?php echo htmlspecialchars($user['weight'] ?? ''); ?>" placeholder="e.g. 70">

                </div>

            </div>



            <button type="submit" class="btn-save" id="btn-save">

                <i class="fas fa-save"></i> Save Changes

            </button>

            <div id="loading-msg"><i class="fas fa-spinner fa-spin"></i> Processing...</div>

            

            <a href="profile.php" class="btn-cancel">Cancel</a>

        </form>

    </div>



    <script>

        const IMGBB_API_KEY = 'f8bbc81c32e4ebae4619166a269ad997'; 

        let selectedFile = null;



        function handleAvatarSelect(input) {

            if (input.files && input.files[0]) {

                selectedFile = input.files[0];

                const reader = new FileReader();

                reader.onload = function(e) {

                    document.getElementById('avatar-preview').src = e.target.result;

                }

                reader.readAsDataURL(selectedFile);

            }

        }



        // Image Compression Logic

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

                    canvas.toBlob(blob => {

                        callback(blob);

                    }, 'image/jpeg', quality);

                };

            };

        }



        document.getElementById('profileForm').addEventListener('submit', function(e) {

            e.preventDefault(); 

            const btn = document.getElementById('btn-save');

            const load = document.getElementById('loading-msg');



            

            if (!selectedFile) {

                this.submit(); // Standard PHP submit

                return;

            }



            

            btn.disabled = true;

            btn.style.opacity = "0.5";

            load.style.display = "block";

            load.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Uploading Avatar...';



            compressImage(selectedFile, 500, 0.7, function(compressedBlob) {

                const formData = new FormData();

                formData.append('image', compressedBlob, 'avatar.jpg');

                formData.append('key', IMGBB_API_KEY);



                fetch('https://api.imgbb.com/1/upload', {

                    method: 'POST',

                    body: formData

                })

                .then(res => res.json())

                .then(data => {

                    if (data.success) {

                        document.getElementById('inp-avatar-url').value = data.data.url;

                        document.getElementById('profileForm').submit();

                    } else {

                        alert('Image Upload Failed: ' + (data.error ? data.error.message : 'Unknown'));

                        btn.disabled = false;

                        load.style.display = "none";

                    }

                })

                .catch(err => {

                    console.error(err);

                    alert('Network Error during upload');

                    btn.disabled = false;

                    load.style.display = "none";

                });

            });

        });

    </script>



</body>

</html>