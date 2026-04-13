<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$assessments = dbSelect("SELECT ta.assessment_id, s.student_no, CONCAT(s.lastname,', ',s.firstname) AS full_name, col.college_code, c.course_code, ta.total_amount_due, ta.scholarship_deduction, ta.total_paid, (ta.total_amount_due-ta.scholarship_deduction-ta.total_paid) AS balance_due, ta.payment_scheme FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN student s ON e.student_id=s.student_id JOIN course c ON s.course_id=c.course_id JOIN college col ON s.college_id=col.college_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY s.lastname");

$totals = dbSelectOne("SELECT SUM(total_amount_due) AS total_due, SUM(scholarship_deduction) AS total_sch, SUM(total_paid) AS total_paid, SUM(total_amount_due-scholarship_deduction-total_paid) AS total_bal FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Assessments</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Tuition Assessments</h1><div class="hdiv"></div><p>Fee assessment overview</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">

    <?php if ($totals): ?>
    <div class="stats">
      <div class="stat blue"><div class="stat-label">Total assessed</div><div class="stat-val"><?= peso($totals['total_due']) ?></div><div class="stat-sub">Gross tuition</div></div>
      <div class="stat green"><div class="stat-label">Scholarships</div><div class="stat-val"><?= peso($totals['total_sch']) ?></div><div class="stat-sub">Total deductions</div></div>
      <div class="stat amber"><div class="stat-label">Collected</div><div class="stat-val"><?= peso($totals['total_paid']) ?></div><div class="stat-sub">Total payments</div></div>
      <div class="stat red"><div class="stat-label">Outstanding</div><div class="stat-val"><?= peso($totals['total_bal']) ?></div><div class="stat-sub">Remaining balance</div></div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <span class="card-title">All assessments</span>
        <input class="search" placeholder="Search…" oninput="filterTable('ass-tbl',this.value)">
      </div>
      <table class="tbl" id="ass-tbl">
        <thead><tr><th>Student No.</th><th>Name</th><th>College</th><th>Total Due</th><th>Scholarship</th><th>Paid</th><th>Balance</th><th>Scheme</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($assessments as $a): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($a['student_no']) ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($a['full_name']) ?></td>
            <td><span class="tag tag-navy"><?= htmlspecialchars($a['college_code']) ?></span></td>
            <td class="mono"><?= peso($a['total_amount_due']) ?></td>
            <td class="mono" style="color:var(--green)"><?= peso($a['scholarship_deduction']) ?></td>
            <td class="mono"><?= peso($a['total_paid']) ?></td>
            <td class="mono" style="color:<?= $a['balance_due']>0?'var(--red)':'var(--subtle)' ?>;font-weight:<?= $a['balance_due']>0?600:400 ?>"><?= peso($a['balance_due']) ?></td>
            <td><span class="tag tag-gray"><?= htmlspecialchars($a['payment_scheme']) ?></span></td>
            <td><a href="/enrollease/admin/payments.php" class="btn btn-sm btn-pay">+ Pay</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($assessments)): ?><tr class="empty-row"><td colspan="9">No assessments</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>
<script>
function filterTable(id,q){
  document.querySelectorAll('#'+id+' tbody tr').forEach(r=>{
    r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none';
  });
}
</script>
</body>
</html>
