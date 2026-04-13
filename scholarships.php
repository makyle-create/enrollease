<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = ''; $msgType = 'success';

if (isPost()) {
    $action = $_POST['action'] ?? '';

    // CREATE scholarship type
    if ($action === 'create_sch') {
        $r = dbExecute(
            "INSERT INTO scholarship (scholarship_name,scholarship_type,max_amount) VALUES (?,?,?)",
            [post('scholarship_name'), post('scholarship_type'), post('max_amount')]
        );
        $msg = $r['success'] ? 'Scholarship created.' : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    // UPDATE scholarship type
    if ($action === 'update_sch') {
        $r = dbExecute(
            "UPDATE scholarship SET scholarship_name=?,scholarship_type=?,max_amount=? WHERE scholarship_id=?",
            [post('scholarship_name'), post('scholarship_type'), post('max_amount'), post('scholarship_id')]
        );
        $msg = $r['success'] ? 'Scholarship updated.' : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    // DELETE scholarship type
    if ($action === 'delete_sch') {
        dbExecute("DELETE FROM scholarship WHERE scholarship_id=?", [post('scholarship_id')]);
        $msg = 'Scholarship deleted.';
    }

    // AWARD scholarship to student
    if ($action === 'award') {
        $semId = dbSelectOne("SELECT semester_id FROM semester WHERE is_active='Y'")['semester_id'] ?? null;
        $r = dbExecute(
            "INSERT INTO student_scholarship (student_id,scholarship_id,semester_id,amount_awarded) VALUES (?,?,?,?)",
            [post('student_id'), post('scholarship_id'), $semId, post('amount_awarded')]
        );
        if ($r['success']) {
            // Update scholarship deduction on assessment
            dbExecute(
                "UPDATE tuition_assessment SET scholarship_deduction=(SELECT IFNULL(SUM(ss.amount_awarded),0) FROM student_scholarship ss JOIN semester sem ON ss.semester_id=sem.semester_id WHERE ss.student_id=? AND sem.is_active='Y') WHERE enrollment_id=(SELECT enrollment_id FROM enrollment e JOIN semester sem ON e.semester_id=sem.semester_id WHERE e.student_id=? AND sem.is_active='Y')",
                [post('student_id'), post('student_id')]
            );
            $msg = 'Scholarship awarded successfully.';
        } else {
            $msg = 'Error: ' . $r['error']; $msgType = 'error';
        }
    }
}

$scholarships = dbSelect("SELECT * FROM scholarship ORDER BY scholarship_name");
$awarded      = dbSelect("SELECT ss.student_scholar_id, s.student_no, CONCAT(s.lastname,', ',s.firstname) AS student_name, sch.scholarship_name, sch.scholarship_type, ss.amount_awarded, ss.status FROM student_scholarship ss JOIN student s ON ss.student_id=s.student_id JOIN scholarship sch ON ss.scholarship_id=sch.scholarship_id JOIN semester sem ON ss.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY s.lastname");
$students     = dbSelect("SELECT student_id, student_no, CONCAT(lastname,', ',firstname) AS full_name FROM student WHERE status='Active' ORDER BY lastname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Scholarships</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Scholarships</h1><div class="hdiv"></div><p>Manage scholarship types and awards</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Scholarship types -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">Scholarship types</span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-sch')">+ Add</button>
        </div>
        <table class="tbl">
          <thead><tr><th>Name</th><th>Type</th><th>Max Amount</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($scholarships as $s): ?>
            <tr>
              <td style="font-weight:500"><?= htmlspecialchars($s['scholarship_name']) ?></td>
              <td><span class="tag tag-blue"><?= htmlspecialchars($s['scholarship_type']) ?></span></td>
              <td class="mono"><?= peso($s['max_amount']) ?></td>
              <td>
                <button class="btn btn-sm btn-edit" onclick='openEditSch(<?= json_encode($s) ?>)'>Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                  <input type="hidden" name="action" value="delete_sch">
                  <input type="hidden" name="scholarship_id" value="<?= $s['scholarship_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-del">Del</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($scholarships)): ?><tr class="empty-row"><td colspan="4">No scholarships</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Awarded scholarships -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">Student awards</span>
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-award')">+ Award</button>
        </div>
        <table class="tbl">
          <thead><tr><th>Student</th><th>Scholarship</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($awarded as $a): ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($a['student_no']) ?> <span style="color:var(--muted)"><?= htmlspecialchars($a['student_name']) ?></span></td>
              <td><?= htmlspecialchars($a['scholarship_name']) ?></td>
              <td class="mono" style="color:var(--amber);font-weight:600"><?= peso($a['amount_awarded']) ?></td>
              <td><span class="tag tag-green"><?= htmlspecialchars($a['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($awarded)): ?><tr class="empty-row"><td colspan="4">No awards yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<!-- ADD SCHOLARSHIP -->
<div class="modal-overlay" id="modal-sch">
  <div class="modal">
    <h3 id="sch-title">Add Scholarship</h3><p>Define a scholarship type</p>
    <form method="POST" id="sch-form">
      <input type="hidden" name="action" id="sch-action" value="create_sch">
      <input type="hidden" name="scholarship_id" id="sch-id">
      <div class="field"><label>Scholarship Name</label><input name="scholarship_name" id="sch-nm" placeholder="Academic Excellence" required></div>
      <div class="field"><label>Type</label>
        <select name="scholarship_type" id="sch-tp">
          <option value="Merit-based">Merit-based</option>
          <option value="Need-based">Need-based</option>
          <option value="Special">Special</option>
        </select>
      </div>
      <div class="field"><label>Max Amount (&#8369;)</label><input type="number" name="max_amount" id="sch-amt" placeholder="5000"></div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel" onclick="closeModal('modal-sch')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- AWARD MODAL -->
<div class="modal-overlay" id="modal-award">
  <div class="modal">
    <h3>Award Scholarship</h3><p>Grant a scholarship to a student this semester</p>
    <form method="POST">
      <input type="hidden" name="action" value="award">
      <div class="field"><label>Student</label>
        <select name="student_id" required>
          <option value="">Select student</option>
          <?php foreach ($students as $s): ?>
            <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['student_no'] . ' — ' . $s['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Scholarship</label>
        <select name="scholarship_id" required>
          <option value="">Select scholarship</option>
          <?php foreach ($scholarships as $s): ?>
            <option value="<?= $s['scholarship_id'] ?>"><?= htmlspecialchars($s['scholarship_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Amount Awarded (&#8369;)</label><input type="number" name="amount_awarded" placeholder="0" required></div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel" onclick="closeModal('modal-award')">Cancel</button>
        <button type="submit" class="btn btn-primary">Award</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openEditSch(s){
  document.getElementById('sch-title').textContent = 'Edit Scholarship';
  document.getElementById('sch-action').value = 'update_sch';
  document.getElementById('sch-id').value  = s.scholarship_id;
  document.getElementById('sch-nm').value  = s.scholarship_name;
  document.getElementById('sch-tp').value  = s.scholarship_type;
  document.getElementById('sch-amt').value = s.max_amount;
  openModal('modal-sch');
}
document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click',e=>{ if(e.target===m) m.classList.remove('open'); });
});
</script>
</body>
</html>
