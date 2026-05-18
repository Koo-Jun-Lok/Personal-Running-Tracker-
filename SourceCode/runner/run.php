<?php

require_once '../auth_check.php'; // 引入拦截器
require_once '../db_connect.php'; // 引入数据库

// 开启错误调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check Login

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");

    exit();

}



// --- Handle Saving Run (AJAX) ---

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_run'])) {

    $user_id = $_SESSION['user_id'];

    $distance = floatval($_POST['distance']); 

    $duration = intval($_POST['duration']);   

    $coords = $_POST['coords'];               

    

    // Prevent saving ghost runs

    if ($distance < 0.01) {

        echo json_encode(["status" => "error", "msg" => "Distance too short."]);

        exit();

    }



    $calories = $distance * 60;

    

    $end_time = date("Y-m-d H:i:s");

    $start_time = date("Y-m-d H:i:s", strtotime($end_time) - $duration);



    $sql = "INSERT INTO run_activities 

            (user_id, start_time, end_time, distance_km, duration_seconds, calories_burned, gps_data) 

            VALUES (?, ?, ?, ?, ?, ?, ?)";

            

    if ($stmt = $conn->prepare($sql)) {

        $stmt->bind_param("issdiis", $user_id, $start_time, $end_time, $distance, $duration, $calories, $coords);

        if ($stmt->execute()) {

            echo json_encode(["status" => "success", "msg" => "Run Saved!"]);

        } else {

            echo json_encode(["status" => "error", "msg" => "Database Error"]);

        }

    }

    exit();

}

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">

    <title>Running Tracker</title>



    <link rel="manifest" href="manifest.json">

    <meta name="theme-color" content="#2563EB">



    <link rel="stylesheet" href="../style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />



    <style>

        body {

            height: 100vh;

            display: flex;

            flex-direction: column;

            overflow: hidden; 

            background: #fff;

            font-family: 'Segoe UI', sans-serif;

        }



        #map {

            flex: 1;

            width: 100%;

            z-index: 1;

        }



        /* --- Recenter Button --- */

        .map-overlay-btn {

            position: absolute;

            right: 20px;

            bottom: 340px; /* Above the panel */

            width: 45px;

            height: 45px;

            background: white;

            border-radius: 50%;

            box-shadow: 0 4px 10px rgba(0,0,0,0.2);

            z-index: 1000;

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            border: none;

            color: #4B5563;

            transition: 0.2s;

        }

        .map-overlay-btn:active { transform: scale(0.95); }

        .map-overlay-btn i { font-size: 18px; }



        /* --- GPS Marker --- */

        .gps-marker-container {

            position: relative;

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        .gps-dot {

            width: 16px; height: 16px;

            background: #2563EB;

            border: 3px solid white;

            border-radius: 50%;

            box-shadow: 0 0 5px rgba(0,0,0,0.3);

            z-index: 2;

        }

        .gps-arrow {

            position: absolute;

            top: -5px;

            width: 0; 

            height: 0; 

            border-left: 10px solid transparent;

            border-right: 10px solid transparent;

            border-bottom: 25px solid rgba(37, 99, 235, 0.4); 

            transform-origin: center bottom;

            z-index: 1;

            transition: transform 0.2s linear;

        }



        /* --- Bottom Panel --- */

        .run-panel {

            height: 320px;

            background: white;

            border-top-left-radius: 30px;

            border-top-right-radius: 30px;

            box-shadow: 0 -5px 25px rgba(0,0,0,0.1);

            position: relative;

            z-index: 1001; 

            margin-top: -25px;

            padding: 25px 20px;

            display: flex;

            flex-direction: column;

            align-items: center;

        }



        .status-badge {

            background: #F3F4F6;

            color: #6B7280;

            padding: 6px 16px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 800;

            margin-bottom: 15px;

            letter-spacing: 1px;

        }



        .timer-display {

            font-size: 56px;

            font-weight: 800;

            font-family: 'Courier New', monospace; 

            color: #111827;

            margin-bottom: 15px;

            line-height: 1;

        }



        .stats-row {

            display: flex;

            justify-content: space-between;

            width: 100%;

            max-width: 300px;

            margin-bottom: 30px;

        }



        .stat-box { text-align: center; }

        .stat-val { font-size: 26px; font-weight: 800; color: #1F2937; }

        .stat-lbl { font-size: 11px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }



        .controls { display: flex; gap: 25px; align-items: center; }



        .btn-circle {

            width: 65px; height: 65px;

            border-radius: 50%; border: none;

            display: flex; align-items: center; justify-content: center;

            font-size: 22px; cursor: pointer;

            box-shadow: 0 5px 15px rgba(0,0,0,0.15);

            transition: all 0.2s;

        }

        .btn-circle:active { transform: scale(0.92); }



        #btn-toggle {

            background: linear-gradient(135deg, #2563EB, #1D4ED8);

            color: white;

            width: 85px; height: 85px; 

            font-size: 32px;

            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);

        }



        #btn-stop { background: #FEE2E2; color: #DC2626; display: none; }

        #btn-lock { background: #F3F4F6; color: #4B5563; }



        /* UI Improvements */

        .leaflet-control-layers {

            border: none !important;

            border-radius: 12px !important;

            box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;

            font-weight: 600;

        }

        

        /* Make attribution text clear but small, positioned top-left via JS */

        .leaflet-control-attribution {

            font-size: 9px !important;

            background: rgba(255, 255, 255, 0.7) !important;

            padding: 2px 5px !important;

            border-radius: 4px;

        }

    </style>

</head>

<body>



    <div id="map"></div>



    <button class="map-overlay-btn" id="btn-recenter" onclick="recenterMap()">

        <i class="fas fa-crosshairs"></i>

    </button>



    <div class="run-panel">

        <div class="status-badge" id="status-text">READY TO RUN</div>



        <div class="timer-display" id="timer">00:00:00</div>



        <div class="stats-row">

            <div class="stat-box">

                <div class="stat-val" id="dist-display">0.00</div>

                <div class="stat-lbl">Kilometers</div>

            </div>

            <div class="stat-box">

                <div class="stat-val" id="pace-display">0'00"</div>

                <div class="stat-lbl">Avg Pace</div>

            </div>

        </div>



        <div class="controls">

            <button id="btn-stop" class="btn-circle" onclick="stopRun()">

                <i class="fas fa-stop"></i>

            </button>



            <button id="btn-toggle" class="btn-circle" onclick="toggleRun()">

                <i class="fas fa-play" id="icon-toggle"></i>

            </button>



            <button id="btn-lock" class="btn-circle" onclick="history.back()">

                <i class="fas fa-times"></i>

            </button>

        </div>

    </div>



<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>



<script>

    // --- 1. Initialize Map ---

    

    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {

        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

    });



    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {

        attribution: '&copy; Esri'

    });



    const map = L.map('map', {

        center: [3.1390, 101.6869], 

        zoom: 16,

        zoomControl: false, 

        attributionControl: false, 

        layers: [streetLayer]

    });



    

    L.control.attribution({

        position: 'topleft'

    }).addTo(map);



    const baseMaps = { "Street": streetLayer, "Satellite": satelliteLayer };

    L.control.layers(baseMaps).addTo(map);



    // --- 2. Styles & Markers ---

    const outlineLine = L.polyline([], { color: 'white', weight: 8, opacity: 1 }).addTo(map);

    const mainLine = L.polyline([], { color: '#2563EB', weight: 5, opacity: 1, lineCap: 'round', lineJoin: 'round' }).addTo(map);



    const gpsIcon = L.divIcon({

        className: 'gps-marker-wrapper',

        html: `<div class="gps-marker-container"><div class="gps-arrow" id="gps-arrow"></div><div class="gps-dot"></div></div>`,

        iconSize: [40, 40],

        iconAnchor: [20, 20]

    });

    const userMarker = L.marker([0, 0], { icon: gpsIcon }).addTo(map);



    // --- 3. Variables ---

    let isRunning = false;

    let isPaused = false;

    let elapsedTime = 0;

    let timerInterval;

    let watchID;

    let routeCoordinates = [];

    let totalDistance = 0;

    let lastPosition = null;



    // --- 4. Logic ---

    function toggleRun() {

        const icon = document.getElementById('icon-toggle');

        const status = document.getElementById('status-text');

        const stopBtn = document.getElementById('btn-stop');

        const lockBtn = document.getElementById('btn-lock');

        const toggleBtn = document.getElementById('btn-toggle');



        if (!isRunning) {

            isRunning = true;

            isPaused = false;

            status.textContent = "RECORDING...";

            status.style.color = "#2563EB";

            status.style.background = "#EFF6FF";

            icon.className = "fas fa-pause";

            toggleBtn.style.background = "#2563EB";

            lockBtn.style.display = 'none';

            startTimer();

            startGPS();

        } else if (!isPaused) {

            isPaused = true;

            status.textContent = "PAUSED";

            status.style.color = "#D97706";

            status.style.background = "#FFFBEB";

            icon.className = "fas fa-play";

            toggleBtn.style.background = "#F59E0B";

            stopBtn.style.display = 'flex';

            clearInterval(timerInterval);

        } else {

            isPaused = false;

            status.textContent = "RECORDING...";

            status.style.color = "#2563EB";

            status.style.background = "#EFF6FF";

            icon.className = "fas fa-pause";

            toggleBtn.style.background = "#2563EB";

            stopBtn.style.display = 'none';

            startTimer();

        }

    }



    function startTimer() {

        timerInterval = setInterval(() => {

            elapsedTime++;

            updateTimerUI();

        }, 1000);

    }



    function updateTimerUI() {

        const h = Math.floor(elapsedTime / 3600);

        const m = Math.floor((elapsedTime % 3600) / 60);

        const s = elapsedTime % 60;

        document.getElementById('timer').textContent =

            `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

    }



    function startGPS() {

        if (!navigator.geolocation) return;

        watchID = navigator.geolocation.watchPosition(pos => {

            const lat = pos.coords.latitude;

            const lng = pos.coords.longitude;

            const heading = pos.coords.heading;

            const currentLatLng = [lat, lng];



            userMarker.setLatLng(currentLatLng);

            lastPosition = currentLatLng;



            if (heading !== null && heading !== undefined) {

                const arrow = document.querySelector('#gps-arrow');

                if(arrow) arrow.style.transform = `rotate(${heading}deg)`; 

            }



            if (isRunning && !isPaused) {

                if (routeCoordinates.length > 0) {

                    const lastLatLng = routeCoordinates[routeCoordinates.length - 1];

                    const dist = calcDistance(lastLatLng[0], lastLatLng[1], lat, lng);

                    if (dist > 0.005) { 

                        routeCoordinates.push(currentLatLng);

                        outlineLine.addLatLng(currentLatLng);

                        mainLine.addLatLng(currentLatLng);

                        totalDistance += dist;

                        updateStatsUI();

                        map.panTo(currentLatLng);

                    }

                } else {

                    routeCoordinates.push(currentLatLng);

                    outlineLine.addLatLng(currentLatLng);

                    mainLine.addLatLng(currentLatLng);

                    map.setView(currentLatLng, 17);

                }

            }

        }, err => console.error(err), { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 });

    }



    function recenterMap() {

        if (lastPosition) {

            map.flyTo(lastPosition, 17);

        } else {

            navigator.geolocation.getCurrentPosition(pos => {

                map.flyTo([pos.coords.latitude, pos.coords.longitude], 17);

            });

        }

    }



    function calcDistance(lat1, lon1, lat2, lon2) {

        const R = 6371; 

        const dLat = (lat2 - lat1) * Math.PI / 180;

        const dLon = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;

        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    }



    function updateStatsUI() {

        document.getElementById('dist-display').textContent = totalDistance.toFixed(2);

        let paceText = "0'00\"";

        if (totalDistance > 0.05) {

            const totalMinutes = elapsedTime / 60;

            const paceDecimal = totalMinutes / totalDistance;

            paceText = `${Math.floor(paceDecimal)}'${String(Math.round((paceDecimal - Math.floor(paceDecimal)) * 60)).padStart(2,'0')}"`;

        }

        document.getElementById('pace-display').textContent = paceText;

    }



    function stopRun() {

        if (totalDistance < 0.01) {

            alert("⚠️ You haven't moved enough to save this run!\nGet moving!");

            return;

        }

        if (!confirm("Are you sure you want to finish this run?")) return;



        const fd = new FormData();

        fd.append('save_run', true);

        fd.append('distance', totalDistance.toFixed(3));

        fd.append('duration', elapsedTime);

        fd.append('coords', JSON.stringify(routeCoordinates));



        document.getElementById('btn-stop').disabled = true;

        document.getElementById('status-text').textContent = "SAVING...";



        fetch('run.php', { method: 'POST', body: fd })

            .then(res => res.json())

            .then(data => {

                if(data.status === 'success') location.href = 'home.php';

                else { alert(data.msg); document.getElementById('btn-stop').disabled = false; }

            })

            .catch(() => { alert('Network Error'); document.getElementById('btn-stop').disabled = false; });

    }



    navigator.geolocation.getCurrentPosition(pos => {

        if(!isRunning) {

            const current = [pos.coords.latitude, pos.coords.longitude];

            map.setView(current, 16);

            userMarker.setLatLng(current);

            lastPosition = current;

        }

    }, null, { enableHighAccuracy: true });

</script>



</body>

</html>