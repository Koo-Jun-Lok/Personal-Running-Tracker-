<?php
require_once '../auth_check.php';
require_once '../db_connect.php';

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php");
    exit();
}

$event_id = intval($_GET['event_id'] ?? 0);
$event_title = "Unknown Event";
$runners = [];

if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT title FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $event_title = $res['title'];

    $sql = "SELECT u.user_id, u.name, u.avatar, p.status 
            FROM participations p 
            JOIN users u ON p.user_id = u.user_id 
            WHERE p.event_id = ? 
            ORDER BY u.name ASC";
    $runner_stmt = $conn->prepare($sql);
    $runner_stmt->bind_param("i", $event_id);
    $runner_stmt->execute();
    $runners = $runner_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NFC Station | PRT Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --secondary: #8B5CF6; --success: #10B981; --danger: #EF4444; --bg: #F1F5F9; }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 20px; color: #1E293B; }
        .station-container { max-width: 450px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .nfc-card { background: white; border-radius: 35px; padding: 40px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); text-align: center; border: 1px solid rgba(255,255,255,0.3); }
        .mode-selector { display: flex; background: #F8FAFC; padding: 6px; border-radius: 20px; margin-bottom: 25px; border: 1px solid #E2E8F0; }
        .mode-btn { flex: 1; padding: 12px; border-radius: 16px; border: none; font-size: 11px; font-weight: 800; cursor: pointer; transition: 0.3s; color: #94A3B8; background: transparent; }
        .mode-btn.active { background: white; color: var(--primary); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
        .mode-btn.active.finish-mode { color: var(--secondary); }
        .status-circle { width: 110px; height: 110px; border-radius: 50%; background: #F8FAFC; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 45px; color: #CBD5E1; transition: 0.4s; }
        .active-scan { background: var(--primary); color: white; animation: pulse 1.5s infinite; }
        .active-scan.finish-mode { background: var(--secondary); animation: pulse-purple 1.5s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); box-shadow: 0 0 0 20px rgba(37, 99, 235, 0); } 100% { transform: scale(1); } }
        @keyframes pulse-purple { 0% { transform: scale(1); } 50% { transform: scale(1.1); box-shadow: 0 0 0 20px rgba(139, 92, 246, 0); } 100% { transform: scale(1); } }
        .selected-runner-preview { background: #F8FAFC; padding: 15px; border-radius: 20px; margin-bottom: 15px; display: none; align-items: center; gap: 12px; text-align: left; border: 1px dashed #CBD5E1; }
        .preview-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; }
        .form-control { width: 100%; padding: 18px; border-radius: 20px; border: 2px solid #F1F5F9; font-size: 20px; box-sizing: border-box; text-align: center; font-weight: 800; background: #F8FAFC; margin-bottom: 20px; }
        .btn-action { width: 100%; background: var(--primary); color: white; border: none; padding: 22px; border-radius: 24px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; }
        .btn-action.finish-mode { background: var(--secondary); }
        .search-card { background: white; border-radius: 35px; padding: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); display: flex; flex-direction: column; max-height: 400px; }
        .search-box { width: 80%; padding: 12px 18px; border-radius: 15px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 13px; margin: 0 auto 15px; display: block; }
        .runner-list { flex: 1; overflow-y: auto; }
        .runner-item { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 15px; cursor: pointer; transition: 0.2s; }
        .runner-item:hover { background: #F1F5F9; }
        .back-link { display: block; margin-top: 25px; color: #94A3B8; text-decoration: none; font-size: 12px; font-weight: 700; text-align: center; }
        .d-none { display: none !important; }
    </style>
</head>
<body>

<div class="station-container">
    <div class="nfc-card">
        <div class="mode-selector">
            <button class="mode-btn active" id="mode-checkin" onclick="switchMode('checkin')">Registration</button>
            <button class="mode-btn" id="mode-finish" onclick="switchMode('finish')">Finish Line</button>
        </div>

        <div id="status-icon" class="status-circle"><i class="fas fa-rss"></i></div>
        <h3 id="instruction" style="margin: 0; font-weight: 800;">Ready to Scan</h3>
        <p style="font-size: 12px; color: var(--primary); font-weight: 700; margin-top: 5px; opacity: 0.7;">Event: <?php echo htmlspecialchars($event_title); ?></p>

        <div id="preview-box" class="selected-runner-preview">
            <img src="" id="prev-img" class="preview-avatar">
            <div style="flex:1">
                <span id="prev-name" style="font-size: 14px; font-weight: 800; display: block;"></span>
                <span id="prev-id" style="font-size: 10px; color: #94A3B8; font-weight: 700;"></span>
            </div>
            <i class="fas fa-times-circle" style="color:#CBD5E1; cursor:pointer;" onclick="clearSelection()"></i>
        </div>

        <div id="registration-input-area">
            <input type="number" id="target_user_id" class="form-control" placeholder="Enter ID to Bind">
        </div>

        <button id="main-btn" class="btn-action" onclick="handleAction()">BIND & WRITE TAG</button>
        <a href="event_details.php?id=<?php echo $event_id; ?>" class="back-link"><i class="fas fa-arrow-left"></i> Exit Station</a>
    </div>

    <div class="search-card" id="search-section">
        <div class="search-header">
            <input type="text" id="runner-search" class="search-box" placeholder="Search name or ID..." onkeyup="filterRunners()">
        </div>
        <div class="runner-list">
            <?php foreach($runners as $r): ?>
                <div class="runner-item" data-name="<?= htmlspecialchars($r['name']) ?>" data-id="<?= $r['user_id'] ?>" onclick="selectRunner(<?= $r['user_id'] ?>, '<?= htmlspecialchars($r['name']) ?>', '<?= !empty($r['avatar']) ? $r['avatar'] : '../assets/default_avatar.jpg' ?>')">
                    <img src="<?= !empty($r['avatar']) ? $r['avatar'] : '../assets/default_avatar.jpg' ?>" class="preview-avatar" style="width:35px; height:35px; border-radius:10px;">
                    <div style="flex:1">
                        <span class="name"><?= htmlspecialchars($r['name']) ?> <small style="color:var(--primary)">#<?= $r['user_id'] ?></small></span>
                        <span style="font-size:9px; font-weight:800; color:#94A3B8;"><?= strtoupper($r['status']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>

// --- 修改版：大音量成功雙連音 ---
function playSuccessBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        
        // 第一個音符 (低一點)
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.type = 'sine';
        osc1.frequency.value = 523.25; // C5 音
        
        // 將音量調大 (最高為 1，這裡設為 1.0 全開)
        gain1.gain.setValueAtTime(1.0, audioCtx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
        
        osc1.start(audioCtx.currentTime);
        osc1.stop(audioCtx.currentTime + 0.1);

        // 第二個音符 (高一點，緊接著播放)
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.type = 'sine';
        osc2.frequency.value = 783.99; // G5 音
        
        // 將音量調大
        gain2.gain.setValueAtTime(1.0, audioCtx.currentTime + 0.1);
        gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
        
        osc2.start(audioCtx.currentTime + 0.1);
        osc2.stop(audioCtx.currentTime + 0.3);

    } catch (e) {
        console.error("Audio API error: ", e);
    }
}
// -----------------------------------
// -----------------------------------

let currentMode = 'checkin';
const mainBtn = document.getElementById('main-btn');
const statusIcon = document.getElementById('status-icon');
const instruction = document.getElementById('instruction');
const registrationInputArea = document.getElementById('registration-input-area');
const userIdInput = document.getElementById('target_user_id');
const previewBox = document.getElementById('preview-box');
const searchSection = document.getElementById('search-section');

function switchMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    statusIcon.className = 'status-circle';
    statusIcon.style.background = "";
    statusIcon.innerHTML = '<i class="fas fa-rss"></i>';
    
    if (mode === 'checkin') {
        document.getElementById('mode-checkin').classList.add('active');
        searchSection.classList.remove('d-none');
        registrationInputArea.classList.remove('d-none');
        instruction.innerText = "Registration Mode";
        mainBtn.innerText = "BIND & WRITE TAG";
        mainBtn.className = "btn-action";
        mainBtn.disabled = false;
    } else {
        document.getElementById('mode-finish').classList.add('active', 'finish-mode');
        searchSection.classList.add('d-none');
        registrationInputArea.classList.add('d-none');
        instruction.innerText = "Finish Line Mode";
        mainBtn.innerText = "OPEN FINISH SCANNER";
        mainBtn.className = "btn-action finish-mode";
        mainBtn.disabled = false;
        clearSelection();
    }
}

function selectRunner(id, name, avatar) {
    if(currentMode !== 'checkin') return;
    userIdInput.value = id;
    document.getElementById('prev-img').src = avatar;
    document.getElementById('prev-name').innerText = name;
    document.getElementById('prev-id').innerText = "ID: #" + id;
    previewBox.style.display = "flex";
    registrationInputArea.classList.add('d-none');
}

function clearSelection() {
    userIdInput.value = "";
    previewBox.style.display = "none";
    if(currentMode === 'checkin') registrationInputArea.classList.remove('d-none');
}

function filterRunners() {
    const q = document.getElementById('runner-search').value.toLowerCase();
    document.querySelectorAll('.runner-item').forEach(item => {
        const text = item.getAttribute('data-name').toLowerCase() + item.getAttribute('data-id');
        item.style.display = text.includes(q) ? 'flex' : 'none';
    });
}

async function handleAction() {
    if (!('NDEFReader' in window)) return alert("Web NFC not supported.");
    if (currentMode === 'checkin') {
        const userId = userIdInput.value;
        if (!userId) return alert("Select a runner!");
        try {
            const ndef = new NDEFReader();
            instruction.innerText = "Ready to Write...";
            statusIcon.classList.add('active-scan');
            await ndef.write("PRT_USER_" + userId);
            showSuccess("BOUND: #" + userId);
            verifyOnServer("PRT_USER_" + userId, userId);
            setTimeout(() => switchMode('checkin'), 2000);
        } catch (e) { alert("Error: " + e); statusIcon.classList.remove('active-scan'); }
    } else {
        try {
            const ndef = new NDEFReader();
            await ndef.scan();
            statusIcon.classList.add('active-scan', 'finish-mode');
            instruction.innerText = "Scanner Active...";
            mainBtn.disabled = true;
            ndef.onreading = event => {
                const decoder = new TextDecoder();
                for (const record of event.message.records) {
                    let tagData = decoder.decode(record.data);
                    if (tagData.includes("PRT_USER_")) {
                        const extractedId = tagData.split("PRT_USER_")[1];
                        verifyOnServer(tagData, extractedId);
                        if (navigator.vibrate) navigator.vibrate(200);
                    }
                }
            };
        } catch (e) { alert("NFC Error: " + e); }
    }
}

function showSuccess(msg) {
    statusIcon.classList.remove('active-scan');
    statusIcon.style.background = "var(--success)";
    statusIcon.innerHTML = '<i class="fas fa-check" style="color:white"></i>';
    instruction.innerText = msg;
}

function verifyOnServer(tagData, userId) {
    const formData = new FormData();
    formData.append('tag_data', tagData);
    formData.append('user_id', userId);
    formData.append('event_id', '<?php echo $event_id; ?>');
    formData.append('mode', currentMode);

    fetch('verify_nfc.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // --- 触发清脆的成功哔声！ ---
            playSuccessBeep(); 
            
            showSuccess(currentMode === 'finish' ? "FINISH: #" + userId : "READY: #" + userId);
            if(currentMode === 'finish') {
                setTimeout(() => {
                    statusIcon.style.background = "";
                    statusIcon.classList.add('active-scan', 'finish-mode');
                    statusIcon.innerHTML = '<i class="fas fa-rss"></i>';
                    instruction.innerText = "Waiting for Next...";
                }, 2000);
            }
        } else {
            alert(data.message);
            statusIcon.style.background = "var(--danger)";
        }
    });
}
</script>
</body>
</html>