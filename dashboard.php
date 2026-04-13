<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$enrolled   = dbSelectOne("SELECT COUNT(*) AS cnt FROM enrollment e JOIN semester s ON e.semester_id=s.semester_id WHERE s.is_active='Y'");
$sections   = dbSelectOne("SELECT COUNT(*) AS cnt FROM section s JOIN semester sem ON s.semester_id=sem.semester_id WHERE sem.is_active='Y' AND s.is_active='Y'");
$paysum     = dbSelectOne("SELECT IFNULL(SUM(total_paid),0) AS paid, IFNULL(SUM(total_amount_due-scholarship_deduction-total_paid),0) AS bal FROM tuition_assessment ta JOIN enrollment e ON ta.enrollment_id=e.enrollment_id JOIN semester s ON e.semester_id=s.semester_id WHERE s.is_active='Y'");
$bycollege  = dbSelect("SELECT col.college_code, col.college_name, COUNT(DISTINCT e.student_id) AS total FROM enrollment e JOIN student st ON e.student_id=st.student_id JOIN college col ON st.college_id=col.college_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y' GROUP BY col.college_code, col.college_name ORDER BY col.college_code");
$recent     = dbSelect("SELECT s.student_no, CONCAT(s.lastname,', ',s.firstname) AS full_name, e.enrollment_date, (ta.total_amount_due-ta.scholarship_deduction-ta.total_paid) AS balance_due FROM enrollment e JOIN student s ON e.student_id=s.student_id JOIN tuition_assessment ta ON e.enrollment_id=ta.enrollment_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY e.enrollment_date DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Dashboard</h1><div class="hdiv"></div><p>Admin overview</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">
    <div class="stats">
      <div class="stat blue"><div class="stat-label">Enrolled students</div><div class="stat-val"><?= $enrolled['cnt'] ?></div><div class="stat-sub">This semester</div></div>
      <div class="stat amber"><div class="stat-label">Active sections</div><div class="stat-val"><?= $sections['cnt'] ?></div><div class="stat-sub">This semester</div></div>
      <div class="stat green"><div class="stat-label">Total collected</div><div class="stat-val"><?= peso($paysum['paid']) ?></div><div class="stat-sub">Tuition payments</div></div>
      <div class="stat red"><div class="stat-label">Outstanding</div><div class="stat-val"><?= peso($paysum['bal']) ?></div><div class="stat-sub">Balance due</div></div>
    </div>
    <div class="two-col">
      <div class="card">
        <div class="card-head"><span class="card-title">Enrollment by college</span></div>
        <table class="tbl">
          <thead><tr><th>College</th><th>Students</th></tr></thead>
          <tbody>
            <?php foreach ($bycollege as $r): ?>
            <tr>
              <td><span class="tag tag-navy"><?= htmlspecialchars($r['college_code']) ?></span> <?= htmlspecialchars($r['college_name']) ?></td>
              <td class="mono"><?= $r['total'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bycollege)): ?><tr class="empty-row"><td colspan="2">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card">
        <div class="card-head"><span class="card-title">Recent enrollments</span></div>
        <table class="tbl">
          <thead><tr><th>Student</th><th>Date</th><th>Balance</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($r['student_no']) ?> <span style="color:var(--muted)"><?= htmlspecialchars($r['full_name']) ?></span></td>
              <td class="mono"><?= fmtDate($r['enrollment_date']) ?></td>
              <td class="mono" style="color:<?= $r['balance_due']>0?'var(--red)':'var(--subtle)' ?>"><?= peso($r['balance_due']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?><tr class="empty-row"><td colspan="3">No enrollments yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
</body>
</html>
