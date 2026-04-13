<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$enrollments = dbSelect("SELECT e.enrollment_id, s.student_no, CONCAT(s.lastname,', ',s.firstname) AS full_name, col.college_code, c.course_code, e.enrollment_date, e.status, e.total_units FROM enrollment e JOIN student s ON e.student_id=s.student_id JOIN course c ON s.course_id=c.course_id JOIN college col ON s.college_id=col.college_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY s.lastname");

// View subjects for a specific enrollment
$viewEnrollmentId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewSubjects = [];
$viewName = '';
if ($viewEnrollmentId) {
    $viewSubjects = dbSelect("SELECT sub.subject_code, sub.subject_name, sub.units, sec.section_code, sec.faculty_name, sec.schedule, sec.room FROM enrolled_subject es JOIN section sec ON es.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id WHERE es.enrollment_id=?", [$viewEnrollmentId]);
    $enr = dbSelectOne("SELECT CONCAT(s.lastname,', ',s.firstname) AS full_name FROM enrollment e JOIN student s ON e.student_id=s.student_id WHERE e.enrollment_id=?", [$viewEnrollmentId]);
    $viewName = $enr['full_name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Enrollments</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Enrollments</h1><div class="hdiv"></div><p>Current semester enrollment records</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">

    <?php if ($viewEnrollmentId && !empty($viewSubjects)): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-head">
        <span class="card-title">Subjects — <?= htmlspecialchars($viewName) ?></span>
        <a href="/enrollease/admin/enrollments.php" class="btn btn-sm btn-cancel">Back to list</a>
      </div>
      <table class="tbl">
        <thead><tr><th>Code</th><th>Subject</th><th>Units</th><th>Section</th><th>Faculty</th><th>Schedule</th><th>Room</th></tr></thead>
        <tbody>
          <?php foreach ($viewSubjects as $s): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($s['subject_code']) ?></td>
            <td><?= htmlspecialchars($s['subject_name']) ?></td>
            <td class="mono"><?= $s['units'] ?></td>
            <td class="mono"><?= htmlspecialchars($s['section_code']) ?></td>
            <td><?= htmlspecialchars($s['faculty_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['schedule'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['room'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <span class="card-title">All enrollments (<?= count($enrollments) ?>)</span>
        <input class="search" placeholder="Search…" oninput="filterTable('enr-tbl',this.value)">
      </div>
      <table class="tbl" id="enr-tbl">
        <thead><tr><th>Student No.</th><th>Name</th><th>College</th><th>Course</th><th>Date</th><th>Units</th><th>Status</th><th>Subjects</th></tr></thead>
        <tbody>
          <?php foreach ($enrollments as $e): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($e['student_no']) ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($e['full_name']) ?></td>
            <td><span class="tag tag-navy"><?= htmlspecialchars($e['college_code']) ?></span></td>
            <td class="mono"><?= htmlspecialchars($e['course_code']) ?></td>
            <td class="mono"><?= fmtDate($e['enrollment_date']) ?></td>
            <td class="mono"><?= $e['total_units'] ?></td>
            <td><span class="tag tag-green"><?= htmlspecialchars($e['status']) ?></span></td>
            <td><a href="?view=<?= $e['enrollment_id'] ?>" class="btn btn-sm btn-edit">View</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($enrollments)): ?><tr class="empty-row"><td colspan="8">No enrollments yet</td></tr><?php endif; ?>
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
