<?php
session_start();
include("../connection/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM admins WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) { // for now plaintext; you can switch to password_hash
            $_SESSION['doctor_id'] = $user['id'];
            header("Location: doctor_dashboard.php");
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with this username!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Portal Login | Oral Sight AI 360</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1565c0, #42a5f5);
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
    <img src="https://cdn-icons-png.flaticon.com/512/3063/3063176.png" alt="doctor">
    <h3>Oral Sight AI 360</h3>
    <p class="text-muted">Doctor Portal Login</p>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
      <div class="mb-3 text-start">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3 text-start">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p class="mt-3">Don’t have an account? <a href="doctor_register.php">Register</a></p>
  </div>
</body>
</html>
