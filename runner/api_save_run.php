<?php

// api_save_run.php

session_start();

http_response_code(200); // Force HTTP 200 to tell the firewall "everything is normal"

header('Content-Type: application/json');



require '../db_connect.php';
require_once '../auth_check.php';



if (!isset($_SESSION['user_id'])) {

    echo json_encode(["status" => "error", "message" => "Unauthorized"]);

    exit();

}



// Receive POST data

$distance = $_POST['distance'] ?? 0;

$duration = $_POST['duration'] ?? 0;

$encoded_route = $_POST['route_encoded'] ?? '';



// [Key Step 3] Decode Base64 data

$gps_data = base64_decode($encoded_route);



// If decoding fails (e.g. empty), use an empty array

if (!$gps_data) {

    $gps_data = '[]';

}



$user_id = $_SESSION['user_id'];

$start_time = date("Y-m-d H:i:s", time() - $duration);

$end_time = date("Y-m-d H:i:s");

$calories = $distance * 60; 



// Calculate pace

if ($distance > 0) {

    $pace_raw = ($duration / 60) / $distance;

    $pace_mins = floor($pace_raw);

    $pace_secs = round(($pace_raw - $pace_mins) * 60);

    $pace = sprintf("%d'%02d\"", $pace_mins, $pace_secs);

} else {

    $pace = "0'00\"";

}



// Insert into database

$sql = "INSERT INTO run_activities (user_id, start_time, end_time, distance_km, duration_seconds, calories_burned, avg_pace_min_km, gps_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";



if ($stmt = $conn->prepare($sql)) {

    $stmt->bind_param("issddsss", $user_id, $start_time, $end_time, $distance, $duration, $calories, $pace, $gps_data);

    

    if ($stmt->execute()) {

        echo json_encode(["status" => "success", "message" => "Run saved!"]);

    } else {

        // If the database returns an error, send back the detailed error

        echo json_encode(["status" => "error", "message" => "DB Error: " . $stmt->error]);

    }

    $stmt->close();

} else {

    echo json_encode(["status" => "error", "message" => "Prepare Error: " . $conn->error]);

}

?>

