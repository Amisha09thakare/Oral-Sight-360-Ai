<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}

$id = (int) $_GET['id'];
$appt = $conn->query("SELECT * FROM appointments WHERE id=$id")->fetch_assoc();

if (isset($_POST['update'])) {
    $date = $_POST['date'];
    $slot = $_POST['slot'];
    $status = $_POST['status'];

    $sql = "UPDATE appointments SET appointment_date='$date', slot='$slot', status='$status' WHERE id=$id";
    if ($conn->query($sql)) {
        header("Location: doctor_dashboard.php");
        exit;
    } else {
        $error = "Error: ".$conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Edit Appointment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<h3>Edit Appointment</h3>
<?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
<form method="POST" class="row g-3">
  <div class="col-md-4"><label>Date</label><input type="date" name="date" class="form-control" value="<?= $appt['appointment_date']; ?>" required></div>
  <div class="col-md-4"><label>Slot</label>
    <select name="slot" class="form-select">
      <option <?= $appt['slot']=='slot1'?'selected':''; ?> value="slot1">9 AM - 10 AM</option>
      <option <?= $appt['slot']=='slot2'?'selected':''; ?> value="slot2">10 AM - 11 AM</option>
      <option <?= $appt['slot']=='slot3'?'selected':''; ?> value="slot3">11 AM - 12 PM</option>
      <option <?= $appt['slot']=='slot4'?'selected':''; ?> value="slot4">12 PM - 1 PM</option>
    </select>
  </div>
  <div class="col-md-4"><label>Status</label>
    <select name="status" class="form-select">
      <option <?= $appt['status']=='pending'?'selected':''; ?>>pending</option>
      <option <?= $appt['status']=='booked'?'selected':''; ?>>booked</option>
      <option <?= $appt['status']=='completed'?'selected':''; ?>>completed</option>
      <option <?= $appt['status']=='cancelled'?'selected':''; ?>>cancelled</option>
    </select>
  </div>
  <div class="col-md-12"><button type="submit" name="update" class="btn btn-primary">Update</button></div>
</form>
</body></html>
