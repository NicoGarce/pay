<?php
$page_title = "Backup Portal — Payments Hub";
require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();

// handle exports — SQL dumps importable in phpMyAdmin (before any HTML output)
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    // helper to output SQL dump for a single table
    $dumpTable = function($table) use ($con) {
        $tableEsc = str_replace('`','',$table);
        // header
        echo "-- --------------------------------------------------------\n";
        echo "-- UPHSL Payments Backup\n";
        echo "-- Table: `{$tableEsc}`\n";
        echo "-- Date: ".date('Y-m-d H:i:s')."\n";
        echo "-- --------------------------------------------------------\n\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
        echo "DROP TABLE IF EXISTS `{$tableEsc}`;\n";
        $cr = @mysqli_query($con, "SHOW CREATE TABLE `{$tableEsc}`");
        if ($cr && $row = mysqli_fetch_assoc($cr)) {
            $create = $row['Create Table'] ?? $row['Create Table'] ?? '';
            echo $create . ";\n\n";
        }
        // columns for INSERT
        $colsRes = @mysqli_query($con, "SHOW COLUMNS FROM `{$tableEsc}`");
        $cols = [];
        while ($c = @mysqli_fetch_assoc($colsRes)) $cols[] = "`".$c['Field']."`";
        if (empty($cols)) return;
        $colList = implode(',', $cols);
        echo "INSERT INTO `{$tableEsc}` ({$colList}) VALUES\n";
        $res = @mysqli_query($con, "SELECT * FROM `{$tableEsc}`");
        $first = true;
        $batch = 0;
        while ($row = @mysqli_fetch_assoc($res)) {
            $vals = [];
            foreach ($cols as $colRaw) {
                $col = trim($colRaw,'`');
                $v = $row[$col] ?? null;
                if ($v === null) $vals[] = 'NULL';
                else {
                    $vals[] = "'".mysqli_real_escape_string($con, $v)."'";
                }
            }
            if (!$first) echo ",\n";
            echo "(".implode(',', $vals).")";
            $first = false;
            $batch++;
            if ($batch >= 500) { // break into multiple INSERTs for phpMyAdmin limits
                echo ";\nINSERT INTO `{$tableEsc}` ({$colList}) VALUES\n";
                $first = true;
                $batch = 0;
            }
        }
        if (!$first) echo ";\n";
        else echo "-- no data\n";
        echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
    };
    if ($type === 'return_data') {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="backup_return_data_'.date('Y-m-d_His').'.sql"');
        // no BOM for SQL — phpMyAdmin prefers plain utf8
        $dumpTable('return_data');
        exit;
    }
    if ($type === 'campus' || $type === 'campus_tmp') {
        $camp = $_GET['camp'] ?? '';
        $isTmp = ($type === 'campus_tmp');
        $table = $isTmp ? mapCampusToTmpTable($camp) : mapCampusToTable($camp);
        if (!$table || !tableExists($con, $table)) { http_response_code(404); die("Invalid campus/table"); }
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="backup_'.$table.'_'.date('Y-m-d_His').'.sql"');
        $dumpTable($table);
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

// stats for display
$tables = [];
$campuses = ["UPHB"=>"Binan","UPHMU"=>"Medical University","UPHG"=>"GMA","UPHM"=>"Manila","PHCP"=>"Pangasinan","UPHI"=>"Isabela","UPHR"=>"Roxas"];
foreach ($campuses as $code=>$name) {
    $t = mapCampusToTable($code);
    $tt = mapCampusToTmpTable($code);
    $c1 = 0; $c2 = 0;
    if ($t && tableExists($con, $t)) {
        $r = @mysqli_query($con, "SELECT COUNT(*) as c FROM `{$t}`");
        if ($r) $c1 = (int)mysqli_fetch_assoc($r)['c'];
    }
    if ($tt && tableExists($con, $tt)) {
        $r = @mysqli_query($con, "SELECT COUNT(*) as c FROM `{$tt}`");
        if ($r) $c2 = (int)mysqli_fetch_assoc($r)['c'];
    }
    $tables[] = ['code'=>$code,'name'=>$name,'table'=>$t,'tmp'=>$tt,'count'=>$c1,'tmpcount'=>$c2];
}
$totalReturn = 0;
$r = @mysqli_query($con, "SELECT COUNT(*) as c FROM return_data");
if ($r) $totalReturn = (int)mysqli_fetch_assoc($r)['c'];
?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup" class="active">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content">
  <h2 style="margin:0 0 6px">Backup Portal</h2>
  <p style="color:var(--muted); margin:0 0 16px; font-size:13.5px">Create local backups as <strong>.sql</strong> files — import directly in phpMyAdmin (Import tab). No data is deleted.</p>

  <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:14px" class="backup-grid">
    <div style="background:#f8fafc; border:1px solid var(--line); border-radius:14px; padding:16px">
      <div style="font-weight:800; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted)">Payment Transactions</div>
      <div style="font-size:22px; font-weight:800; color:var(--blue); margin:4px 0"><?= number_format($totalReturn) ?> records</div>
      <div style="font-size:12px; color:var(--muted); margin-bottom:12px">Table <code>return_data</code> — all DragonPay transactions</div>
      <a href="<?= $payments_base ?>admin/backup?export=return_data" class="btn" style="background:var(--blue); color:#fff; width:100%; justify-content:center">Download SQL</a>
    </div>
    <div style="background:#fff; border:1px solid var(--line); border-radius:14px; padding:16px">
      <div style="font-weight:800; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted)">How it works</div>
      <ul style="margin:8px 0 0 18px; color:var(--muted); font-size:13px; line-height:1.7">
        <li>.sql opens in phpMyAdmin → Import — keep the file safe.</li>
        <li>Run backups before major imports or enrollment periods.</li>
        <li>Files are generated on demand, not stored on server.</li>
      </ul>
    </div>
  </div>

  <h3 style="margin:20px 0 10px; font-size:15px">Campus Tables</h3>
  <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px" class="backup-grid">
    <?php foreach($tables as $t): ?>
    <div style="background:#fff; border:1px solid var(--line); border-radius:14px; padding:14px">
      <div style="font-weight:800; font-size:13px"><?= htmlspecialchars($t['name']) ?> <span style="font-weight:700; color:var(--muted); font-size:11px">(<?= $t['code'] ?>)</span></div>
      <div style="display:flex; gap:8px; margin:8px 0; flex-wrap:wrap">
        <span style="background:#f1f5f9; border:1px solid #e2e8f0; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700"><?= htmlspecialchars($t['table']) ?>: <?= number_format($t['count']) ?></span>
        <span style="background:#f1f5f9; border:1px solid #e2e8f0; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700"><?= htmlspecialchars($t['tmp']) ?>: <?= number_format($t['tmpcount']) ?></span>
      </div>
      <div style="display:flex; gap:8px">
        <a href="<?= $payments_base ?>admin/backup?export=campus&camp=<?= $t['code'] ?>" class="btn" style="flex:1; background:var(--blue); color:#fff; font-size:12px; padding:8px; justify-content:center">Enrolled SQL</a>
        <a href="<?= $payments_base ?>admin/backup?export=campus_tmp&camp=<?= $t['code'] ?>" class="btn" style="flex:1; background:#fff; border:1px solid var(--line); font-size:12px; padding:8px; justify-content:center">Temp SQL</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p style="font-size:11px; color:var(--muted); margin-top:10px">Tip: Enrolled = <code>binan, gma, manila...</code> • Temporary = <code>binan_tmp_stud...</code> — both are included in the row counts above. Use the Enrolled CSV button per campus; for temporary, use the same button after switching the campus tab in Student Management if needed.</p>

  <style>
    @media(max-width:700px){ .backup-grid{grid-template-columns:1fr !important} }
  </style>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
