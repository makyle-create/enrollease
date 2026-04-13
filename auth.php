<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /enrollease/index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: /enrollease/student/dashboard.php');
        exit;
    }
}

function requireStudent() {
    requireLogin();
    if ($_SESSION['role'] !== 'student') {
        header('Location: /enrollease/admin/dashboard.php');
        exit;
    }
}

function getCurrentUser() {
    return [
        'user_id'    => $_SESSION['user_id']    ?? null,
        'username'   => $_SESSION['username']   ?? null,
        'role'       => $_SESSION['role']        ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'full_name'  => $_SESSION['full_name']  ?? null,
        'student_no' => $_SESSION['student_no'] ?? null,
    ];
}

function isPost() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function post($key, $default = '') {
    return htmlspecialchars(trim($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function peso($n) {
    return '₱' . number_format((float)$n, 2);
}

function fmtDate($d) {
    return $d ? date('M j, Y', strtotime($d)) : '—';
}
?>
