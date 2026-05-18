<?php
// 开启错误提示，方便排错
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入刚刚上传的扩展库
require_once __DIR__ . '/vendor/autoload.php';
use Minishlink\WebPush\VAPID;

try {
    // 自动生成一对安全的密钥
    $vapidKeys = VAPID::createVapidKeys();

    echo "<h3 style='color: #2563EB; font-family: sans-serif;'>✅ 密钥生成成功！请妥善保存以下信息：</h3>";
    echo "<div style='background: #F1F5F9; padding: 20px; border-radius: 10px; font-family: monospace; word-wrap: break-word;'>";
    echo "<p><b>Public Key (公钥 - 稍后放在前端 JS 里):</b><br><span style='color: #10B981;'>" . $vapidKeys['publicKey'] . "</span></p>";
    echo "<p><b>Private Key (私钥 - 绝对保密，放在后端 PHP 里):</b><br><span style='color: #EF4444;'>" . $vapidKeys['privateKey'] . "</span></p>";
    echo "</div>";
    echo "<p style='color: #64748B; font-family: sans-serif;'>⚠️ 提示：保存好这两串代码后，请立即从服务器上删除 <b>generate_keys.php</b> 文件以保证安全。</p>";
    
} catch (Exception $e) {
    echo "生成失败，错误信息: " . $e->getMessage();
}
?>