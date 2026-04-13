<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();

$sid = getCurrentUser()['student_id'];
$msg = ''; $msgType = 'success';

// DROP subject
if (isPost() && ($_POST['action'] ?? '') === 'drop') {
    $esid = (int)$_POST['enroll_subject_id'];
    $es   = dbSelectOne("SELECT section_id, enrollment_id FROM enrolled_subject WHERE enroll_subject_id=?", [$esid]);
    if ($es) {
        dbExecute("DELETE FROM enrolled_subject WHERE enroll_subject_id=?", [$esid]);
        dbExecute("UPDATE section SET current_count=GREATEST(current_count-1,0) WHERE section_id=?", [$es['section_id']]);
        // Recalc totals
        $eid = $es['enrollment_id'];
        dbExecute("UPDATE enrollment SET total_units=(SELECT IFNULL(SUM(sub.units),0) FROM enrolled_subject es2 JOIN section sec ON es2.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id WHERE es2.enrollment_id=?) WHERE enrollment_id=?", [$eid,$eid]);
        dbExecute("UPDATE tuition_assessment SET total_amount_due=(SELECT IFNULL(SUM(sub.units),0)*1500 FROM enrolled_subject es2 JOIN section sec ON es2.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id WHERE es2.enrollment_id=?) WHERE enrollment_id=?", [$eid,$eid]);
        $msg = 'Subject dropped successfully.';
    }
}

$subjects = dbSelect("SELECT es.enroll_subject_id, sub.subject_code, sub.subject_name, sub.units, sec.section_code, sec.faculty_name, sec.schedule, sec.room FROM enrolled_subject es JOIN enrollment e ON es.enrollment_id=e.enrollment_id JOIN section sec ON es.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y' ORDER BY sub.subject_code", [$sid]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — My Subjects</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>My Subjects</h1><div class="hdiv"></div><p>Your enlisted subjects this semester</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="card">
      <div class="card-head">
        <span class="card-title">Enlisted subjects (<?= count($subjects) ?>)</span>
        <a href="/enrollease/student/enroll.php" class="btn btn-primary btn-sm">+ Add Subject</a>
      </div>
      <table class="tbl">
        <thead><tr><th>Code</th><th>Subject</th><th>Units</th><th>Section</th><th>Faculty</th><th>Schedule</th><th>Room</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($subjects as $s): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($s['subject_code']) ?></td>
            <td><?= htmlspecialchars($s['subject_name']) ?></td>
            <td class="mono"><?= $s['units'] ?></td>
            <td class="mono"><?= htmlspecialchars($s['section_code']) ?></td>
            <td><?= htmlspecialchars($s['faculty_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['schedule'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['room'] ?? '—') ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Drop <?= htmlspecialchars($s['subject_name']) ?>?')">
                <input type="hidden" name="action" value="drop">
                <input type="hidden" name="enroll_subject_id" value="<?= $s['enroll_subject_id'] ?>">
                <button type="submit" class="btn btn-sm btn-drop">Drop</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($subjects)): ?>
          <tr class="empty-row"><td colspan="8">No subjects enlisted. <a href="/enrollease/student/enroll.php">Enroll now</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
</body>
</html>
