<?php
// OLP Standalone Admin Auth — does NOT depend on uphsledu/app
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/dbconnect.php';
// OLP users now live in uphsledu_onlinepayment (independent from uphsledu_main)
// Prefer local onlinepayment PDO; fallback to legacy getDBConnection if needed
if (!function_exists('getDBConnection')) {
    $candidates = [
        __DIR__ . '/../app/config/database.php',
        __DIR__ . '/../../uphsledu/app/config/database.php',
        'C:/xampp/htdocs/uphsledu/app/config/database.php',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) { require_once $c; break; }
    }
}
function olp_getUsersPDO(){
    static $pdo = null;
    if ($pdo) return $pdo;
    // Try onlinepayment first (independent)
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=uphsledu_onlinepayment;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        // ensure users table exists (light check)
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            role ENUM('super_admin','admin','author','hr') DEFAULT 'hr',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $pdo;
    } catch (Throwable $e) {
        // fallback to legacy main DB if onlinepayment unavailable
        try { if (function_exists('getDBConnection')) return getDBConnection(); } catch (Throwable $e2) {}
        return null;
    }
}

function olp_isLoggedIn() {
    return isset($_SESSION['olp_user_id']) && isset($_SESSION['olp_user_role']);
}
function olp_isSuperAdmin() {
    return olp_isLoggedIn() && $_SESSION['olp_user_role'] === 'super_admin';
}
function olp_isAdmin() {
    return olp_isLoggedIn() && in_array($_SESSION['olp_user_role'] ?? '', ['admin','super_admin']);
}
function olp_getUserById($id) {
    try {
        $pdo = olp_getUsersPDO() ?: (function_exists('getDBConnection') ? getDBConnection() : null);
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    } catch (Throwable $e) { return null; }
}
function olp_getUserByUsername($username) {
    try {
        $pdo = olp_getUsersPDO() ?: (function_exists('getDBConnection') ? getDBConnection() : null);
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (Throwable $e) { return null; }
}
function olp_requireAdmin() {
    if (!olp_isLoggedIn() || !olp_isSuperAdmin()) {
        header('Location: /olp/admin/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/olp/admin/'));
        exit;
    }
}
function olp_redirect($url){ header("Location: $url"); exit; }
// Compat wrappers for legacy admin pages (students.php/monitoring.php copied from uphsledu/admin)
if (!function_exists('getUserById')) {
    function getUserById($id) { return olp_getUserById($id); }
}
if (!function_exists('getUserByUsername')) {
    function getUserByUsername($username) { return olp_getUserByUsername($username); }
}
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() { return olp_isLoggedIn(); }
}
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() { return olp_isSuperAdmin(); }
}
