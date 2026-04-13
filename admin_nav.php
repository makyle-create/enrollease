<?php
$current = basename($_SERVER['PHP_SELF']);
$user = getCurrentUser();
?>
<aside class="sidebar">
  <div class="sidebar-top">
    <div class="logo-wrap">
      <div class="logo-icon">E</div>
      <div><div class="logo-text">EnrollEase</div><div class="logo-sub">Admin Portal</div></div>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-group">Overview</div>
    <a class="nav-item <?= $current==='dashboard.php'?'active':'' ?>" href="/enrollease/admin/dashboard.php"><span class="nav-dot"></span>Dashboard</a>
    <div class="nav-group">Enrollment</div>
    <a class="nav-item <?= $current==='students.php'?'active':'' ?>"    href="/enrollease/admin/students.php"><span class="nav-dot"></span>Students</a>
    <a class="nav-item <?= $current==='sections.php'?'active':'' ?>"    href="/enrollease/admin/sections.php"><span class="nav-dot"></span>Sections</a>
    <a class="nav-item <?= $current==='enrollments.php'?'active':'' ?>" href="/enrollease/admin/enrollments.php"><span class="nav-dot"></span>Enrollments</a>
    <div class="nav-group">Payments</div>
    <a class="nav-item <?= $current==='assessments.php'?'active':'' ?>" href="/enrollease/admin/assessments.php"><span class="nav-dot"></span>Assessments</a>
    <a class="nav-item <?= $current==='payments.php'?'active':'' ?>"    href="/enrollease/admin/payments.php"><span class="nav-dot"></span>Payments</a>
    <a class="nav-item <?= $current==='scholarships.php'?'active':'' ?>" href="/enrollease/admin/scholarships.php"><span class="nav-dot"></span>Scholarships</a>
    <div class="nav-group">System</div>
    <a class="nav-item <?= $current==='users.php'?'active':'' ?>" href="/enrollease/admin/users.php"><span class="nav-dot"></span>Users</a>
  </nav>
  <div class="sidebar-foot">
    <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
    <a href="/enrollease/logout.php" class="logout-link">Sign out</a>
  </div>
</aside>
