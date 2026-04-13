<?php
// student/profile.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStudent();
$sid = getCurrentUser()['student_id'];
$p = dbSelectOne("SELECT s.*, c.course_code, c.course_name, col.college_name, col.college_code FROM student s JOIN course c ON s.course_id=c.course_id JOIN college col ON s.college_id=col.college_id WHERE s.student_id=?", [$sid]);
$fields = [['Student No.',$p['student_no']],['Last Name',$p['lastname']],['First Name',$p['firstname']],['Middle Name',$p['middlename']??'—'],['Email',$p['email']],['Contact No.',$p['contact_no']??'—'],['College',$p['college_name']],['Course',$p['course_name']],['Year Level','Year '.$p['year_level']],['Status',$p['status']]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EnrollEase — My Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/enrollease/assets/css/style.css">
<style>
.profile-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.pfield{padding:12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border)}
.pfield-label{font-size:10px;font-weight:600;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.pfield-val{font-size:13px;font-weight:500}
</style>
</head>
<body>
<div class="layout">
<?php include '../includes/student_nav.php'; ?>
<div class="main">
  <header class="header">
    <div class="header-left"><h1>My Profile</h1><div class="hdiv"></div><p>Your student information</p></div>
    <span class="sem-pill">1st Semester 2024–2025</span>
  </header>
  <div class="content">
    <div class="card">
      <div class="card-head"><span class="card-title">Student Information</span></div>
      <div class="card-body">
        <div class="profile-grid">
          <?php foreach ($fields as [$label,$val]): ?>
          <div class="pfield">
            <div class="pfield-label"><?= $label ?></div>
            <div class="pfield-val"><?= htmlspecialchars($val) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</body>
</html>
