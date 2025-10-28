<?php
session_start();
include("../connection/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM patients WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['patient_id'] = $user['id'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with this email!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Patient Portal Login | Oral Sight AI 360</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1976d2, #42a5f5);
    }
    .login-card {
      max-width: 400px;
      width: 100%;
      background: #fff;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
      text-align: center;
    }
    .login-card h3 {
      font-weight: bold;
      margin-bottom: 15px;
    }
    .login-card img {
      width: 50px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <img src="https://cdn-icons-png.flaticon.com/512/616/616602.png" alt="tooth">
    <h3>Oral Sight AI 360</h3>
    <p class="text-muted">Patient Portal Login</p>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
      <div class="mb-3 text-start">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3 text-start">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <p class="text-muted small">
        Your password is your date of birth (YYYY-MM-DD) + last 3 digits of your phone number
      </p>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p class="mt-3">Don’t have an account? <a href="register.php">Register</a></p>
  </div>
</body>
</html>
