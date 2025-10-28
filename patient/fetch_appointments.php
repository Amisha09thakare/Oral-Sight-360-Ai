<?php
session_start();
include("../connection/db.php");

$patient_id = $_SESSION['patient_id'];
$res = $conn->query("SELECT appointment_date, slot, status FROM appointments WHERE patient_id=$patient_id");

$events = [];
while ($row = $res->fetch_assoc()) {
    $events[] = [
        'title' => $row['slot'] . " (" . $row['status'] . ")",
        'start' => $row['appointment_date']
    ];
}
echo json_encode($events);
?>
