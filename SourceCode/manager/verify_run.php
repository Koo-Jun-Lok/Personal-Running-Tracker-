<?php

require_once '../auth_check.php'; 
require_once '../db_connect.php'; 


ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}



$manager_id = $_SESSION['user_id'];

$msg = "";

$err = "";



// Check ID

if (!isset($_GET['pid'])) {

    header("Location: dashboard.php");

    exit();

}

$pid = intval($_GET['pid']);



// --- 2. Verify Ownership & Fetch Data ---

// Only fetch if the event belongs to this manager

$sql = "SELECT p.*, u.name as runner_name, u.email as runner_email, e.title as event_title, e.target_distance_km, e.event_id 

        FROM participations p 

        JOIN users u ON p.user_id = u.user_id 

        JOIN events e ON p.event_id = e.event_id 

        WHERE p.participation_id = ? AND e.manager_id = ?";



$data = null;

if ($stmt = $conn->prepare($sql)) {

    $stmt->bind_param("ii", $pid, $manager_id);

    $stmt->execute();

    $res = $stmt->get_result();

    $data = $res->fetch_assoc();

}



if (!$data) { 

    // If no data, it means either PID is wrong OR this manager doesn't own the event

    die("Record not found or access denied."); 

}



// --- 3. Handle Form Submission (Approve/Reject) ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $decision = $_POST['decision']; // 'verify' or 'reject'

    

    if ($decision === 'verify') {

        $new_status = 'verified';

        $msg = "✅ Run Verified! Runner has been notified.";

    } else {

        $new_status = 'joined'; // Reset to joined so they can try uploading again

        $err = "❌ Run Rejected. Status reset to 'Joined'.";

    }



    $stmt = $conn->prepare("UPDATE participations SET status = ? WHERE participation_id = ?");

    $stmt->bind_param("si", $new_status, $pid);

    

    if ($stmt->execute()) {

        // Refresh data to show new status instantly

        $data['status'] = $new_status;

    } else {

        $err = "Database error.";

    }

}

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Run</title>

    

    <link rel="stylesheet" href="../style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">



    <style>

        body { background: #F8FAFC; margin: 0; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }

        

        .top-nav { background: white; padding: 15px 30px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; }

        .nav-title { font-weight: 800; font-size: 18px; color: #1E293B; display: flex; align-items: center; gap: 10px; }

        .btn-back { text-decoration: none; color: #64748B; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 5px; }

        .btn-back:hover { color: #1E293B; }



        .main-container { max-width: 1100px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: 300px 1fr; gap: 30px; }



        /* Left Column: Info */

        .info-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); height: fit-content; border: 1px solid #E2E8F0; position: sticky; top: 30px; }

        .label { font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 5px; }

        .value { font-size: 16px; font-weight: 600; color: #1E293B; margin-bottom: 20px; }

        

        .runner-box { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }

        .runner-avatar { width: 40px; height: 40px; background: #E2E8F0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748B; }



        /* Right Column: Evidence */

        .evidence-section { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; }

        

        .section-title { font-size: 20px; font-weight: 800; color: #1E293B; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }

        

        .status-pill { padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }

        .st-completed { background: #FEF3C7; color: #D97706; }

        .st-verified { background: #DCFCE7; color: #166534; }



        /* Proof Grid */

        .proof-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }

        

        .proof-card { border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; transition: transform 0.2s; }

        .proof-card:hover { transform: translateY(-5px); border-color: #CBD5E1; }

        

        .proof-img { width: 100%; height: 220px; object-fit: cover; background: #F8FAFC; display: block; }

        .proof-placeholder { width: 100%; height: 220px; display: flex; align-items: center; justify-content: center; background: #F1F5F9; color: #94A3B8; font-size: 13px; font-style: italic; }

        

        .proof-meta { padding: 12px; background: #FAFAFA; border-top: 1px solid #E2E8F0; font-weight: 700; font-size: 13px; color: #475569; display: flex; justify-content: space-between; }

        

        /* Action Buttons */

        .actions { display: flex; gap: 15px; border-top: 1px solid #E2E8F0; padding-top: 30px; }

        .btn { flex: 1; padding: 15px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }

        .btn-verify { background: #10B981; color: white; }

        .btn-verify:hover { background: #059669; }

        .btn-reject { background: #EF4444; color: white; }

        .btn-reject:hover { background: #DC2626; }



        @media (max-width: 800px) { .main-container { grid-template-columns: 1fr; } .info-card { position: static; } }

    </style>

</head>

<body>



    <nav class="top-nav">

        <div class="nav-title"><i class="fas fa-search"></i> Review Submission</div>

        <a href="event_details.php?id=<?php echo $data['event_id']; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Event</a>

    </nav>



    <div class="main-container">

        

        <div class="info-card">

            <div class="label">Runner</div>

            <div class="runner-box">

                <div class="runner-avatar"><i class="fas fa-user"></i></div>

                <div>

                    <div style="font-weight:700; color:#1E293B;"><?php echo htmlspecialchars($data['runner_name']); ?></div>

                    <div style="font-size:12px; color:#64748B;"><?php echo htmlspecialchars($data['runner_email']); ?></div>

                </div>

            </div>



            <hr style="border:0; border-top:1px solid #F1F5F9; margin: 15px 0;">



            <div class="label">Event Challenge</div>

            <div class="value"><?php echo htmlspecialchars($data['event_title']); ?></div>



            <div class="label">Target Distance</div>

            <div class="value"><?php echo $data['target_distance_km']; ?> KM</div>



            <div class="label">Submission Date</div>

            <div class="value"><?php echo date("d M Y, h:i A", strtotime($data['joined_at'])); ?></div>

        </div>



        <div class="evidence-section">

            <div class="section-title">

                Proof of Completion

                <?php if ($data['status'] == 'verified'): ?>

                    <span class="status-pill st-verified"><i class="fas fa-check"></i> Verified</span>

                <?php else: ?>

                    <span class="status-pill st-completed"><i class="fas fa-hourglass-half"></i> Pending Review</span>

                <?php endif; ?>

            </div>



            <?php if ($msg): ?>

                <div style="background:#DCFCE7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold;">

                    <?php echo $msg; ?>

                </div>

            <?php endif; ?>

            <?php if ($err): ?>

                <div style="background:#FEF2F2; color:#991B1B; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold;">

                    <?php echo $err; ?>

                </div>

            <?php endif; ?>



            <div class="proof-grid">

                <div class="proof-card">

                    <?php if(!empty($data['proof_start'])): ?>

                        <a href="<?php echo $data['proof_start']; ?>" target="_blank">

                            <img src="<?php echo $data['proof_start']; ?>" class="proof-img">

                        </a>

                    <?php else: ?>

                        <div class="proof-placeholder">No photo uploaded</div>

                    <?php endif; ?>

                    <div class="proof-meta">

                        <span>1. Start</span>

                        <i class="fas fa-play-circle" style="color:#3B82F6;"></i>

                    </div>

                </div>



                <div class="proof-card">

                    <?php if(!empty($data['proof_checkpoint'])): ?>

                        <a href="<?php echo $data['proof_checkpoint']; ?>" target="_blank">

                            <img src="<?php echo $data['proof_checkpoint']; ?>" class="proof-img">

                        </a>

                    <?php else: ?>

                        <div class="proof-placeholder">No photo uploaded</div>

                    <?php endif; ?>

                    <div class="proof-meta">

                        <span>2. Checkpoint</span>

                        <i class="fas fa-map-marker-alt" style="color:#F59E0B;"></i>

                    </div>

                </div>



                <div class="proof-card">

                    <?php if(!empty($data['proof_image'])): ?>

                        <a href="<?php echo $data['proof_image']; ?>" target="_blank">

                            <img src="<?php echo $data['proof_image']; ?>" class="proof-img">

                        </a>

                    <?php else: ?>

                        <div class="proof-placeholder">No photo uploaded</div>

                    <?php endif; ?>

                    <div class="proof-meta">

                        <span>3. Finish</span>

                        <i class="fas fa-flag-checkered" style="color:#EF4444;"></i>

                    </div>

                </div>

            </div>



            <?php if ($data['status'] !== 'verified'): ?>

                <form method="POST" class="actions">

                    <button type="submit" name="decision" value="verify" class="btn btn-verify" onclick="return confirm('Confirm verification?')">

                        <i class="fas fa-check-circle"></i> Approve & Verify

                    </button>

                    <button type="submit" name="decision" value="reject" class="btn btn-reject" onclick="return confirm('Reject submission? The runner will have to resubmit.')">

                        <i class="fas fa-times-circle"></i> Reject

                    </button>

                </form>

            <?php else: ?>

                <div style="text-align:center; padding:20px; color:#94A3B8; border-top:1px solid #E2E8F0; margin-top:20px;">

                    This run has been verified.

                </div>

            <?php endif; ?>



        </div>

    </div>



</body>

</html>