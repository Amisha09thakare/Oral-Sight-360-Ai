<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_dashboard.php");
    exit;
}

$doctor_id = (int) $_SESSION['doctor_id'];
$patient_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($patient_id <= 0) {
    header("Location: doctor_dashboard.php");
    exit;
}

// Fetch existing patient
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("Location: doctor_dashboard.php");
    exit;
}

$success = $error = "";

// Handle form submission
if (isset($_POST['edit_patient'])) {
    $name   = trim($_POST['name']);
    $email  = trim($_POST['email']);
    $dob    = trim($_POST['dob']);
    $phone  = trim($_POST['phone']);
    $age    = trim($_POST['age']);
    $gender = trim($_POST['gender']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dob)) {
        $error = "Invalid date format.";
    } elseif (!is_numeric($age) || $age < 0) {
        $error = "Invalid age.";
    } elseif (!preg_match("/^\d{10,15}$/", $phone)) {
        $error = "Invalid phone number.";
    } elseif (!in_array($gender, ['male','female','other'])) {
        $error = "Invalid gender.";
    } else {
        // Check if another patient has the same email
        $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $error = "Email already registered.";
            $stmt->close();
        } else {
            $stmt->close();
            // Update the patient
            $stmt = $conn->prepare("UPDATE patients SET name = ?, email = ?, dob = ?, phone = ?, age = ?, gender = ? WHERE id = ?");
            $stmt->bind_param("ssssisi", $name, $email, $dob, $phone, $age, $gender, $patient_id);
            if ($stmt->execute()) {
                $success = "Patient updated successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Edit Patient</h3>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <div class="mb-3"><label>Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($patient['name']); ?>" required></div>
        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email']); ?>" required></div>
        <div class="mb-3"><label>DOB</label><input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($patient['dob']); ?>" required></div>
        <div class="mb-3"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($patient['phone']); ?>" required></div>
        <div class="mb-3"><label>Age</label><input type="number" name="age" class="form-control" value="<?= htmlspecialchars($patient['age']); ?>" required></div>
        <div class="mb-3"><label>Gender</label>
            <select name="gender" class="form-select" required>
                <option value="male" <?= $patient['gender']=='male'?'selected':''; ?>>Male</option>
                <option value="female" <?= $patient['gender']=='female'?'selected':''; ?>>Female</option>
                <option value="other" <?= $patient['gender']=='other'?'selected':''; ?>>Other</option>
            </select>
        </div>
        <button type="submit" name="edit_patient" class="btn btn-primary">Update Patient</button>
        <a href="doctor_dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
