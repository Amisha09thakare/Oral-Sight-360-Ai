<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$doctor_id = (int) $_SESSION['doctor_id'];
$plan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$success = $error = "";

$stmt = $conn->prepare("SELECT tp.*, p.name FROM treatment_plans tp JOIN patients p ON tp.patient_id = p.id WHERE tp.id = ? AND tp.doctor_id = ?");
$stmt->bind_param("ii", $plan_id, $doctor_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) {
    die("Plan not found.");
}

// Fetch visits from completed appointments
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ? AND status = 'completed'");
$stmt->bind_param("i", $plan['patient_id']);
$stmt->execute();
$visits = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if (isset($_POST['update_plan'])) {
    $completed_weeks = (int) $_POST['completed_weeks'];

    if ($completed_weeks < 0 || $completed_weeks > $plan['total_weeks']) {
        $error = "Completed weeks must be between 0 and total weeks.";
    } else {
        // Update treatment_progress
        $stmt = $conn->prepare("UPDATE treatment_progress SET completed_weeks = ?, visits = ? WHERE patient_id = ?");
        $stmt->bind_param("iii", $completed_weeks, $visits, $plan['patient_id']);
        if ($stmt->execute()) {
            $success = "Plan updated successfully.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get current progress
$stmt = $conn->prepare("SELECT * FROM treatment_progress WHERE patient_id = ?");
$stmt->bind_param("i", $plan['patient_id']);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();
$stmt->close();

$remaining = max(0, $plan['total_weeks'] - $progress['completed_weeks']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Treatment Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Edit Plan for <?= htmlspecialchars($plan['name']); ?></h3>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label>Total Weeks</label>
            <input type="number" value="<?= $plan['total_weeks']; ?>" class="form-control" readonly>
        </div>
        <div class="col-md-4">
            <label>Completed Weeks</label>
            <input type="number" name="completed_weeks" value="<?= $progress['completed_weeks']; ?>" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Remaining Weeks</label>
            <input type="number" value="<?= $remaining; ?>" class="form-control" readonly>
        </div>
        <div class="col-md-4">
            <label>Visits</label>
            <input type="number" value="<?= $visits; ?>" class="form-control" readonly>
        </div>
       
        <div class="col-md-12 mt-3">
    <button type="submit" name="update_plan" class="btn btn-primary">Update Plan</button>
    <a href="doctor_dashboard.php" class="btn btn-secondary">Back</a>
</div>

    </form>
</div>
</body>
</html>
