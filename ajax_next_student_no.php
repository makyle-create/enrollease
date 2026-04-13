<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$year = date('Y');
$row  = dbSelectOne(
    "SELECT student_no FROM student WHERE student_no LIKE ? ORDER BY student_id DESC LIMIT 1",
    [$year . '-%']
);

if ($row) {
    $parts = explode('-', $row['student_no']);
    $next  = (int)end($parts) + 1;
} else {
    $next = 1;
}

echo $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
