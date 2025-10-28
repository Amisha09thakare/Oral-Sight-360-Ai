<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$doctor_id = (int) $_SESSION['doctor_id'];
$patient_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($patient_id <= 0) {
    header("Location: doctor_dashboard.php");
    exit;
}

// First, check if the patient exists
$stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    $stmt->close();
    header("Location: doctor_dashboard.php");
    exit;
}
$stmt->close();

// Delete the patient
$stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
if ($stmt->execute()) {
    $stmt->close();
    header("Location: doctor_dashboard.php?msg=Patient deleted successfully");
    exit;
} else {
    $error = "Error deleting patient: " . $stmt->error;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Delete Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Delete Patient</h3>
    <?php if (isset($error)) { ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <a href="doctor_dashboard.php" class="btn btn-secondary">Back</a>
    <?php } ?>
</div>
</body>
</html>
