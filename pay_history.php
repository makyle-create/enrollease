<?php
// student/pay_history.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();
$sid = getCurrentUser()['student_id'];
$history = dbSelect("SELECT p.receipt_number, p.payment_date, p.amount, p.payment_mode FROM payment p JOIN tuition_assessment ta ON p.assessment_id=ta.assessment_id JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y' ORDER BY p.payment_date DESC", [$sid]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Payment History</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Payment History</h1><div class="hdiv"></div><p>Your payment transactions</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <div class="card">
      <div class="card-head"><span class="card-title">All payments (<?= count($history) ?>)</span></div>
      <table class="tbl">
        <thead><tr><th>Receipt No.</th><th>Date</th><th>Amount</th><th>Mode</th></tr></thead>
        <tbody>
          <?php foreach ($history as $p): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($p['receipt_number']) ?></td>
            <td class="mono"><?= fmtDate($p['payment_date']) ?></td>
            <td class="mono" style="color:var(--green);font-weight:600"><?= peso($p['amount']) ?></td>
            <td><span class="tag tag-blue"><?= htmlspecialchars($p['payment_mode']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($history)): ?><tr class="empty-row"><td colspan="4">No payment history</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
</body>
</html>
