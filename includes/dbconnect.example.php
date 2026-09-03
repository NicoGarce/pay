<?php
// Example — COPY to dbconnect.php and fill real credentials (see .gitignore)
// Do NOT commit dbconnect.php with real passwords
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }
$con = @mysqli_connect("localhost","root","","uphsledu_onlinepayment"); // <-- set your DB, user, pass
if(!$con){
    // fallback: try legacy typo DB name if it exists on this host
    $con = @mysqli_connect("localhost","root","","uphsedu_onlinepayment");
}
if(!$con){
    $con = null;
} else {
    @mysqli_set_charset($con, "utf8mb4");
}
