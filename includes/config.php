<?php
// OLP â€” Online Payment Portal â€” STANDALONE config
// All payment data uses LOCAL includes/dbconnect.php (mysqli $con â†’ UPHSedu_onlinepayment)
// Admin auth uses LOCAL includes/auth.php + app/config/database.php (PDO â†’ UPHSedu_main) â€” NOT UPHSedu folder
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Payments DB (LOCAL) ---
require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/campus_table_manager.php';

if (isset($con)) {
    @mysqli_set_charset($con, 'utf8mb4');
    ensureCampusTablesExist($con);
    ensureTmpStudentTablesExist($con);
}

// --- Base path for this standalone hub ---
// Detect environment: live server (pay.uphsl.edu.ph) uses root '/', local uses '/olp/'
if (!isset($GLOBALS['payments_base'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'pay.uphsl.edu.ph') !== false) {
        $GLOBALS['payments_base'] = '/';
    } else {
        $GLOBALS['payments_base'] = '/olp/';
    }
}
$payments_base = $GLOBALS['payments_base'];

// --- Admin auth â€” load LOCAL auth ---
require_once __DIR__ . '/auth.php';

function paymentsRequireAdmin() {
    olp_requireAdmin();
}

