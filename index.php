<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['doctor_id'])) {
    header("Location: doctor/dashboard.php");
    exit;
}
if (isset($_SESSION['patient_id'])) {
    header("Location: patient/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Oral Sight AI 360 | Welcome</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(120deg, #1565c0, #42a5f5);
      height: 100vh;
      display: flex; justify-content: center; align-items: center;
    }
    .card {
      width: 400px; text-align: center;
      border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    h3 { color: #1565c0; }
    .btn { width: 100%; margin: 10px 0; }
  </style>
</head>
<body>
  <div class="card p-4 bg-white">
    <h3 class="mb-4">🦷 Oral Sight AI 360</h3>
    <p class="text-muted">Choose your login type</p>
    <a href="doctor/doctor_login.php" class="btn btn-primary">Doctor Login</a>
    <a href="patient/login.php" class="btn btn-success">Patient Login</a>
  </div>
</body>
</html>
