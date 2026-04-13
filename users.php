<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = ''; $msgType = 'success';

function generatePassword($length = 10) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $pass  = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

if (isPost()) {
    $action = $_POST['action'] ?? '';

    // Create admin account only
    if ($action === 'create_admin') {
        $pw   = $_POST['password'] ?? generatePassword();
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        $r    = dbExecute(
            "INSERT INTO users (username,password_hash,role,student_id) VALUES (?,'admin',NULL)",
            [post('username'), $hash]
        );
        // fix: correct param count
        $r = dbExecute(
            "INSERT INTO users (username,password_hash,role,student_id) VALUES (?,?,'admin',NULL)",
            [post('username'), $hash]
        );
        $msg = $r['success']
            ? "Admin account created. Username: <strong>" . htmlspecialchars(post('username')) . "</strong>"
            : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    // Toggle active/inactive
    if ($action === 'toggle') {
        dbExecute(
            "UPDATE users SET is_active=IF(is_active='Y','N','Y') WHERE user_id=?",
            [post('user_id')]
        );
        $msg = 'Account status updated.';
    }

    // Reset password for any user
    if ($action === 'reset_pw') {
        $newPw = generatePassword();
        $hash  = password_hash($newPw, PASSWORD_BCRYPT);
        dbExecute("UPDATE users SET password_hash=? WHERE user_id=?", [$hash, post('user_id')]);
        $msg = 'Password reset. New temporary password: <strong>' . $newPw . '</strong>';
        $msgType = 'success';
    }
}

$users = dbSelect(
    "SELECT u.user_id, u.username, u.role, u.is_active, u.created_at,
            s.student_no, CONCAT(s.lastname,', ',s.firstname) AS full_name
     FROM users u
     LEFT JOIN student s ON u.student_id = s.student_id
     ORDER BY u.role ASC, u.username ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Users</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left">
      <h1>Users</h1><div class="hdiv"></div>
      <p>System accounts — student accounts are created via the Students page</p>
    </div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-bottom:16px">
      Student accounts are created automatically when you add a student via
      <a href="/enrollease/admin/students.php" style="color:var(--blue);font-weight:500">Students &rarr;</a>.
      This page manages admin accounts and lets you toggle/reset any account.
    </div>

    <div class="card">
      <div class="card-head">
        <span class="card-title">All accounts (<?= count($users) ?>)</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-add')">
          + Add Admin Account
        </button>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>Username</th><th>Role</th><th>Student</th>
            <th>Status</th><th>Created</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($u['username']) ?></td>
            <td>
              <span class="tag <?= $u['role']==='admin'?'tag-navy':'tag-blue' ?>">
                <?= $u['role'] ?>
              </span>
            </td>
            <td>
              <?php if ($u['full_name']): ?>
                <span class="mono" style="font-size:10px">
                  <?= htmlspecialchars($u['student_no']) ?>
                </span>
                <?= htmlspecialchars($u['full_name']) ?>
              <?php else: ?>
                <span style="color:var(--subtle)">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="tag <?= $u['is_active']==='Y'?'tag-green':'tag-red' ?>">
                <?= $u['is_active']==='Y'?'Active':'Inactive' ?>
              </span>
            </td>
            <td class="mono"><?= fmtDate($u['created_at']) ?></td>
            <td style="display:flex;gap:6px">
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-edit">
                  <?= $u['is_active']==='Y'?'Deactivate':'Activate' ?>
                </button>
              </form>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Reset this account password?')">
                <input type="hidden" name="action" value="reset_pw">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-pay">Reset PW</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr class="empty-row"><td colspan="6">No users found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<!-- ADD ADMIN MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h3>Add Admin Account</h3>
    <p>Create a new administrator login</p>
    <form method="POST">
      <input type="hidden" name="action" value="create_admin">
      <div class="field">
        <label>Username</label>
        <input name="username" placeholder="e.g. registrar" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="Set password" required>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel"
                onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Admin</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body>
</html>
