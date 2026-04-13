<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = ''; $msgType = 'success';

if (isPost()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'post_payment') {
        $receipt = 'OR-' . date('YmdHis') . rand(10,99);
        $r = dbExecute(
            "INSERT INTO payment (assessment_id,payment_date,amount,payment_mode,receipt_number) VALUES (?,CURDATE(),?,?,?)",
            [post('assessment_id'), post('amount'), post('payment_mode'), $receipt]
        );
        if ($r['success']) {
            dbExecute(
                "UPDATE tuition_assessment SET total_paid=(SELECT IFNULL(SUM(amount),0) FROM payment WHERE assessment_id=?) WHERE assessment_id=?",
                [post('assessment_id'), post('assessment_id')]
            );
            $msg = "Payment posted. Receipt No: $receipt";
        } else {
            $msg = 'Error: ' . $r['error']; $msgType = 'error';
        }
    }

    if ($action === 'delete_payment') {
        $pid = post('payment_id');
        $row = dbSelectOne("SELECT assessment_id FROM payment WHERE payment_id=?", [$pid]);
        dbExecute("DELETE FROM payment WHERE payment_id=?", [$pid]);
        if ($row) {
            dbExecute(
                "UPDATE tuition_assessment SET total_paid=(SELECT IFNULL(SUM(amount),0) FROM payment WHERE assessment_id=?) WHERE assessment_id=?",
                [$row['assessment_id'], $row['assessment_id']]
            );
        }
        $msg = 'Payment deleted.';
    }
}

$assessments = dbSelect("SELECT ta.assessment_id, s.student_no, CONCAT(s.lastname,', ',s.firstname) AS full_name, ta.total_amount_due, ta.scholarship_deduction, ta.total_paid, (ta.total_amount_due-ta.scholarship_deduction-ta.total_paid) AS balance_due, ta.payment_scheme FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN student s ON e.student_id=s.student_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY s.lastname");
$payments    = dbSelect("SELECT p.payment_id, p.receipt_number, s.student_no, CONCAT(s.lastname,', ',s.firstname) AS student_name, p.payment_date, p.amount, p.payment_mode FROM payment p JOIN tuition_assessment ta ON p.assessment_id=ta.assessment_id JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN student s ON e.student_id=s.student_id ORDER BY p.payment_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Payments</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Payments</h1><div class="hdiv"></div><p>Post and manage payment transactions</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
      <div class="card-head"><span class="card-title">Tuition assessments</span></div>
      <table class="tbl">
        <thead><tr><th>Student No.</th><th>Name</th><th>Total Due</th><th>Scholarship</th><th>Paid</th><th>Balance</th><th>Scheme</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($assessments as $a): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($a['student_no']) ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($a['full_name']) ?></td>
            <td class="mono"><?= peso($a['total_amount_due']) ?></td>
            <td class="mono" style="color:var(--green)"><?= peso($a['scholarship_deduction']) ?></td>
            <td class="mono"><?= peso($a['total_paid']) ?></td>
            <td class="mono" style="color:<?= $a['balance_due']>0?'var(--red)':'var(--subtle)' ?>;font-weight:<?= $a['balance_due']>0?600:400 ?>"><?= peso($a['balance_due']) ?></td>
            <td><span class="tag tag-gray"><?= htmlspecialchars($a['payment_scheme']) ?></span></td>
            <td><button class="btn btn-sm btn-pay" onclick="openPayModal(<?= $a['assessment_id'] ?>,'<?= htmlspecialchars($a['full_name']) ?>','<?= peso($a['balance_due']) ?>')">+ Pay</button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($assessments)): ?><tr class="empty-row"><td colspan="8">No assessments</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-head">
        <span class="card-title">Payment transactions (<?= count($payments) ?>)</span>
        <input class="search" placeholder="Search…" oninput="filterTable('pay-tbl',this.value)">
      </div>
      <table class="tbl" id="pay-tbl">
        <thead><tr><th>Receipt No.</th><th>Student No.</th><th>Name</th><th>Date</th><th>Amount</th><th>Mode</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($p['receipt_number']) ?></td>
            <td class="mono"><?= htmlspecialchars($p['student_no']) ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($p['student_name']) ?></td>
            <td class="mono"><?= fmtDate($p['payment_date']) ?></td>
            <td class="mono" style="color:var(--green);font-weight:600"><?= peso($p['amount']) ?></td>
            <td><span class="tag tag-blue"><?= htmlspecialchars($p['payment_mode']) ?></span></td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this payment?')">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                <button type="submit" class="btn btn-sm btn-del">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($payments)): ?><tr class="empty-row"><td colspan="7">No payments</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<div class="modal-overlay" id="modal-pay">
  <div class="modal">
    <h3>Post Payment</h3>
    <p id="pay-sub">Record a payment for this student</p>
    <form method="POST">
      <input type="hidden" name="action" value="post_payment">
      <input type="hidden" name="assessment_id" id="pay-aid">
      <div class="field"><label>Amount (&#8369;)</label><input type="number" name="amount" step="0.01" placeholder="0.00" required></div>
      <div class="field"><label>Payment Mode</label>
        <select name="payment_mode">
          <option value="Cash">Cash</option>
          <option value="GCash">GCash</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="Maya">Maya</option>
        </select>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel" onclick="closeModal('modal-pay')">Cancel</button>
        <button type="submit" class="btn btn-primary">Post Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openPayModal(aid,name,bal){
  document.getElementById('pay-aid').value = aid;
  document.getElementById('pay-sub').textContent = name + ' — Balance: ' + bal;
  openModal('modal-pay');
}
function filterTable(id,q){
  document.querySelectorAll('#'+id+' tbody tr').forEach(r=>{
    r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none';
  });
}
document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click',e=>{ if(e.target===m) m.classList.remove('open'); });
});
</script>
</body>
</html>
