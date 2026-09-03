<?php
// OLP Standalone AJAX â€” Payment Monitoring (no dependency on UPHSedu)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/config/database.php';
if (!olp_isLoggedIn() || !olp_isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../includes/dbconnect.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$perPage = 50;

$whereConditions = [];
if ($search !== '') {
    $s = mysqli_real_escape_string($con, $search);
    $whereConditions[] = "(txnid LIKE '%{$s}%' OR refno LIKE '%{$s}%' OR message LIKE '%{$s}%')";
}
if ($statusFilter !== '') {
    $s = mysqli_real_escape_string($con, $statusFilter);
    $whereConditions[] = "status = '{$s}'";
}
if ($dateFrom !== '') {
    $s = mysqli_real_escape_string($con, $dateFrom);
    $whereConditions[] = "DATE(transdate) >= '{$s}'";
}
if ($dateTo !== '') {
    $s = mysqli_real_escape_string($con, $dateTo);
    $whereConditions[] = "DATE(transdate) <= '{$s}'";
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countQuery = "SELECT COUNT(DISTINCT txnid) as total FROM return_data $whereClause";
$countResult = mysqli_query($con, $countQuery);
$totalRecords = (int)(mysqli_fetch_assoc($countResult)['total'] ?? 0);
$totalPages = (int)ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

$query = "SELECT txnid, refno, status, message, MAX(transdate) as transdate, amount FROM return_data $whereClause GROUP BY txnid ORDER BY transdate DESC LIMIT $perPage OFFSET $offset";
$result = mysqli_query($con, $query);
$payments = [];
while ($row = mysqli_fetch_assoc($result)) $payments[] = $row;

header('Content-Type: application/json');
echo json_encode([
    'payments' => $payments,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'per_page' => $perPage
    ]
]);

