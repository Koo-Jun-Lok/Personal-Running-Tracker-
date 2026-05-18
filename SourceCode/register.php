<?php
// --- 1. Configuration & Setup ---
// Enable error reporting to help debug issues during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database connection file
require 'db_connect.php';

$msg = "";
$error = "";

// --- 2. Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role']; 

    // --- 3. Input Validation (Updated Security Rules) ---
    
    // Check for empty fields
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } 
    // Check if password match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    // RULE: Minimum 8 characters
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    // RULE: At least one Uppercase Letter (A-Z)
    elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must include at least one UPPERCASE letter.";
    }
    // RULE: At least one Lowercase Letter (a-z)
    elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must include at least one lowercase letter.";
    }
    // RULE: At least one Number (0-9)
    elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must include at least one number.";
    }
    else {
        // --- 4. Database Checks & Insertion ---
        
        // Check for duplicate email
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    echo "<script>alert('✅ Account Created! Please Login.'); window.location.href='login.php';</script>";
                    exit();
                } else {
                    $error = "System error: " . $stmt->error;
                }
            }
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PRT</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563EB">
    <link rel="apple-touch-icon" href="assets/icon-192.png">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            padding-bottom: 20px; 
            background-color: var(--bg-color);
        }

        .auth-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }

        h2 { margin-bottom: 5px; color: var(--text-main); }
        .subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; display: block; }

        .input-group { text-align: left; margin-bottom: 15px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-main); margin-bottom: 5px; }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #E5E7EB;
            border-radius: var(--radius-md);
            font-size: 15px;
            background: #F9FAFB;
            transition: all 0.2s;
            box-sizing: border-box; 
            font-family: inherit;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .error-msg {
            background: #FEE2E2;
            color: var(--danger-color);
            font-size: 13px;
            padding: 10px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            text-align: left;
        }

        .login-link { margin-top: 20px; font-size: 14px; color: var(--text-muted); }
        .login-link a { color: var(--primary-color); font-weight: 600; }

        /* Modal Styles */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;
        }
        .modal-content {
            background: white; width: 90%; max-width: 500px; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md); overflow: hidden; display: flex; flex-direction: column; max-height: 80vh;
        }
        .modal-header {
            padding: 15px 20px; border-bottom: 1px solid #eee; display: flex;
            justify-content: space-between; align-items: center; background: #f9fafb;
        }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-body {
            padding: 20px; overflow-y: auto; font-size: 14px; color: #4b5563;
            line-height: 1.6; text-align: left;
        }
        .modal-body h4 { margin-top: 15px; margin-bottom: 5px; color: var(--primary-color); }
        .modal-footer { padding: 15px; border-top: 1px solid #eee; text-align: right; }
        .close-btn { font-size: 24px; cursor: pointer; color: #999; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="brand-logo">
            <i class="fas fa-running"></i>
        </div>

        <h2>Create Account</h2>
        <span class="subtitle">Join the community today</span>

        <?php if($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label>User Name</label>
                <input type="text" name="name" placeholder="username" required>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email" required>
            </div>

            <div class="input-group">
                <label>Role</label>
                <select name="role">
                    <option value="runner">Runner</option>
                    <option value="event_manager">Manager</option>
                </select>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min 8 chars, A-Z, 0-9" required>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Re-enter password" required>
            </div>

            <div style="font-size: 12px; margin: 15px 0; text-align: left; color: #666; display: flex; align-items: center;">
                <input type="checkbox" required style="width:auto; margin-right:8px; margin-top:0;"> 
                <span>I agree to the <a href="#" onclick="openTerms(event)" style="color:var(--primary-color); cursor:pointer;">Terms & Conditions</a></span>
            </div>

            <button type="submit" class="btn btn-primary">Sign Up</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>

    <div id="termsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📜 Terms & Conditions</h3>
                <span class="close-btn" onclick="closeTerms()">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Last Updated: December 2025</strong></p>
                <h4>1. Nature of Project</h4>
                <p>The Personal Running Tracker (PRT) is developed as a Final Year Project (FYP) for academic purposes only.</p>
                <h4>2. Health Disclaimer</h4>
                <p>Running involves physical risk. By using this app, you acknowledge that you are physically fit. We are not liable for injuries.</p>
                <h4>3. Data & Privacy</h4>
                <p>We collect GPS data and uploaded photos for verifying run activities. Data is visible to admins for grading.</p>
                <h4>4. User Conduct</h4>
                <p>Do not upload offensive or obscene content. Cheating (fake GPS/photos) will result in rejection.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeTerms()" style="width:auto;">I Understand</button>
            </div>
        </div>
    </div>

    <script>
        function openTerms(e) {
            if(e) e.preventDefault();
            document.getElementById('termsModal').style.display = 'flex';
        }
        function closeTerms() {
            document.getElementById('termsModal').style.display = 'none';
        }
        window.onclick = function(event) {
            var modal = document.getElementById('termsModal');
            if (event.target == modal) closeTerms();
        }
    </script>

</body>
</html>