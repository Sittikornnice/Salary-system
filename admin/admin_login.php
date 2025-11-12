<?php
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli('localhost', 'root', '', 'salary_system');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        $_SESSION['admin'] = $username;
        header('Location: company.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบผู้ดูแล</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400&display=swap" rel="stylesheet">
  <style>
    body {
      background: #eaf6fb;
      font-family: 'Prompt', sans-serif;
      font-weight: 300;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow-y: hidden;
      padding: 20px;
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: #fff;
      padding: 40px 32px;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(0, 176, 116, 0.08);
      border: none;
    }
    .login-title {
      text-align: center;
      color: #009688;
      font-weight: 400;
      font-size: 1.4rem;
      margin-bottom: 20px;
    }
    .form-label {
      font-weight: 300;
      color: #009688;
      letter-spacing: 0.5px;
      font-size: 1rem;
    }
    .form-control {
      border-radius: 10px;
      border: 1.5px solid #e0f2f1;
      font-weight: 300;
      background: #f7fafc;
      font-size: 1rem;
      margin-bottom: 12px;
    }
    .form-control:focus {
      border-color: #26a69a;
      box-shadow: 0 0 0 0.15rem rgba(0, 150, 136, 0.10);
      background: #e0f2f1;
    }
    .btn-info {
      background-color: #7be7c4;
      color: #00695c;
      border: none;
      font-weight: 400;
      font-size: 1.08rem;
      letter-spacing: 1px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .btn-info:hover {
      background-color: #4dd0a1;
      color: #004d40;
    }
    .alert-danger {
      font-size: 0.95rem;
      padding: 8px 12px;
      margin-bottom: 16px;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-title">เข้าสู่ระบบผู้ดูแล</div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">ชื่อผู้ใช้</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">รหัสผ่าน</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-info w-100">เข้าสู่ระบบ</button>
    </form>
  </div>
</body>
</html>
