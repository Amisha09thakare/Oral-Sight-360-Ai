<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['patient_id'])){
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];

if(isset($_POST['book'])){
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $slot = $_POST['slot'];

    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, slot, status) VALUES (?,?,?,?, 'pending')");
    $stmt->bind_param("iiss", $patient_id, $doctor_id, $date, $slot);
    if($stmt->execute()){
        $msg = "✅ Appointment request sent. Waiting for doctor approval.";
    } else {
        $msg = "❌ Error booking appointment.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Book Appointment</title>
  <style>
    .slot { padding:10px; margin:5px; cursor:pointer; border-radius:5px; display:inline-block; }
    .available { background:lightgreen; }
    .booked { background:tomato; color:#fff; }
    .past { background:#ccc; color:#666; }
  </style>
</head>
<body>
<h2>Book Appointment</h2>
<?php if(isset($msg)) echo "<p>$msg</p>"; ?>

<form method="post">
  <label>Select Doctor:</label>
  <select name="doctor_id" required>
    <?php
    $res = $conn->query("SELECT id, username FROM admins");
    while($row = $res->fetch_assoc()){
        echo "<option value='{$row['id']}'>{$row['username']}</option>";
    }
    ?>
  </select><br><br>

  <label>Select Date:</label>
  <input type="date" name="date" required min="<?= date('Y-m-d') ?>"><br><br>

  <label>Choose Slot:</label><br>
  <?php
  $slots = ['slot1','slot2','slot3','slot4'];
  foreach($slots as $s){
      echo "<label><input type='radio' name='slot' value='$s' required> $s</label><br>";
  }
  ?><br>

  <button type="submit" name="book">Book</button>
</form>

</body>
</html>
