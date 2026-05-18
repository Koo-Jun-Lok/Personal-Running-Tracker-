<?php
// cron_push.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. 引入必要文件
require_once __DIR__ . '/vendor/autoload.php';
require_once 'db_connect.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

date_default_timezone_set('Asia/Kuala_Lumpur');

// 2. 你的 VAPID 密钥配置
$auth = [
    'VAPID' => [
        'subject' => 'mailto:junlok2003@gmail.com',
        'publicKey' => 'BJdrHY8CzAEQ6GDHtF2SAuwOnyAv03_QnbkeIHn744C6ib3xQqMFLqXVs-_K2ZNVokt98U37DkcjfpIWCPt9ULQ',
        'privateKey' => '3dimYn8kgtf2sRCFIZlR3s8CI5h5mwud5CiQJ6COZwU',
    ],
];

$webPush = new WebPush($auth);
$current_time = date('Y-m-d H:i:s');

echo "<pre>Running Daily 9 PM Notification Task at $current_time...\n";

// ==========================================
// 🚀 直接执行：查找明天开始的比赛并发送通知
// ==========================================
$sql = "SELECT e.title, e.start_time, s.user_id, s.endpoint, s.p256dh, s.auth 
        FROM events e
        JOIN participations p ON e.event_id = p.event_id
        JOIN push_subscriptions s ON p.user_id = s.user_id
        WHERE e.event_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
        AND e.status = 'active'
        AND p.status IN ('joined', 'ready')";

if ($result = $conn->query($sql)) {
    if ($result->num_rows === 0) {
        echo "No events scheduled for tomorrow.\n";
    }

    while ($row = $result->fetch_assoc()) {
        $event_title = $row['title'];
        $start_time = date("g:i A", strtotime($row['start_time'])); // 例如转为 8:30 AM 格式

        $payload = json_encode([
            'title' => 'Tomorrow\'s Race Reminder! 🏁',
            'body'  => "Hi! Don't forget your event '$event_title' starts tomorrow at $start_time. See you there!",
            'url'   => '/prt_system/runner/my_events.php'
        ]);

        $subscription = Subscription::create([
            'endpoint' => $row['endpoint'],
            'publicKey' => $row['p256dh'],
            'authToken' => $row['auth'],
        ]);

        $webPush->queueNotification($subscription, $payload);
        echo "Queued notification for User ID: " . $row['user_id'] . " (Event: $event_title)\n";
    }

    // 执行批量发送
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            echo "[Success] Notification sent.\n";
        } else {
            echo "[Failed] Error: {$report->getReason()}\n";
        }
    }
} else {
    echo "Database query failed: " . $conn->error . "\n";
}

echo "Task finished.</pre>";
$conn->close();
?>