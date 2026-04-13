<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();

$sid = getCurrentUser()['student_id'];
$msg = ''; $msgType = 'success';

if (isPost() && ($_POST['action'] ?? '') === 'pay') {
    $amount = (float)$_POST['amount'];
    $mode   = post('payment_mode');
    $ta = dbSelectOne(
        "SELECT ta.assessment_id, (ta.total_amount_due-ta.scholarship_deduction-ta.total_paid) AS balance_due FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y'",
        [$sid]
    );
    if (!$ta) {
        $msg = 'No assessment found. Please enroll first.'; $msgType = 'error';
    } elseif ($amount <= 0) {
        $msg = 'Please enter a valid amount.'; $msgType = 'error';
    } elseif ($amount > $ta['balance_due']) {
        $msg = 'Amount exceeds balance due of ' . peso($ta['balance_due']) . '.'; $msgType = 'error';
    } else {
        $receipt = 'OR-' . date('YmdHis') . rand(10,99);
        $r = dbExecute(
            "INSERT INTO payment (assessment_id,payment_date,amount,payment_mode,receipt_number) VALUES (?,CURDATE(),?,?,?)",
            [$ta['assessment_id'], $amount, $mode, $receipt]
        );
        if ($r['success']) {
            dbExecute(
                "UPDATE tuition_assessment SET total_paid=(SELECT IFNULL(SUM(amount),0) FROM payment WHERE assessment_id=?) WHERE assessment_id=?",
                [$ta['assessment_id'], $ta['assessment_id']]
            );
            $msg = "Payment successful! Receipt No: $receipt";
        } else {
            $msg = 'Error: ' . $r['error']; $msgType = 'error';
        }
    }
}

$assessment = dbSelectOne(
    "SELECT ta.total_amount_due, ta.scholarship_deduction, ta.total_paid, (ta.total_amount_due-ta.scholarship_deduction-ta.total_paid) AS balance_due, ta.payment_scheme FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y'",
    [$sid]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — My Balance</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>My Balance</h1><div class="hdiv"></div><p>Tuition fee and payment</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($assessment): ?>
    <div class="pay-grid">
      <div class="pay-box"><div class="pay-box-label">Total due</div><div class="pay-box-val"><?= peso($assessment['total_amount_due']) ?></div></div>
      <div class="pay-box"><div class="pay-box-label">Scholarship</div><div class="pay-box-val" style="color:var(--green)"><?= peso($assessment['scholarship_deduction']) ?></div></div>
      <div class="pay-box"><div class="pay-box-label">Total paid</div><div class="pay-box-val" style="color:var(--blue)"><?= peso($assessment['total_paid']) ?></div></div>
      <div class="pay-box"><div class="pay-box-label">Balance due</div><div class="pay-box-val" style="color:var(--red)"><?= peso($assessment['balance_due']) ?></div></div>
    </div>

    <?php if ($assessment['balance_due'] > 0): ?>
    <div class="card">
      <div class="card-head"><span class="card-title">Make a payment</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="pay">
          <div style="display:grid;grid-template-columns:1fr 1fr 160px;gap:12px;align-items:end">
            <div class="field" style="margin:0">
              <label>Amount (&#8369;)</label>
              <input type="number" name="amount" step="0.01" placeholder="0.00" max="<?= $assessment['balance_due'] ?>" required>
            </div>
            <div class="field" style="margin:0">
              <label>Payment Mode</label>
              <select name="payment_mode">
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Maya">Maya</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Submit Payment</button>
          </div>
          <p style="font-size:11px;color:var(--subtle);margin-top:10px">Receipt number is automatically generated upon successful payment.</p>
        </form>
      </div>
    </div>
    <?php else: ?>
      <div class="alert alert-success">Your tuition balance is fully settled. No payment due.</div>
    <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-info">No assessment found. Please complete your enrollment first.</div>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
