<?php
session_start();
include("../connection/db.php");

header("Content-Type: application/json");

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$patient_id = (int) $_SESSION['patient_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? "";

if ($id <= 0 || empty($action)) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

// Ensure appointment belongs to this patient
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
$stmt->bind_param("ii", $id, $patient_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo json_encode(["status" => "error", "message" => "Appointment not found"]);
    exit;
}

if ($action === "cancel" && $appointment['status'] === "pending") {
    $stmt = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
