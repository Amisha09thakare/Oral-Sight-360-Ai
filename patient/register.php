<?php
include("../connection/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $age      = $_POST['age'];
    $gender   = $_POST['gender'];
    $phone    = $_POST['phone'];

    // Generate default password (dob + last 3 digits of phone)
    $dob = $_POST['dob'];
    $defaultPass = $dob . substr($phone, -3);
    $password = password_hash($defaultPass, PASSWORD_DEFAULT);

    $sql = "INSERT INTO patients (name,email,password,age,gender,phone,created_at)
            VALUES ('$name','$email','$password','$age','$gender','$phone',NOW())";

    if ($conn->query($sql)) {
        $success = "Registration successful! <a href='login.php'>Login Here</a>";
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | Oral Sight AI 360</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1976d2, #42a5f5);
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
    <h3>Patient Registration</h3>
    <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
      <div class="mb-3">
        <label>Full Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Date of Birth</label>
        <input type="date" name="dob" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Age</label>
        <input type="number" name="age" class="form-control">
      </div>
      <div class="mb-3">
        <label>Gender</label>
        <select name="gender" class="form-select">
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>
      </div>
      <button type="submit" class="btn btn-success w-100">Register</button>
    </form>
    <p class="mt-3 text-center">Already registered? <a href="login.php">Login</a></p>
  </div>
</body>
</html>
