<?php

require_once '../auth_check.php'; 
require_once '../db_connect.php'; 


ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SESSION['role'] !== 'event_manager') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}



if (isset($_GET['id'])) {

    $event_id = intval($_GET['id']);

    $manager_id = $_SESSION['user_id'];



    

    $conn->begin_transaction();



    try {

        // check event for who manager

        $check = $conn->prepare("SELECT event_id FROM events WHERE event_id = ? AND manager_id = ?");

        $check->bind_param("ii", $event_id, $manager_id);

        $check->execute();

        $check->store_result();



        if ($check->num_rows === 0) {

            throw new Exception("Access Denied or Event Not Found.");

        }

        $check->close();



        // delete participation

        $stmt1 = $conn->prepare("DELETE FROM participations WHERE event_id = ?");

        $stmt1->bind_param("i", $event_id);

        if (!$stmt1->execute()) {

            throw new Exception("Error deleting participations: " . $conn->error);

        }

        $stmt1->close();



        // delete event

        $stmt2 = $conn->prepare("DELETE FROM events WHERE event_id = ?");

        $stmt2->bind_param("i", $event_id);

        if (!$stmt2->execute()) {

            throw new Exception("Error deleting event: " . $conn->error);

        }

        $stmt2->close();



        // submit

        $conn->commit();



        // jump to dashboard

        header("Location: manager_dashboard.php?msg=deleted");

        exit();



    } catch (Exception $e) {

        

        $conn->rollback();

        echo "<div style='color:red; padding:20px; text-align:center;'>";

        echo "<h2>Delete Failed (HTTP 500 Fixed)</h2>";

        echo "<p>Error: " . $e->getMessage() . "</p>";

        echo "<a href='manager_dashboard.php'>Return to Dashboard</a>";

        echo "</div>";

    }



} else {

    header("Location: manager_dashboard.php");

    exit();

}

?>