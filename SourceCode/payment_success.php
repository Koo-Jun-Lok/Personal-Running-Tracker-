<?php
// payment_success.php
session_start();
require_once 'stripe-php/init.php';
require_once 'db_connect.php'; 

\Stripe\Stripe::setApiKey('sk_test_51TUQVj2KsAgaxEpqpUfrGrr3Ut9JmRLzJsIlpP4BXNYYgfT6W4pU0o8a1nERBh3h7Wqug7PUN9qe3P5jjQ0FYGJJ00G6jf5Cu8');

$user_id = $_SESSION['user_id'] ?? 0;
$event_id = $_GET['event_id'] ?? 0;
$session_id = $_GET['session_id'] ?? '';

$is_success = false;
$event_title = "PRT Challenge"; // 默认名

if ($user_id && $event_id && $session_id) {
    try {
        $session = \Stripe\Checkout\Session::retrieve($session_id);

        if ($session->payment_status === 'paid') {
            $stmt = $conn->prepare("INSERT INTO participations (user_id, event_id, status) VALUES (?, ?, 'joined') ON DUPLICATE KEY UPDATE status='joined'");
            $stmt->bind_param("ii", $user_id, $event_id);
            $stmt->execute();
            $is_success = true;

            $evt_stmt = $conn->prepare("SELECT title FROM events WHERE event_id = ?");
            $evt_stmt->bind_param("i", $event_id);
            $evt_stmt->execute();
            $evt_res = $evt_stmt->get_result();
            if ($row = $evt_res->fetch_assoc()) {
                $event_title = $row['title'];
            }
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registration Confirmed | PRT System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --success-color: #059669; /* Formal Emerald */
            --success-bg: #D1FAE5;
            --error-color: #DC2626;   /* Formal Red */
            --error-bg: #FEE2E2;
            --primary: #2563EB;       /* Corporate Blue */
            --primary-hover: #1D4ED8;
            --bg-color: #F3F4F6;      /* Neutral Gray */
            --card-bg: #FFFFFF;
            --text-main: #111827;
            --text-muted: #4B5563;
            --border-light: #E5E7EB;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .confirmation-card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            width: 100%;
            max-width: 460px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            text-align: center;
        }

        .status-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 24px;
        }

        .icon-success { background: var(--success-bg); color: var(--success-color); }
        .icon-error { background: var(--error-bg); color: var(--error-color); }

        h1 { font-size: 22px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; letter-spacing: -0.5px; }
        .subtitle { font-size: 14px; color: var(--text-muted); margin-bottom: 32px; }

        /* Formal Receipt Details Box */
        .details-box {
            background: #F9FAFB;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 32px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-label { color: var(--text-muted); font-weight: 500; }
        .detail-value { color: var(--text-main); font-weight: 600; text-align: right; max-width: 60%; word-break: break-word; }

        /* Buttons */
        .btn-custom {
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: block;
            width: 100%;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .btn-primary-action {
            background: var(--text-main);
            color: #FFFFFF;
            border: 1px solid var(--text-main);
            margin-bottom: 12px;
        }
        .btn-primary-action:hover {
            background: #374151;
            color: #FFFFFF;
        }
        .btn-secondary-action {
            background: transparent;
            border: 1px solid #D1D5DB;
            color: var(--text-main);
        }
        .btn-secondary-action:hover {
            background: #F3F4F6;
            color: var(--text-main);
        }

        hr.divider {
            border: 0;
            border-top: 1px solid var(--border-light);
            margin: 24px 0;
        }

        .next-steps {
            text-align: left;
            margin-bottom: 32px;
        }
        .next-steps h3 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .next-steps ul {
            padding-left: 20px;
            margin: 0;
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.6;
        }
        .next-steps li { margin-bottom: 8px; }

        .countdown-text {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 24px;
        }
        .countdown-text span {
            font-weight: 600;
            color: var(--text-main);
        }
    </style>
</head>
<body>

<div class="confirmation-card">
    <?php if ($is_success): ?>
        
        <div class="status-icon icon-success">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Registration Confirmed</h1>
        <p class="subtitle">Your payment has been successfully processed.</p>

        <div class="details-box">
            <div class="detail-row">
                <span class="detail-label">Event</span>
                <span class="detail-value"><?= htmlspecialchars($event_title) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value" style="color: var(--success-color);">Active Participant</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Reference ID</span>
                <span class="detail-value" style="font-family: monospace; font-size: 12px; color: var(--text-muted);">
                    <?= htmlspecialchars(substr($session_id, -10)) ?>
                </span>
            </div>
        </div>

        <div class="next-steps">
            <h3>Next Steps</h3>
            <ul>
                <li>Review the event schedule in your dashboard.</li>
                <li>Prepare necessary gear prior to the event date.</li>
                <li>Ensure your NFC tag or registration ID is accessible on race day.</li>
            </ul>
        </div>

        <a href="runner/my_events.php" class="btn-custom btn-primary-action">Proceed to Dashboard</a>
        <a href="../index.php" class="btn-custom btn-secondary-action">Return to Home</a>
        
        <div class="countdown-text">Automatically redirecting in <span id="timer">15</span> seconds.</div>

    <?php else: ?>
        
        <div class="status-icon icon-error">
            <i class="fas fa-exclamation"></i>
        </div>
        
        <h1>Transaction Failed</h1>
        <p class="subtitle">We were unable to process your payment.</p>

        <div class="details-box" style="text-align: center; color: var(--error-color);">
            <?= isset($error_msg) ? htmlspecialchars($error_msg) : "The transaction was declined or cancelled. No charges were made." ?>
        </div>
        
        <hr class="divider">

        <a href="event_details.php?id=<?= htmlspecialchars($event_id) ?>" class="btn-custom btn-primary-action" style="background: var(--error-color); border-color: var(--error-color);">Attempt Payment Again</a>
        <a href="../index.php" class="btn-custom btn-secondary-action">Return to Home</a>

    <?php endif; ?>
</div>

<?php if ($is_success): ?>
<script>
    let timeLeft = 15;
    const timerEl = document.getElementById('timer');
    
    const interval = setInterval(() => {
        timeLeft--;
        if(timeLeft > 0) {
            timerEl.innerText = timeLeft;
        } else {
            clearInterval(interval);
            window.location.href = "runner/my_events.php";
        }
    }, 1000);
</script>
<?php endif; ?>

</body>
</html>