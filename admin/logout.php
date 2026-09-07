<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
unset($_SESSION['olp_user_id'], $_SESSION['olp_user_role'], $_SESSION['olp_username']);
unset($_SESSION['user_id'], $_SESSION['user_role']);
session_destroy();
header('Location: ' . $payments_base . 'admin/login');
exit;
