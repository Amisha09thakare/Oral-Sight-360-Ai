<?php
session_start();
include '../config/db.php'; // your DB connection

// Ensure doctor is logged in
if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit();
}

if(isset($_POST['upload_scan'])){
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_SESSION['doctor_id'];
    $file = $_FILES['scan_file'];

    $allowed = ['stl','ply','obj','glb','gltf','png','jpg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(in_array($ext, $allowed)){
        $target = "uploads/scans/" . time() . "_" . basename($file['name']);
        
        if(move_uploaded_file($file['tmp_name'], $target)){
            // Insert into scans table
            $stmt = $conn->prepare("INSERT INTO scans (admin_id, patient_id, patient_name, scan_file, file_type, status) 
                                    VALUES (?, ?, (SELECT name FROM patients WHERE id=?), ?, ?, 'pending')");
            $stmt->bind_param("iisss", $doctor_id, $patient_id, $patient_id, $target, $ext);
            $stmt->execute();
            $msg = "✅ Scan uploaded successfully!";
        } else {
            $msg = "❌ Error uploading file.";
        }
    } else {
        $msg = "❌ Invalid file type!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Upload Patient Scan</title>
</head>
<body>
  <h2>Upload 3D Scan for Patient</h2>
  <?php if(isset($msg)) echo "<p>$msg</p>"; ?>

  <form method="post" enctype="multipart/form-data">
      <label>Choose Patient:</label>
      <select name="patient_id" required>
          <?php
          $result = $conn->query("SELECT id, name FROM patients");
          while($row = $result->fetch_assoc()){
              echo "<option value='{$row['id']}'>{$row['name']}</option>";
          }
          ?>
      </select><br><br>

      <input type="file" name="scan_file" required><br><br>
      <button type="submit" name="upload_scan">Upload</button>
  </form>
</body>
</html>
