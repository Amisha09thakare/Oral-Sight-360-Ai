<?php
session_start();
include("../connection/db.php");
if (!isset($_SESSION['admin_id'])) { header("Location: doctor_login.php"); exit; }
$admin = (int)$_SESSION['admin_id'];
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!$action) { header("Location: doctor_dashboard.php"); exit; }

switch($action) {
  case 'approve':
    // set status booked
    $conn->query("UPDATE appointments SET status='booked' WHERE id=$id AND doctor_id=$admin");
    break;
  case 'reject':
    // delete immediately (you said you want auto-delete on rejection)
    $conn->query("DELETE FROM appointments WHERE id=$id AND doctor_id=$admin");
    break;
  case 'complete':
    $conn->query("UPDATE appointments SET status='completed' WHERE id=$id AND doctor_id=$admin");
    // also increment visits for patient in treatment_progress if exists
    $ap = $conn->query("SELECT patient_id FROM appointments WHERE id=$id")->fetch_assoc();
    if ($ap && $ap['patient_id']) {
      $pid = (int)$ap['patient_id'];
      $conn->query("INSERT INTO treatment_progress (patient_id, total_weeks, completed_weeks, visits) VALUES ($pid, NULL, 0, 1) ON DUPLICATE KEY UPDATE visits = visits + 1");
    }
    break;
  case 'delete':
    $conn->query("DELETE FROM appointments WHERE id=$id AND doctor_id=$admin");
    break;
  case 'cleanup':
    // remove cancelled older than 7 days
    $conn->query("DELETE FROM appointments WHERE status='cancelled' AND appointment_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND doctor_id=$admin");
    break;
}

header("Location: doctor_dashboard.php");
exit;

