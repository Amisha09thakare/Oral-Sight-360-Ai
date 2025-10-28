<?php
session_start();
header('Content-Type: application/json');
include("../connection/db.php");

$doctor_id = (int) ($_GET['doctor_id'] ?? 0);
$month = (int) ($_GET['month'] ?? date('m'));
$year = (int) ($_GET['year'] ?? date('Y'));

if (!$doctor_id || !$month || !$year) {
    echo json_encode(["status" => "error", "message" => "Missing params"]);
    exit;
}

$holidays = ["2025-01-26","2025-08-15","2025-10-02"];
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$availability = [];

for ($d=1; $d <= $days_in_month; $d++) {
    $date = sprintf("%04d-%02d-%02d", $year, $month, $d);
    $day = date('w', strtotime($date));

    // Sundays/holidays = unavailable
    if ($day == 0 || in_array($date, $holidays)) {
        $availability[$date] = false;
        continue;
    }

    // Slots (default)
    $slots = ["09:00 AM","10:00 AM","11:00 AM","02:00 PM","03:00 PM","04:00 PM"];

    // Saturdays = 2 slots
    if ($day == 6) {
        $slots = array_slice($slots, 0, 2);
    }

    // Count booked
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND appointment_date=? AND status IN ('pending','booked')");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $availability[$date] = ($c < count($slots));
}

echo json_encode(["status" => "success", "availability" => $availability]);
?>
