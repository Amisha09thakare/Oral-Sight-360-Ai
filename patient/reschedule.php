<?php
session_start();
include("../connection/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_id = (int) $_POST['appointment_id'];
    $new_date = $_POST['appointment_date'];
    $new_slot = $_POST['slot'];

    if (!$appointment_id || !$new_date || !$new_slot) {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, slot = ?, status = 'pending' WHERE id = ?");
    $stmt->bind_param("ssi", $new_date, $new_slot, $appointment_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment rescheduled."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }

    $stmt->close();
}
?>
