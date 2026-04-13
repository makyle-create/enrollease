<?php
// student/settings.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();

$user = getCurrentUser();
$msg = ''; $msgType = 'success';

if (isPost()) {
    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    if (empty($old) || empty($new) || empty($conf)) {
        $msg = 'All fields are required.'; $msgType = 'error';
    } elseif ($new !== $conf) {
        $msg = 'New passwords do not match.'; $msgType = 'error';
    } elseif (strlen($new) < 6) {
        $msg = 'New password must be at least 6 characters.'; $msgType = 'error';
    } else {
        $row = dbSelectOne("SELECT password_hash FROM users WHERE user_id=?", [$user['user_id']]);
        if (!$row || !password_verify($old, $row['password_hash'])) {
            $msg = 'Current password is incorrect.'; $msgType = 'error';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            dbExecute("UPDATE users SET password_hash=? WHERE user_id=?", [$hash, $user['user_id']]);
            $msg = 'Password changed successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Settings</h1><div class="hdiv"></div><p>Change your password</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <div class="card" style="max-width:440px">
      <div class="card-head"><span class="card-title">Change Password</span></div>
      <div class="card-body">
        <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <form method="POST">
          <div class="field"><label>Current Password</label><input type="password" name="old_password" required placeholder="Enter current password"></div>
          <div class="field"><label>New Password</label><input type="password" name="new_password" required placeholder="Minimum 6 characters"></div>
          <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" required placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
</body>
</html>
