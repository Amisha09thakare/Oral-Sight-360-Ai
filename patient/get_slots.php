<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    echo json_encode([]);
    exit;
}
$doctor_id = (int) $_SESSION['doctor_id'];
$date = isset($_GET['date']) ? $_GET['date'] : '';
$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM patients WHERE id = ? AND doctor_id = ?");
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode([]);
    exit;
}
$stmt->close();

$slot_times = [
    'slot1' => '09:00 AM - 10:00 AM',
    'slot2' => '11:00 AM - 12:00 PM',
    'slot3' => '02:00 PM - 03:00 PM',
    'slot4' => '04:00 PM - 05:00 PM'
];

$stmt = $conn->prepare("SELECT slot FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('booked','pending')");
$stmt->bind_param("is", $doctor_id, $date);
$stmt->execute();
$res = $stmt->get_result();
$booked = [];
while($row = $res->fetch_assoc()) {
    $booked[] = $row['slot'];
}
$stmt->close();

$available = [];
foreach($slot_times as $key => $label) {
    if (!in_array($key, $booked)) {
        $available[] = ['key' => $key, 'label' => $label];
    }
}

echo json_encode($available);
