<?php
// 1. Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Database connection
require_once 'db_connect.php';

$message = "";
$status = "";

// Get Email from URL
$email = isset($_GET['email']) ? $_GET['email'] : '';

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $post_email = $_POST['email'];
    $user_otp = $_POST['otp'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $status = "error";
    } else {
        $now = date("Y-m-d H:i:s");
        // Check OTP validity
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND reset_code = ? AND code_expires_at > ?");
        $stmt->bind_param("sss", $post_email, $user_otp, $now);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Success: Hash and Update Password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, code_expires_at = NULL WHERE email = ?");
            $update->bind_param("ss", $hashed_password, $post_email);
            
            if ($update->execute()) {
                $message = "Password updated! <a href='login.php' style='color:inherit; font-weight:bold;'>Login here</a>";
                $status = "success";
            } else {
                $message = "Database update failed.";
                $status = "error";
            }
        } else {
            $message = "Invalid or expired OTP code.";
            $status = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verify OTP - PRT System</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Align with login.php layout */
        body {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-color);
        }

        .auth-card {
            background: var(--card-bg);
            width: 90%;
            max-width: 360px;
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
        }

        .brand-logo {
            width: 70px;
            height: 70px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }

        h2 { margin-bottom: 5px; color: var(--text-main); }
        .subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; display: block; }

        .input-group { text-align: left; margin-bottom: 15px; position: relative; }
        
        input {
            width: 100%;
            padding: 14px;
            padding-right: 45px; /* Space for the eye icon */
            border: 1px solid #E5E7EB;
            border-radius: var(--radius-md);
            font-size: 15px;
            background: #F9FAFB;
            transition: all 0.2s;
            box-sizing: border-box; 
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Eye Icon Styling */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            z-index: 10;
        }

        /* Success/Error Message Styling */
        .msg {
            font-size: 14px;
            padding: 12px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .success { background: #DCFCE7; color: #166534; }
        .error { background: #FEE2E2; color: #991B1B; }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="brand-logo">
            <i class="fas fa-user-shield"></i>
        </div>

        <h2>Verify OTP</h2>
        <span class="subtitle">Verification for: <strong><?php echo htmlspecialchars($email); ?></strong></span>

        <?php if($message): ?>
            <div class="msg <?php echo $status; ?>">
                <i class="fas <?php echo ($status == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            
            <div class="input-group">
                <input type="text" name="otp" placeholder="6-digit OTP" required maxlength="6">
            </div>

            <div class="input-group">
                <input type="password" name="new_password" id="new_password" placeholder="New Password" required minlength="6">
                <i class="fas fa-eye toggle-password" data-target="new_password"></i>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required minlength="6">
                <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
            </div>
            
            <button type="submit" class="btn-primary">Update Password</button>
        </form>

        <div style="margin-top: 25px; font-size: 14px; color: var(--text-muted);">
            Wait, I remember it! <a href="login.php" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Log In</a>
        </div>
    </div>

    <script>
        // Toggle Password Visibility Logic
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                }
            });
        });
    </script>

</body>
</html>