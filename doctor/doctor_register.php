<?php
include("../connection/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username   = $_POST['username'];
    $password   = $_POST['password']; // you can use password_hash later
    $email      = $_POST['email'];
    $speciality = $_POST['speciality'];

    $sql = "INSERT INTO admins (username,password) VALUES ('$username','$password')";

    if ($conn->query($sql)) {
        $success = "Doctor registered successfully! <a href='doctor_login.php'>Login Here</a>";
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Registration | Oral Sight AI 360</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1565c0, #42a5f5);
    }
    .register-card {
      max-width: 500px;
      width: 100%;
      background: #fff;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    .register-card h3 {
      font-weight: bold;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="register-card">
    <h3>Doctor Registration</h3>
    <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
      <div class="mb-3">
        <label>Full Name</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div class="mb-3">
        <label>Speciality</label>
        <input type="text" name="speciality" class="form-control">
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-success w-100">Register</button>
    </form>
    <p class="mt-3 text-center">Already registered? <a href="doctor_login.php">Login</a></p>
  </div>
</body>
</html>
