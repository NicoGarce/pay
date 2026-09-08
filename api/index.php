<?php
/**
 * OLP — GTRN / Locator API
 * Imported from uphsledu/online_payment/api.php — standalone
 * Now uses OLP includes/dbconnect.php ($con) instead of hardcoded creds.
 * Keeps backwards compat with old ?action=getUsers&enrlid=xxx
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/dbconnect.php';

// $con is mysqli from includes/dbconnect.php (OLP standalone, uphsledu_onlinepayment)
if (!isset($con) || !$con) {
    echo json_encode(["status" => "error", "message" => "DB Connection Failed"]);
    exit();
}
@mysqli_set_charset($con, "utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$enrlid = $_GET['enrlid'] ?? ($_POST['enrlid'] ?? '');

if ($action === "getUsers") {
    if (trim($enrlid) === '') {
        echo json_encode(["status" => "error", "message" => "Missing enrlid"]);
        exit();
    }
    // Check if tblgtrn exists (legacy table — may not exist on fresh OLP installs)
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='tblgtrn' LIMIT 1");
    if (!$check || !mysqli_fetch_row($check)) {
        echo json_encode(["status" => "success", "data" => [], "note" => "tblgtrn not available on this host — use _tmp_stud tables instead"]);
        exit();
    }
    $stmt = @mysqli_prepare($con, "SELECT locno, gtrno FROM tblgtrn WHERE is_valid=1 AND locno=?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed"]);
        exit();
    }
    mysqli_stmt_bind_param($stmt, "s", $enrlid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($res)) { $users[] = $row; }
    mysqli_stmt_close($stmt);
    echo json_encode(["status" => "success", "data" => $users]);
    exit();
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
