<?php
require_once 'includes/auth.php';
session_destroy();
header('Location: /enrollease/index.php');
exit;
