<?php
session_start();
header('Content-Type: application/json');
include("../connection/db.php");

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$patient_id = (int) $_SESSION['patient_id'];
$doctor_id = (int) ($_POST['doctor_id'] ?? 0);
$appointment_date = $_POST['appointment_date'] ?? '';
$slot = $_POST['slot'] ?? '';

if (!$doctor_id || !$appointment_date || !$slot) {
    echo json_encode(["status" => "error", "message" => "Missing fields"]);
    exit;
}

// Check if slot already booked
$stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND slot=? AND status IN ('pending','booked')");
$stmt->bind_param("iss", $doctor_id, $appointment_date, $slot);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Slot already taken"]);
    exit;
}
$stmt->close();

// Insert new request
$stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, slot, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $slot);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Appointment request sent"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
$stmt->close();
$conn->close();
?>
