<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = ''; $msgType = 'success';

if (isPost()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $semId = dbSelectOne("SELECT semester_id FROM semester WHERE is_active='Y'")['semester_id'] ?? null;
        $r = dbExecute(
            "INSERT INTO section (section_code,subject_id,semester_id,faculty_name,schedule,room,max_capacity) VALUES (?,?,?,?,?,?,?)",
            [post('section_code'), post('subject_id'), $semId, post('faculty_name') ?: null, post('schedule') ?: null, post('room') ?: null, post('max_capacity') ?: 40]
        );
        $msg = $r['success'] ? 'Section created.' : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    if ($action === 'update') {
        $r = dbExecute(
            "UPDATE section SET faculty_name=?,schedule=?,room=?,max_capacity=?,is_active=? WHERE section_id=?",
            [post('faculty_name'), post('schedule'), post('room'), post('max_capacity'), post('is_active'), post('section_id')]
        );
        $msg = $r['success'] ? 'Section updated.' : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    if ($action === 'delete') {
        dbExecute("UPDATE section SET is_active='N' WHERE section_id=?", [post('section_id')]);
        $msg = 'Section deactivated.';
    }
}

$sections = dbSelect("SELECT sec.*, sub.subject_code, sub.subject_name FROM section sec JOIN subject sub ON sec.subject_id=sub.subject_id JOIN semester sem ON sec.semester_id=sem.semester_id WHERE sem.is_active='Y' ORDER BY sub.subject_code");
$subjects = dbSelect("SELECT * FROM subject WHERE is_active='Y' ORDER BY subject_code");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Sections</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>Sections</h1><div class="hdiv"></div><p>Class section management</p></div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="card">
      <div class="card-head">
        <span class="card-title">All sections (<?= count($sections) ?>)</span>
        <div class="card-actions">
          <input class="search" placeholder="Search…" oninput="filterTable('sec-tbl',this.value)">
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-add')">+ Add Section</button>
        </div>
      </div>
      <table class="tbl" id="sec-tbl">
        <thead><tr><th>Code</th><th>Subject</th><th>Faculty</th><th>Schedule</th><th>Room</th><th>Capacity</th><th>Active</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($sections as $s): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($s['section_code']) ?></td>
            <td><?= htmlspecialchars($s['subject_name']) ?> <span style="color:var(--muted);font-size:11px">(<?= htmlspecialchars($s['subject_code']) ?>)</span></td>
            <td><?= htmlspecialchars($s['faculty_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['schedule'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['room'] ?? '—') ?></td>
            <td class="mono"><?= $s['current_count'] ?>/<?= $s['max_capacity'] ?></td>
            <td><span class="tag <?= $s['is_active']==='Y'?'tag-green':'tag-red' ?>"><?= $s['is_active']==='Y'?'Active':'Inactive' ?></span></td>
            <td>
              <button class="btn btn-sm btn-edit" onclick='openEdit(<?= json_encode($s) ?>)'>Edit</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Deactivate section?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="section_id" value="<?= $s['section_id'] ?>">
                <button type="submit" class="btn btn-sm btn-del">Deactivate</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sections)): ?><tr class="empty-row"><td colspan="8">No sections found</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h3>Add Section</h3><p>Create a new class section for the active semester</p>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="field"><label>Section Code</label><input name="section_code" placeholder="e.g. CC101-C" required></div>
      <div class="field"><label>Subject</label>
        <select name="subject_id" required>
          <option value="">Select subject</option>
          <?php foreach ($subjects as $sub): ?>
            <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_code'].' — '.$sub['subject_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Faculty Name</label><input name="faculty_name" placeholder="Prof. Santos"></div>
      <div class="field"><label>Schedule</label><input name="schedule" placeholder="MWF 7-8AM"></div>
      <div class="field"><label>Room</label><input name="room" placeholder="Room 101"></div>
      <div class="field"><label>Max Capacity</label><input type="number" name="max_capacity" value="40"></div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Section</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <h3>Edit Section</h3><p>Update section details</p>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="section_id" id="edit-id">
      <div class="field"><label>Faculty Name</label><input name="faculty_name" id="edit-fac"></div>
      <div class="field"><label>Schedule</label><input name="schedule" id="edit-sch"></div>
      <div class="field"><label>Room</label><input name="room" id="edit-room"></div>
      <div class="field"><label>Max Capacity</label><input type="number" name="max_capacity" id="edit-cap"></div>
      <div class="field"><label>Status</label>
        <select name="is_active" id="edit-act">
          <option value="Y">Active</option><option value="N">Inactive</option>
        </select>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn btn-cancel" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Section</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openEdit(s){
  document.getElementById('edit-id').value   = s.section_id;
  document.getElementById('edit-fac').value  = s.faculty_name || '';
  document.getElementById('edit-sch').value  = s.schedule || '';
  document.getElementById('edit-room').value = s.room || '';
  document.getElementById('edit-cap').value  = s.max_capacity;
  document.getElementById('edit-act').value  = s.is_active;
  openModal('modal-edit');
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
