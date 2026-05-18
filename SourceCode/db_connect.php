<?php
// db_connect.php

date_default_timezone_set('Asia/Kuala_Lumpur');

$servername = "localhost";
$username = "codexbiz_koo";
$password = 'g39Aal@PluwU'; 
$dbname = "codexbiz_koo";


$conn = new mysqli($servername, $username, $password, $dbname);

// error checking
if ($conn->connect_error) {
    die(" (Connection Failed): " . $conn->connect_error);
}

$conn->query("SET time_zone = '+08:00'");
?>