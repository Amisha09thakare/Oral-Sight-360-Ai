<?php
session_start();
include("../connection/db.php");
if (!isset($_SESSION['admin_id'])) { header("Location: doctor_login.php"); exit; }
$patient = (int)($_GET['patient'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$patient) { header("Location: doctor_dashboard.php"); exit; }

if ($action === 'inc') {
  $conn->query("INSERT INTO treatment_progress (patient_id, total_weeks, completed_weeks, visits) VALUES ($patient, NULL, 1, 0) ON DUPLICATE KEY UPDATE completed_weeks = completed_weeks + 1");
} elseif ($action === 'visits') {
  $conn->query("INSERT INTO treatment_progress (patient_id, total_weeks, completed_weeks, visits) VALUES ($patient, NULL, 0, 1) ON DUPLICATE KEY UPDATE visits = visits + 1");
}

header("Location: doctor_dashboard.php");
exit;
