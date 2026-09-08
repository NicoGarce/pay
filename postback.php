<?php
/**
 * OLP — DragonPay Postback (server-to-server) — authoritative
 * Parity with uphsledu/online_payment/postback.php
 * DragonPay POSTs: txnid, refno, status, message, digest, etc.
 * Updates return_data: status (human label), message where refno = ?
 * Returns plain text "result=OK" expected by DragonPay.
 */
require_once __DIR__ . '/includes/config.php';

$s = "";
$raw = $_POST["status"] ?? ($_GET["status"] ?? "");
if ($raw=="S") {$s="Success";}
if ($raw=="F") {$s="Failure";}
if ($raw=="P") {$s="Pending";}
if ($raw=="U") {$s="Unknown";}
if ($raw=="R") {$s="Refund";}
if ($raw=="K") {$s="Chargeback";}
if ($raw=="V") {$s="Void";}
if ($raw=="A") {$s="Authorized";}
if ($s==="" && $raw!=="") $s = $raw;

if (!isset($con) || !$con instanceof mysqli) {
    http_response_code(500);
    echo "result=FAIL"; exit;
}
@mysqli_set_charset($con, "utf8mb4");

// Prefer refno lookup (as original), but also handle txnid fallback
$refno = $_POST["refno"] ?? ($_GET["refno"] ?? '');
$txnid = $_POST["txnid"] ?? ($_GET["txnid"] ?? '');
$message = $_POST["param2"] ?? ($_POST["message"] ?? ($_GET["param2"] ?? ($_GET["message"] ?? '')));

if ($refno !== '') {
    $stmt = @mysqli_prepare($con, "UPDATE return_data SET status=?, message=? WHERE refno=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $s, $message, $refno);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
    } else {
        @mysqli_query($con, "UPDATE return_data SET status='".mysqli_real_escape_string($con,$s)."', message='".mysqli_real_escape_string($con,$message)."' WHERE refno='".mysqli_real_escape_string($con,$refno)."'");
    }
    // also update by txnid if no ref rows yet (brand new pending)
    if ($txnid !== '' && @mysqli_affected_rows($con)===0) {
        $stmt2 = @mysqli_prepare($con, "UPDATE return_data SET status=?, message=?, refno=? WHERE txnid=?");
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "ssss", $s, $message, $refno, $txnid);
            @mysqli_stmt_execute($stmt2);
            @mysqli_stmt_close($stmt2);
        }
    }
} elseif ($txnid !== '') {
    $stmt = @mysqli_prepare($con, "UPDATE return_data SET status=?, message=? WHERE txnid=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $s, $message, $txnid);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
    }
}
echo "result=OK";
