<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$doctor_id = (int) $_SESSION['doctor_id'];
$success = $error = "";

if (isset($_POST['add_plan'])) {
    $patient_id = (int) $_POST['patient_id'];
    $total_weeks = (int) $_POST['total_weeks'];
    $start_date = trim($_POST['start_date']);
    $notes = trim($_POST['notes']);

    if ($total_weeks <= 0) {
        $error = "Total weeks must be greater than 0.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
        $error = "Invalid start date.";
    } else {
        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, doctor_id, total_weeks, start_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $patient_id, $doctor_id, $total_weeks, $start_date, $notes);
        if ($stmt->execute()) {
            // Ensure treatment_progress exists
            $plan_id = $stmt->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("SELECT * FROM treatment_progress WHERE patient_id = ?");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows == 0) {
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO treatment_progress (patient_id, total_weeks, completed_weeks, visits) VALUES (?, ?, 0, 0)");
                $stmt->bind_param("ii", $patient_id, $total_weeks);
                $stmt->execute();
            }
            $stmt->close();

            $success = "Treatment plan added successfully.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Assign Treatment Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Assign Treatment Plan</h3>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label>Select Patient</label>
            <select name="patient_id" class="form-select" required>
                <option value="">Select</option>
                <?php
                $stmt = $conn->prepare("SELECT id, name FROM patients WHERE doctor_id = ?");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $patients = $stmt->get_result();
                while($pt = $patients->fetch_assoc()) {
                    echo "<option value='".$pt['id']."'>".htmlspecialchars($pt['name'])."</option>";
                }
                $stmt->close();
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Total Weeks</label>
            <input type="number" name="total_weeks" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
        </div>
        <div class="col-md-12">
            <label>Notes (optional)</label>
            <textarea name="notes" class="form-control"></textarea>
        </div>
        
        <div class="col-md-12 mt-3">
    <button type="submit" name="add_plan" class="btn btn-success">Assign Plan</button>
    <a href="doctor_dashboard.php" class="btn btn-secondary">Back</a>
</div>

    </form>
</div>
</body>
</html>
