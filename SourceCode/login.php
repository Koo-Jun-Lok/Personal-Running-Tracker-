<?php
// --- 1. Configuration & Setup ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 设置 Session 生命周期为 900 秒 (15 分钟)
$session_lifetime = 900; 
ini_set('session.gc_maxlifetime', $session_lifetime);
session_set_cookie_params($session_lifetime);

session_start();

// 核心逻辑：如果用户已经登录且未超时，直接根据角色跳转
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    // 检查是否超过 15 分钟没活动
    if (time() - $_SESSION['last_activity'] < $session_lifetime) {
        // 自动重定向
        if ($_SESSION['role'] == 'admin') {
            header("Location: admin/admin_dashboard.php");
        } elseif ($_SESSION['role'] == 'event_manager') {
            header("Location: manager/manager_dashboard.php");
        } else {
            header("Location: runner/home.php");
        }
        exit();
    } else {
        // 如果超时，清除 Session
        session_unset();
        session_destroy();
    }
}

require 'db_connect.php';
$error = "";

// --- 2. Login Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT user_id, name, password, role FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // 登录成功：设置 Session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                // 记录当前登录时间
                $_SESSION['last_activity'] = time(); 
                
                if ($user['role'] == 'admin') {
                    header("Location: admin/admin_dashboard.php");
                } elseif ($user['role'] == 'event_manager') {
                    header("Location: manager/manager_dashboard.php");
                } else {
                    header("Location: runner/home.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - PRT</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563EB">
    <link rel="apple-touch-icon" href="assets/icon-192.png">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* 这里是你原来的 CSS 样式... */
        body {
            height: 100vh; width: 100vw; overflow: hidden; margin: 0;
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; background-color: var(--bg-color);
        }
        #install-banner {
            display: none; background: var(--primary-color); color: white;
            padding: 10px 20px; width: 100%; text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); position: absolute;
            top: 0; left: 0; z-index: 1000; box-sizing: border-box;
            animation: slideDown 0.5s ease;
        }
        .banner-content { display: flex; justify-content: space-between; align-items: center; max-width: 600px; margin: 0 auto; }
        .btn-install { background: white; color: var(--primary-color); border: none; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; cursor: pointer; margin-left: 10px; }
        .close-banner { background: none; border: none; color: rgba(255,255,255,0.8); font-size: 18px; cursor: pointer; margin-left: 15px; }
        .auth-card { background: var(--card-bg); width: 90%; max-width: 360px; padding: 30px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); text-align: center; }
        .brand-logo { width: 70px; height: 70px; background: var(--primary-color); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }
        h2 { margin-bottom: 5px; color: var(--text-main); }
        .subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; display: block; }
        .input-group { text-align: left; margin-bottom: 20px; }
        input { width: 100%; padding: 14px; border: 1px solid #E5E7EB; border-radius: var(--radius-md); font-size: 15px; background: #F9FAFB; transition: all 0.2s; box-sizing: border-box; }
        input:focus { outline: none; border-color: var(--primary-color); background: white; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .error-msg { background: #FEE2E2; color: var(--danger-color); font-size: 14px; padding: 10px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .forgot-link { text-align: right; margin-top: -12px; margin-bottom: 25px; }
        .forgot-link a { font-size: 13px; color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .register-link { margin-top: 25px; font-size: 14px; color: var(--text-muted); }
        .register-link a { color: var(--primary-color); font-weight: 600; }
        @keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
    </style>
</head>
<body>
    <div id="install-banner">
        <div class="banner-content">
            <div style="display:flex; align-items:center;">
                <i class="fas fa-mobile-alt" style="margin-right:8px;"></i>
                <span>Install PRT App!</span>
            </div>
            <div style="display:flex; align-items:center;">
                <button id="install-btn" class="btn-install">Install</button>
                <button id="close-banner" class="close-banner">&times;</button>
            </div>
        </div>
    </div>

    <div class="auth-card">
        <div class="brand-logo">
            <i class="fas fa-running"></i>
        </div>
        <h2>Welcome Back</h2>
        <span class="subtitle">Log in to track your progress</span>

        <?php if($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="forgot-link">
                <a href="forget_password.php">Forgot Password?</a>
            </div>
            <button type="submit" class="btn btn-primary">Log In</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Sign Up</a>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(reg => console.log('SW Registered!', reg.scope))
                    .catch(err => console.log('SW Failed!', err));
            });
        }
        let deferredPrompt;
        const banner = document.getElementById('install-banner');
        const installBtn = document.getElementById('install-btn');
        const closeBtn = document.getElementById('close-banner');
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault(); 
            deferredPrompt = e; 
            banner.style.display = 'block'; 
        });
        installBtn.addEventListener('click', () => {
            banner.style.display = 'none';
            deferredPrompt.prompt(); 
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted install');
                }
                deferredPrompt = null;
            });
        });
        closeBtn.addEventListener('click', () => {
            banner.style.display = 'none';
        });
    </script>
</body>
</html>