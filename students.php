<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = ''; $msgType = 'success';
$generatedPassword = '';
$generatedStudentNo = '';

// ── AUTO-GENERATE next student number ─────────────────────
function generateStudentNo() {
    $year = date('Y');
    // Find the highest sequence number used this year
    $row = dbSelectOne(
        "SELECT student_no FROM student
         WHERE student_no LIKE ? ORDER BY student_id DESC LIMIT 1",
        [$year . '-%']
    );
    if ($row) {
        $parts = explode('-', $row['student_no']);
        $next  = (int)end($parts) + 1;
    } else {
        $next = 1;
    }
    return $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// ── RANDOM PASSWORD ────────────────────────────────────────
function generatePassword($length = 10) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $pass  = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

// ── HANDLE FORM SUBMISSIONS ────────────────────────────────
if (isPost()) {
    $action = $_POST['action'] ?? '';

    // ── CREATE student + user account ─────────────────────
    if ($action === 'create') {
        $student_no = post('student_no');
        $password   = $_POST['raw_password'] ?? generatePassword();

        // Verify student_no not already taken
        $dupNo = dbSelectOne("SELECT student_id FROM student WHERE student_no=?", [$student_no]);
        if ($dupNo) {
            $msg = 'Student number ' . htmlspecialchars($student_no) . ' is already in use.';
            $msgType = 'error';
        } else {
            // Insert student
            $r = dbExecute(
                "INSERT INTO student (student_no,lastname,firstname,middlename,email,contact_no,course_id,college_id,year_level,status)
                 VALUES (?,?,?,?,?,?,?,?,?,'Active')",
                [
                    $student_no,
                    post('lastname'),
                    post('firstname'),
                    post('middlename') ?: null,
                    post('email'),
                    post('contact_no') ?: null,
                    post('course_id'),
                    post('college_id'),
                    post('year_level') ?: 1
                ]
            );

            if (!$r['success']) {
                $msg = 'Error creating student: ' . $r['error'];
                $msgType = 'error';
            } else {
                $newStudentId = $r['lastId'];
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $r2 = dbExecute(
                    "INSERT INTO users (username,password_hash,role,student_id) VALUES (?,?,'student',?)",
                    [$student_no, $hash, $newStudentId]
                );

                if (!$r2['success']) {
                    dbExecute("DELETE FROM student WHERE student_id=?", [$newStudentId]);
                    $msg = 'Error creating user account: ' . $r2['error'];
                    $msgType = 'error';
                } else {
                    $msg = "Student and account created. Username: <strong>{$student_no}</strong> — Temporary password: <strong>{$password}</strong> — Give this to the student.";
                    $msgType = 'success';
                }
            }
        }
    }

    // ── UPDATE student ─────────────────────────────────────
    if ($action === 'update') {
        $r = dbExecute(
            "UPDATE student SET lastname=?,firstname=?,middlename=?,email=?,
             contact_no=?,course_id=?,college_id=?,year_level=?,status=?
             WHERE student_id=?",
            [
                post('lastname'), post('firstname'),
                post('middlename') ?: null,
                post('email'),
                post('contact_no') ?: null,
                post('course_id'), post('college_id'),
                post('year_level'), post('status'),
                post('student_id')
            ]
        );
        $msg = $r['success'] ? 'Student updated successfully.' : 'Error: ' . $r['error'];
        $msgType = $r['success'] ? 'success' : 'error';
    }

    // ── SOFT DELETE ─────────────────────────────────────────
    if ($action === 'delete') {
        dbExecute("UPDATE student SET status='Inactive' WHERE student_id=?", [post('student_id')]);
        dbExecute("UPDATE users SET is_active='N' WHERE student_id=?", [post('student_id')]);
        $msg = 'Student deactivated.';
    }

    // ── RESET PASSWORD ──────────────────────────────────────
    if ($action === 'reset_pw') {
        $newPw = generatePassword();
        $hash  = password_hash($newPw, PASSWORD_BCRYPT);
        dbExecute("UPDATE users SET password_hash=? WHERE student_id=?", [$hash, post('student_id')]);
        $msg = 'Password reset. New temporary password: <strong>' . $newPw . '</strong> — Give this to the student.';
        $msgType = 'success';
    }
}

// ── READ ───────────────────────────────────────────────────
$students  = dbSelect(
    "SELECT s.student_id, s.student_no, s.lastname, s.firstname, s.middlename,
            s.email, s.contact_no, c.course_code, c.course_id,
            col.college_code, col.college_id, s.year_level, s.status,
            u.is_active AS account_active
     FROM student s
     JOIN course c   ON s.course_id  = c.course_id
     JOIN college col ON s.college_id = col.college_id
     LEFT JOIN users u ON u.student_id = s.student_id AND u.role='student'
     ORDER BY s.lastname, s.firstname"
);
$colleges  = dbSelect("SELECT * FROM college ORDER BY college_code");
$courses   = dbSelect(
    "SELECT c.course_id, c.course_code, c.course_name, col.college_id
     FROM course c
     JOIN department d ON c.dept_id=d.dept_id
     JOIN college col  ON d.college_id=col.college_id
     ORDER BY c.course_code"
);

// Pre-generate next student number for the modal
$nextStudentNo = generateStudentNo();
$suggestedPw   = generatePassword();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — Students</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
<style>
.pw-box {
  background: var(--amber-lt); border: 1px solid #fde68a;
  border-radius: 6px; padding: 10px 12px; font-size: 12px;
  color: var(--amber); margin-top: 6px;
}
.pw-box strong { font-family: 'JetBrains Mono', monospace; font-size: 14px; }
</style>
</head>
<body>
<div class="layout">
<?php include '../includes/admin_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left">
      <h1>Students</h1><div class="hdiv"></div>
      <p>Student registry — admin creates and manages all accounts</p>
    </div>
    <span class="sem-pill">&#9679; Active Semester</span>
  </header>
  <div class="content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= $msg /* intentionally unescaped for <strong> tags */ ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <span class="card-title">All students (<?= count($students) ?>)</span>
        <div class="card-actions">
          <input class="search" type="text" placeholder="Search…"
                 oninput="filterTable('stu-tbl', this.value)">
          <button class="btn btn-primary btn-sm"
                  onclick="openAddModal()">+ Add Student</button>
        </div>
      </div>
      <table class="tbl" id="stu-tbl">
        <thead>
          <tr>
            <th>Student No.</th><th>Name</th><th>College</th>
            <th>Course</th><th>Year</th><th>Status</th><th>Account</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars($s['student_no']) ?></td>
            <td style="font-weight:500">
              <?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname']) ?>
            </td>
            <td><span class="tag tag-navy"><?= htmlspecialchars($s['college_code']) ?></span></td>
            <td class="mono"><?= htmlspecialchars($s['course_code']) ?></td>
            <td class="mono"><?= $s['year_level'] ?></td>
            <td>
              <span class="tag <?= $s['status']==='Active'?'tag-green':'tag-red' ?>">
                <?= $s['status'] ?>
              </span>
            </td>
            <td>
              <span class="tag <?= $s['account_active']==='Y'?'tag-blue':'tag-gray' ?>">
                <?= $s['account_active']==='Y' ? 'Active' : 'No account' ?>
              </span>
            </td>
            <td style="display:flex;gap:5px;flex-wrap:wrap">
              <button class="btn btn-sm btn-edit"
                      onclick='openEdit(<?= json_encode($s) ?>)'>Edit</button>
              <?php if ($s['status']==='Active'): ?>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Deactivate this student?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                <button type="submit" class="btn btn-sm btn-del">Deactivate</button>
              </form>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($s['student_no'])) ?>?')">
                <input type="hidden" name="action" value="reset_pw">
                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                <button type="submit" class="btn btn-sm btn-pay">Reset PW</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?>
            <tr class="empty-row"><td colspan="8">No students found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<!-- ── ADD MODAL ─────────────────────────────────── -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h3>Add New Student</h3>
    <p>An account will be created automatically with a randomized password.</p>
    <form method="POST">
      <input type="hidden" name="action" value="create">

      <!-- Student number — pre-filled, editable -->
      <div class="field">
        <label>Student Number <span style="color:var(--red)">*</span></label>
        <div style="display:flex;gap:8px">
          <input name="student_no" id="add-sno" required
                 value="<?= htmlspecialchars($nextStudentNo) ?>"
                 style="font-family:'JetBrains Mono',monospace">
          <button type="button" class="btn btn-sm"
                  style="background:var(--bg);border:1px solid var(--border);white-space:nowrap"
                  onclick="refreshStudentNo()">&#8635; Refresh</button>
        </div>
        <div style="font-size:11px;color:var(--subtle);margin-top:4px">
          Auto-generated based on current year and last used number. You can edit if needed.
        </div>
      </div>

      <div class="two-col">
        <div class="field">
          <label>Last Name <span style="color:var(--red)">*</span></label>
          <input name="lastname" placeholder="dela Cruz" required>
        </div>
        <div class="field">
          <label>First Name <span style="color:var(--red)">*</span></label>
          <input name="firstname" placeholder="Juan" required>
        </div>
      </div>

      <div class="field">
        <label>Middle Name <span style="color:var(--subtle);font-weight:400">(optional)</span></label>
        <input name="middlename" placeholder="Santos">
      </div>

      <div class="field">
        <label>Email <span style="color:var(--red)">*</span></label>
        <input type="email" name="email" placeholder="juan@example.com" required>
      </div>

      <div class="field">
        <label>Contact Number</label>
        <input name="contact_no" placeholder="09171234567">
      </div>

      <div class="two-col">
        <div class="field">
          <label>College <span style="color:var(--red)">*</span></label>
          <select name="college_id" id="add-college" required
                  onchange="filterCourses('add-college','add-course')">
            <option value="">Select college</option>
            <?php foreach ($colleges as $c): ?>
              <option value="<?= $c['college_id'] ?>">
                <?= htmlspecialchars($c['college_code'] . ' — ' . $c['college_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Course <span style="color:var(--red)">*</span></label>
          <select name="course_id" id="add-course" required>
            <option value="">Select college first</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= $c['course_id'] ?>"
                      data-college="<?= $c['college_id'] ?>"
                      style="display:none">
                <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Year Level <span style="color:var(--red)">*</span></label>
        <select name="year_level">
          <option value="1">1st Year</option>
          <option value="2">2nd Year</option>
          <option value="3">3rd Year</option>
          <option value="4">4th Year</option>
        </select>
      </div>

      <!-- Password — pre-filled random, shown to admin -->
      <div class="field">
        <label>Temporary Password</label>
        <div style="display:flex;gap:8px">
          <input name="raw_password" id="add-pw" value="<?= htmlspecialchars($suggestedPw) ?>"
                 style="font-family:'JetBrains Mono',monospace">
          <button type="button" class="btn btn-sm"
                  style="background:var(--bg);border:1px solid var(--border);white-space:nowrap"
                  onclick="generatePw()">&#8635; New</button>
        </div>
        <div class="pw-box" id="pw-preview">
          Give this password to the student: <strong id="pw-display"><?= htmlspecialchars($suggestedPw) ?></strong>
        </div>
      </div>

      <div class="modal-btns">
        <button type="button" class="btn btn-cancel"
                onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Student &amp; Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ────────────────────────────────── -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <h3>Edit Student</h3>
    <p>Update the student's information</p>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="student_id" id="edit-id">

      <div class="field">
        <label>Student Number</label>
        <input id="edit-sno" disabled
               style="background:var(--bg);color:var(--muted);font-family:'JetBrains Mono',monospace">
      </div>

      <div class="two-col">
        <div class="field">
          <label>Last Name <span style="color:var(--red)">*</span></label>
          <input name="lastname" id="edit-ln" required>
        </div>
        <div class="field">
          <label>First Name <span style="color:var(--red)">*</span></label>
          <input name="firstname" id="edit-fn" required>
        </div>
      </div>

      <div class="field">
        <label>Middle Name</label>
        <input name="middlename" id="edit-mn">
      </div>

      <div class="field">
        <label>Email <span style="color:var(--red)">*</span></label>
        <input type="email" name="email" id="edit-em" required>
      </div>

      <div class="field">
        <label>Contact Number</label>
        <input name="contact_no" id="edit-cn">
      </div>

      <div class="two-col">
        <div class="field">
          <label>College <span style="color:var(--red)">*</span></label>
          <select name="college_id" id="edit-col" required
                  onchange="filterCourses('edit-col','edit-crs')">
            <?php foreach ($colleges as $c): ?>
              <option value="<?= $c['college_id'] ?>">
                <?= htmlspecialchars($c['college_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Course <span style="color:var(--red)">*</span></label>
          <select name="course_id" id="edit-crs" required>
            <?php foreach ($courses as $c): ?>
              <option value="<?= $c['course_id'] ?>"
                      data-college="<?= $c['college_id'] ?>">
                <?= htmlspecialchars($c['course_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="two-col">
        <div class="field">
          <label>Year Level</label>
          <select name="year_level" id="edit-yr">
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
          </select>
        </div>
        <div class="field">
          <label>Status</label>
          <select name="status" id="edit-st">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div class="modal-btns">
        <button type="button" class="btn btn-cancel"
                onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Student</button>
      </div>
    </form>
  </div>
</div>

<script>
// All courses as JS array for filtering
const allCourses = <?= json_encode(array_map(fn($c) => [
    'id'        => (int)$c['course_id'],
    'code'      => $c['course_code'],
    'name'      => $c['course_name'],
    'college_id'=> (int)$c['college_id'],
], $courses)) ?>;

function filterCourses(collegeSelId, courseSelId) {
    const collegeSel = document.getElementById(collegeSelId);
    const courseSel  = document.getElementById(courseSelId);
    const selectedCollegeId = parseInt(collegeSel.value);

    // Remove all option children except the first placeholder (if any)
    const currentVal = courseSel.value;
    courseSel.innerHTML = '';

    let matched = false;
    allCourses.forEach(c => {
        if (!selectedCollegeId || c.college_id === selectedCollegeId) {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.code + ' — ' + c.name;
            opt.dataset.college = c.college_id;
            courseSel.appendChild(opt);
            if (!matched) {
                courseSel.value = c.id;
                matched = true;
            }
        }
    });

    // Restore previous selection if still valid
    if (currentVal) {
        const exists = [...courseSel.options].some(o => o.value === currentVal);
        if (exists) courseSel.value = currentVal;
    }
}

function openAddModal() {
    // Reset college/course selectors
    const colSel = document.getElementById('add-college');
    colSel.value = '';
    const crsEl  = document.getElementById('add-course');
    crsEl.innerHTML = '<option value="">Select college first</option>';
    [...document.querySelectorAll('#add-course option[data-college]')]
        .forEach(o => o.style.display = 'none');
    openModal('modal-add');
}

function openEdit(s) {
    document.getElementById('edit-id').value  = s.student_id;
    document.getElementById('edit-sno').value = s.student_no;
    document.getElementById('edit-ln').value  = s.lastname;
    document.getElementById('edit-fn').value  = s.firstname;
    document.getElementById('edit-mn').value  = s.middlename || '';
    document.getElementById('edit-em').value  = s.email;
    document.getElementById('edit-cn').value  = s.contact_no || '';
    document.getElementById('edit-yr').value  = s.year_level;
    document.getElementById('edit-st').value  = s.status;

    // Set college and filter courses
    document.getElementById('edit-col').value = s.college_id;
    filterCourses('edit-col', 'edit-crs');
    document.getElementById('edit-crs').value = s.course_id;

    openModal('modal-edit');
}

// ── PASSWORD GENERATOR ─────────────────────────────
const CHARS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
function generatePw() {
    let pw = '';
    for (let i = 0; i < 10; i++) {
        pw += CHARS[Math.floor(Math.random() * CHARS.length)];
    }
    document.getElementById('add-pw').value       = pw;
    document.getElementById('pw-display').textContent = pw;
}

// ── STUDENT NUMBER REFRESH ─────────────────────────
async function refreshStudentNo() {
    const res = await fetch('/enrollease/admin/ajax_next_student_no.php');
    const txt = await res.text();
    document.getElementById('add-sno').value = txt.trim();
}

function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function filterTable(id, q) {
    document.querySelectorAll('#' + id + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// Sync password preview as admin types
document.getElementById('add-pw')?.addEventListener('input', function() {
    document.getElementById('pw-display').textContent = this.value;
});
</script>
</body>
</html>
