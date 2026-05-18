<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reset Password - PRT</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* 复用 login.php 的核心布局样式 */
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

        .input-group { text-align: left; margin-bottom: 20px; }
        
        input {
            width: 100%;
            padding: 14px;
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

        .register-link { margin-top: 25px; font-size: 14px; color: var(--text-muted); }
        .register-link a { color: var(--primary-color); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="brand-logo">
            <i class="fas fa-key"></i>
        </div>

        <h2>Reset Password</h2>
        <span class="subtitle">Enter your email to receive an OTP</span>

        <form method="POST" action="process_send_email_otp.php">
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Send OTP to Email</button>
        </form>

        <div class="register-link">
            Remembered your password? <a href="login.php">Log In</a>
        </div>
    </div>

</body>
</html>