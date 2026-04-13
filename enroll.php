<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();

$sid = getCurrentUser()['student_id'];
$msg = ''; $msgType = 'success';

function getOrCreateEnrollment($sid) {
    $existing = dbSelectOne(
        "SELECT enrollment_id FROM enrollment e JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y'",
        [$sid]
    );
    if ($existing) return $existing['enrollment_id'];

    $semId = dbSelectOne("SELECT semester_id FROM semester WHERE is_active='Y'")['semester_id'] ?? null;
    if (!$semId) return null;

    dbExecute("INSERT INTO enrollment (student_id,semester_id,status) VALUES (?,?,'Active')", [$sid, $semId]);
    $new = dbSelectOne(
        "SELECT enrollment_id FROM enrollment e JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y'",
        [$sid]
    );
    $eid = $new['enrollment_id'];
    dbExecute("INSERT INTO tuition_assessment (enrollment_id,total_amount_due,scholarship_deduction,total_paid,payment_scheme) VALUES (?,0,0,0,'Full')", [$eid]);
    return $eid;
}

function recalcTotals($eid) {
    dbExecute(
        "UPDATE enrollment SET total_units=(SELECT IFNULL(SUM(sub.units),0) FROM enrolled_subject es JOIN section sec ON es.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id WHERE es.enrollment_id=?) WHERE enrollment_id=?",
        [$eid, $eid]
    );
    dbExecute(
        "UPDATE tuition_assessment SET total_amount_due=(SELECT IFNULL(SUM(sub.units),0)*1500 FROM enrolled_subject es JOIN section sec ON es.section_id=sec.section_id JOIN subject sub ON sec.subject_id=sub.subject_id WHERE es.enrollment_id=?) WHERE enrollment_id=?",
        [$eid, $eid]
    );
}

if (isPost() && ($_POST['action'] ?? '') === 'enlist') {
    $secId = (int)$_POST['section_id'];
    $sec   = dbSelectOne("SELECT * FROM section WHERE section_id=?", [$secId]);

    if (!$sec) {
        $msg = 'Section not found.'; $msgType = 'error';
    } elseif ($sec['current_count'] >= $sec['max_capacity']) {
        $msg = 'This section is already full.'; $msgType = 'error';
    } else {
        $eid = getOrCreateEnrollment($sid);
        $dup = dbSelectOne(
            "SELECT es.enroll_subject_id FROM enrolled_subject es JOIN section sec ON es.section_id=sec.section_id WHERE es.enrollment_id=? AND sec.subject_id=?",
            [$eid, $sec['subject_id']]
        );
        if ($dup) {
            $msg = 'You are already enrolled in this subject.'; $msgType = 'error';
        } else {
            $r = dbExecute("INSERT INTO enrolled_subject (enrollment_id,section_id) VALUES (?,?)", [$eid, $secId]);
            if ($r['success']) {
                dbExecute("UPDATE section SET current_count=current_count+1 WHERE section_id=?", [$secId]);
                recalcTotals($eid);
                $msg = 'Subject enlisted successfully.';
            } else {
                $msg = 'Error: ' . $r['error']; $msgType = 'error';
            }
        }
    }
}

$sections   = dbSelect("SELECT sec.section_id, sec.section_code, sub.subject_code, sub.subject_name, sub.units, sec.faculty_name, sec.schedule, sec.room, sec.max_capacity, sec.current_count, (sec.max_capacity-sec.current_count) AS slots_available FROM section sec JOIN subject sub ON sec.subject_id=sub.subject_id JOIN semester sem ON sec.semester_id=sem.semester_id WHERE sem.is_active='Y' AND sec.is_active='Y' AND sec.current_count < sec.max_capacity ORDER BY sub.subject_code");
$enrollment = dbSelectOne("SELECT enrollment_id, total_units FROM enrollment e JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y'", [$sid]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Enroll</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Enroll / Add Subjects</h1><div class="hdiv"></div><p>Browse available sections and enlist</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($enrollment): ?>
      <div class="alert alert-info"><strong>Enrollment active.</strong> Total units enlisted: <strong><?= $enrollment['total_units'] ?></strong></div>
    <?php else: ?>
      <div class="alert alert-info">You are not yet enrolled. Click <strong>Enlist</strong> on any section to start your enrollment automatically.</div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <span class="card-title">Available sections (<?= count($sections) ?>)</span>
        <input class="search" placeholder="Search subject…" oninput="filterTable('sec-tbl',this.value)">
      </div>
      <table class="tbl" id="sec-tbl">
        <thead><tr><th>Section</th><th>Subject</th><th>Units</th><th>Faculty</th><th>Schedule</th><th>Room</th><th>Slots</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($sections as $s): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($s['section_code']) ?></td>
            <td><?= htmlspecialchars($s['subject_name']) ?> <span style="color:var(--muted);font-size:11px">(<?= htmlspecialchars($s['subject_code']) ?>)</span></td>
            <td class="mono"><?= $s['units'] ?></td>
            <td><?= htmlspecialchars($s['faculty_name'] ?? 'TBA') ?></td>
            <td><?= htmlspecialchars($s['schedule'] ?? 'TBA') ?></td>
            <td><?= htmlspecialchars($s['room'] ?? 'TBA') ?></td>
            <td><span class="tag <?= $s['slots_available']>5?'tag-green':($s['slots_available']>0?'tag-amber':'tag-red') ?>"><?= $s['slots_available'] ?> open</span></td>
            <td>
              <form method="POST" onsubmit="return confirm('Enlist in <?= htmlspecialchars($s['section_code']) ?>?')">
                <input type="hidden" name="action" value="enlist">
                <input type="hidden" name="section_id" value="<?= $s['section_id'] ?>">
                <button type="submit" class="btn btn-sm btn-enlist">Enlist</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sections)): ?><tr class="empty-row"><td colspan="8">No available sections</td></tr><?php endif; ?>
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
