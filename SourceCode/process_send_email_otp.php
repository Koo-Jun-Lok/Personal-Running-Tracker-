<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        // 1. 检查 Email 是否存在
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            // 2. 生成 6 位验证码
            $otpCode = (string)rand(100000, 999999);
            $expiresAt = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            // 3. 存入数据库
            $update = $conn->prepare("UPDATE users SET reset_code = ?, code_expires_at = ? WHERE email = ?");
            $update->bind_param("sss", $otpCode, $expiresAt, $email);
            $update->execute();

            // 4. 发送邮件 (Basic PHP Mail)
            $subject = "PRT System - Password Reset OTP";
            $message = "Your verification code is: " . $otpCode . "\r\n";
            $message .= "This code will expire in 15 minutes. If you did not request this, please ignore this email.";
            $headers = "From: no-reply@koo.codex-biz.com" . "\r\n" .
                       "Reply-To: no-reply@koo.codex-biz.com" . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            if (mail($email, $subject, $message, $headers)) {
                // 发送成功，跳转到验证页
                header("Location: verify_otp.php?email=" . urlencode($email));
                exit();
            } else {
                echo "Error: Failed to send email. Your server might not support mail().";
            }
        } else {
            echo "Error: This email address is not registered in our system.";
        }
    }
}
?>