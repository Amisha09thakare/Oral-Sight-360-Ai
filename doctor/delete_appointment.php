<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$id = (int) $_GET['id'];
$conn->query("DELETE FROM appointments WHERE id=$id");

header("Location: doctor_dashboard.php");
exit;
