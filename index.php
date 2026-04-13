<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['role'] === 'admin'
        ? '/enrollease/admin/dashboard.php'
        : '/enrollease/student/dashboard.php'));
    exit;
}

$error = '';

if (isPost()) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $user = dbSelectOne(
            "SELECT u.*, s.lastname, s.firstname, s.student_no
             FROM users u
             LEFT JOIN student s ON u.student_id = s.student_id
             WHERE u.username = ?",
            [$username]
        );

        if (!$user) {
            $error = 'Invalid username or password.';
        } elseif ($user['is_active'] !== 'Y') {
            $error = 'Your account has been deactivated. Please contact the registrar.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid username or password.';
        } else {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['student_no'] = $user['student_no'];
            $_SESSION['full_name']  = $user['role'] === 'student'
                ? $user['lastname'] . ', ' . $user['firstname']
                : 'Administrator';

            header('Location: ' . ($user['role'] === 'admin'
                ? '/enrollease/admin/dashboard.php'
                : '/enrollease/student/dashboard.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-icon">E</div>
      <div class="login-name">EnrollEase</div>
      <div class="login-sub">University Enrollment &amp; Payment System</div>
    </div>
    <div class="login-card">
      <h2>Sign in to your account</h2>
      <p>Use your student number and the password provided by the registrar</p>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username"
                 placeholder="Your student number (e.g. 2024-0001)"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 required autofocus>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password"
                 placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary"
                style="width:100%;margin-top:8px">Sign in</button>
      </form>
    </div>
    <div style="text-align:center;margin-top:14px;font-size:11px;color:var(--subtle)">
      Forgot your password? Contact the registrar's office.
    </div>
  </div>
</div>
</body>
</html>
