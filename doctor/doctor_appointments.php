<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$doctor_id = (int) $_SESSION['doctor_id'];
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle booking
$success = $error = "";
$slots = ['slot1'=>'08:00 - 09:00', 'slot2'=>'09:00 - 10:00', 'slot3'=>'10:00 - 11:00', 'slot4'=>'11:00 - 12:00'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $patient_id = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
    $appointment_date = $_POST['appointment_date'] ?? '';
    $slot = $_POST['slot'] ?? '';

    // Validate inputs
    if ($patient_id <= 0) {
        $error = "Select a patient.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $appointment_date)) {
        $error = "Invalid date format.";
    } elseif (!array_key_exists($slot, $slots)) {
        $error = "Invalid slot selected.";
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = "Cannot book past dates.";
    } else {
        // Check if slot is already booked
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND slot = ? AND status = 'booked'");
        $stmt->bind_param("iss", $doctor_id, $appointment_date, $slot);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $error = "Selected slot is already booked.";
            $stmt->close();
        } else {
            $stmt->close();
            // Insert appointment as pending
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, slot, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $slot);
            if ($stmt->execute()) {
                $success = "Appointment request submitted!";
            } else {
                $error = "Error booking appointment: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch patients for dropdown
$stmt = $conn->prepare("SELECT id, name FROM patients ORDER BY name ASC");
$stmt->execute();
$patients = $stmt->get_result();
$stmt->close();

// Fetch booked slots for selected date if available
$booked_slots = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appointment_date'])) {
    $appointment_date = $_POST['appointment_date'];
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $appointment_date)) {
        $stmt = $conn->prepare("SELECT slot FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = 'booked'");
        $stmt->bind_param("is", $doctor_id, $appointment_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $booked_slots[] = $row['slot'];
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Book Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .slot-booked { background-color: #f8d7da; color: #721c24; }
        .slot-available { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h3>Book Appointment</h3>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label>Patient</label>
            <select name="patient_id" class="form-select" required>
                <option value="">Select Patient</option>
                <?php while($p = $patients->fetch_assoc()) { ?>
                    <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Appointment Date</label>
            <input type="date" name="appointment_date" class="form-control" value="<?= isset($appointment_date)?htmlspecialchars($appointment_date):''; ?>" min="<?= date('Y-m-d'); ?>" required>
        </div>
        <div class="mb-3">
            <label>Select Slot</label>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach($slots as $key => $time): 
                    $disabled = in_array($key, $booked_slots) ? 'disabled' : '';
                    $class = in_array($key, $booked_slots) ? 'slot-booked' : 'slot-available';
                ?>
                <div>
                    <input type="radio" name="slot" value="<?= $key; ?>" id="<?= $key; ?>" <?= $disabled ?> required>
                    <label for="<?= $key; ?>" class="btn btn-sm <?= $class ?>"><?= $time; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" name="book_appointment" class="btn btn-primary">Book Appointment</button>
        <a href="doctor_dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
