<?php
include("../connection/db.php");

$id = $_GET['id'];
$status = $_GET['status'];

$conn->query("UPDATE appointments SET status='$status' WHERE id=$id");

header("Location: doctor_dashboard.php");
exit;
?>
