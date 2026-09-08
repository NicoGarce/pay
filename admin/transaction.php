<?php
/**
 * OLP — Transaction / OR Settlement
 * Unified replacement for uphsledu/online_payment/transaction.php +
 * transaction_gma.php / transaction_manila.php / transaction_pangasinan.php
 * Modern UI (OLP header/footer), prepared statements, campus filter.
 *
 * Sets receipt_no/postdate WHERE refno=?  via ?refno=xxx&orno=yyy
 */
require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();

$page_title = "Transaction Settlement — OR Entry";
$error = '';
$success = '';

// Handle OR assignment (GET as legacy, also POST for safety)
$refnoInput = $_GET['refno'] ?? ($_POST['refno'] ?? '');
$ornoInput  = $_GET['orno']  ?? ($_POST['orno']  ?? '');
if ($refnoInput !== '' && $ornoInput !== '') {
    $refnoInput = trim($refnoInput);
    $ornoInput  = trim($ornoInput);
    if ($refnoInput !== '' && $ornoInput !== '' && isset($con) && $con instanceof mysqli) {
        @mysqli_set_charset($con, 'utf8mb4');
        $stmt = @mysqli_prepare($con, "UPDATE return_data SET receipt_no=?, postdate=NOW() WHERE refno=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $ornoInput, $refnoInput);
            if (mysqli_stmt_execute($stmt)) {
                $success = "OR No. <strong>" . htmlspecialchars($ornoInput) . "</strong> set for Reference <strong>" . htmlspecialchars($refnoInput) . "</strong>.";
            } else {
                $error = "Failed to set OR: " . htmlspecialchars(mysqli_error($con));
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Prepare failed: " . htmlspecialchars(mysqli_error($con));
        }
    } else {
        $error = "DB not available or missing parameters.";
    }
    // PRG to avoid repeat on refresh — keep filters
    if ($success && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $qs = [];
        if (!empty($_GET['campus'])) $qs['campus'] = $_GET['campus'];
        if (!empty($_GET['dfrom'])) $qs['dfrom'] = $_GET['dfrom'];
        if (!empty($_GET['dto'])) $qs['dto'] = $_GET['dto'];
        if (!empty($_GET['search'])) $qs['search'] = $_GET['search'];
        $qs['settled'] = '1';
        // we don't redirect to lose message, just show it
    }
}

$curdate = date("Y-m-d");
// Filters
$campusFilter = strtoupper(trim($_GET['campus'] ?? ($_POST['campus'] ?? '')));
$dfrom = $_POST['dfrom'] ?? ($_GET['dfrom'] ?? $curdate);
$dto   = $_POST['dto']   ?? ($_GET['dto']   ?? $curdate);
$search = trim($_GET['search'] ?? ($_POST['search'] ?? ''));

// Build WHERE — only Success rows as per original
$where = ["status='Success'"];
$params = [];
$types = "";

$campusMap = [
    'UPHB' => "(txnid LIKE 'UPHB\\_%' OR txnid LIKE 'UPHB-%')",
    'UPHMU'=> "txnid LIKE 'UPHMU\\_%' OR txnid LIKE 'UPHMU-%'",
    'UPHG' => "txnid LIKE 'UPHG\\_%' OR txnid LIKE 'UPHG-%'",
    'UPHM' => "(txnid LIKE 'UPHM\\_%' AND txnid NOT LIKE 'UPHMU\\_%' AND txnid NOT LIKE 'UPHMU-%') OR txnid LIKE 'UPHM-%'",
    'PHCP' => "txnid LIKE 'PHCP\\_%' OR txnid LIKE 'PHCP-%'",
    'UPHI' => "txnid LIKE 'UPHI\\_%' OR txnid LIKE 'UPHI-%'",
    'UPHR' => "txnid LIKE 'UPHR\\_%' OR txnid LIKE 'UPHR-%'",
    'GEN'  => "txnid LIKE 'GEN\\_%'",
];
if ($campusFilter !== '' && isset($campusMap[$campusFilter])) {
    $where[] = "(" . $campusMap[$campusFilter] . ")";
} elseif ($campusFilter === 'ALL' || $campusFilter === '') {
    // no campus filter — show all Success
}

if (!empty($dfrom) && !empty($dto)) {
    $where[] = "transdate BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND transdate IS NOT NULL";
    $params[] = $dfrom;
    $params[] = $dto;
    $types .= "ss";
}

if ($search !== '' && isset($con) && $con instanceof mysqli) {
    $searchEsc = mysqli_real_escape_string($con, $search);
    $where[] = "(txnid LIKE '%{$searchEsc}%' OR refno LIKE '%{$searchEsc}%' OR receipt_no LIKE '%{$searchEsc}%')";
}

$whereClause = implode(" AND ", $where);
$sql = "SELECT transdate, txnid, refno, status, amount, message, receipt_no, postdate FROM return_data WHERE {$whereClause} ORDER BY transdate DESC LIMIT 500";

$rows = [];
if (isset($con) && $con instanceof mysqli) {
    @mysqli_set_charset($con, 'utf8mb4');
    $stmt = @mysqli_prepare($con, $sql);
    if ($stmt && $types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        mysqli_stmt_close($stmt);
    } elseif ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        mysqli_stmt_close($stmt);
    } else {
        $res = @mysqli_query($con, $sql);
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        else $error = "Query failed: " . htmlspecialchars(mysqli_error($con));
    }
}

require_once __DIR__ . '/../includes/header.php';
$payments_base = $GLOBALS['payments_base'] ?? '/';
?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/transaction" class="active">OR Settlement</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>

<div class="admin-content" style="padding:0;overflow:hidden">
<style>
.txn-wrap{padding:22px;max-width:1200px;margin:0 auto}
.txn-filters{background:#f8fafc;border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px;display:grid;gap:12px}
.txn-filters-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
@media(max-width:900px){.txn-filters-row{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.txn-filters-row{grid-template-columns:1fr}}
.txn-filters label{font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#475569}
.txn-filters input,.txn-filters select{width:100%;padding:10px 12px;border:1px solid #dfe6f5;border-radius:10px;font:600 13px Inter;background:#fff}
.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:14px;background:#fff}
table{width:100%;border-collapse:collapse;font-size:13px}
th{white-space:nowrap;background:#1c4da1;color:#fff;text-align:left;padding:10px 12px;font-size:11px;letter-spacing:.05em;text-transform:uppercase}
td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top}
tr:hover td{background:#f8fafc}
.badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;border:1px solid var(--line)}
.badge.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.badge.wait{background:#fffbeb;color:#92400e;border-color:#fde68a}
</style>

<div class="txn-wrap">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
    <h2 style="margin:0;color:var(--blue);font-family:'Plus Jakarta Sans',sans-serif">OR Settlement</h2>
    <span style="margin-left:auto;background:#eef2ff;border:1px solid #dbeafe;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:800;color:var(--blue)"><?= count($rows) ?> Success rows</span>
  </div>
  <p style="margin:0 0 12px;color:var(--muted);font-size:13px">Set Official Receipt numbers for <strong>Success</strong> transactions. This mirrors <code>online_payment/transaction.php</code> but unified for all campuses (Binan/MU/GMA/Manila/Pangasinan/Isabela/Roxas/GEN). Uses prepared statements. <code>receipt_no</code> + <code>postdate</code> are written where <code>refno</code> matches.</p>

  <?php if ($error): ?><div class="alert err" style="margin-bottom:12px"><i class="fa-solid fa-triangle-exclamation"></i><div><?= $error ?></div></div><?php endif; ?>
  <?php if ($success): ?><div class="alert ok" style="margin-bottom:12px"><i class="fa-solid fa-circle-check"></i><div><?= $success ?></div></div><?php endif; ?>

  <form method="post" class="txn-filters">
    <div class="txn-filters-row">
      <div><label>Campus</label>
        <select name="campus">
          <option value="">All campuses</option>
          <option value="UPHB" <?= $campusFilter==='UPHB'?'selected':'' ?>>Binan (UPHB)</option>
          <option value="UPHMU" <?= $campusFilter==='UPHMU'?'selected':'' ?>>Medical University (UPHMU)</option>
          <option value="UPHG" <?= $campusFilter==='UPHG'?'selected':'' ?>>GMA (UPHG)</option>
          <option value="UPHM" <?= $campusFilter==='UPHM'?'selected':'' ?>>Manila (UPHM)</option>
          <option value="PHCP" <?= $campusFilter==='PHCP'?'selected':'' ?>>Pangasinan (PHCP)</option>
          <option value="UPHI" <?= $campusFilter==='UPHI'?'selected':'' ?>>Isabela (UPHI)</option>
          <option value="UPHR" <?= $campusFilter==='UPHR'?'selected':'' ?>>Roxas (UPHR)</option>
          <option value="GEN" <?= $campusFilter==='GEN'?'selected':'' ?>>General (GEN)</option>
        </select>
      </div>
      <div><label>From (yyyy-mm-dd)</label><input type="date" name="dfrom" value="<?= htmlspecialchars($dfrom) ?>"></div>
      <div><label>To (yyyy-mm-dd)</label><input type="date" name="dto" value="<?= htmlspecialchars($dto) ?>"></div>
      <div><label>Search (txnid / refno / OR)</label><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g., UPHB_... or REF..."></div>
      <div><button type="submit" class="btn btn-primary" style="background:var(--blue);color:#fff;width:100%"><i class="fa-solid fa-filter"></i> Filter</button></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <span style="font-size:11px;color:#64748b;background:#fff;border:1px dashed #cbd5e1;padding:6px 10px;border-radius:999px">Showing Success only • Limit 500 • <a href="<?= $payments_base ?>admin/monitoring">Full monitoring has all statuses</a></span>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Transaction Date</th><th>Transaction No</th><th>Reference No</th><th>Status</th><th>Amount</th><th>Message</th><th>OR No.</th><th>Set Date</th>
      </tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--muted)"><i class="fa-solid fa-inbox" style="font-size:20px"></i><div style="margin-top:6px">No Success transactions for this filter. Try changing campus/dates.</div></td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap"><?= htmlspecialchars($r['transdate'] ?? '') ?></td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($r['txnid'] ?? '') ?></td>
            <td style="font-family:monospace;text-align:center"><?= htmlspecialchars($r['refno'] ?? '') ?></td>
            <td><span class="badge ok"><?= htmlspecialchars($r['status'] ?? '') ?></span></td>
            <td style="text-align:right;white-space:nowrap">₱<?= htmlspecialchars(number_format((float)($r['amount'] ?? 0),2)) ?></td>
            <td style="max-width:280px;word-break:break-word"><?= htmlspecialchars($r['message'] ?? '') ?></td>
            <td style="text-align:center">
              <?php if (empty($r['receipt_no'])): ?>
                <a href="javascript:getor('<?= htmlspecialchars($r['refno'] ?? '', ENT_QUOTES) ?>')" class="btn" style="padding:6px 10px;background:#0e9f6e;color:#fff;border-radius:999px;font-size:11px;font-weight:800">Set OR</a>
              <?php else: ?>
                <span style="font-weight:800;color:#065f46"><?= htmlspecialchars($r['receipt_no']) ?></span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap"><?= htmlspecialchars($r['postdate'] ?? '') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:10px;font-size:11px;color:#94a3b8;text-align:center">Tip: Click <strong>Set OR</strong> and enter the official receipt number. It is saved via prepared <code>UPDATE return_data SET receipt_no=?, postdate=NOW() WHERE refno=?</code> — parity with <code>transaction.php:8</code>.</div>
</div>
</div>

<script>
function getor(refno){
  var orno = prompt("Enter OR No. for Reference No.: " + refno);
  if (orno === null) return;
  orno = orno.trim();
  if (orno === "") { alert("OR No. cannot be empty."); return; }
  if (confirm("If you are sure that OR number '" + orno + "' is correct for " + refno + ", click OK")==true) {
    var url = "transaction?refno=" + encodeURIComponent(refno) + "&orno=" + encodeURIComponent(orno);
    // preserve filters
    var params = new URLSearchParams(window.location.search);
    if (params.get('campus')) url += "&campus=" + encodeURIComponent(params.get('campus'));
    if (params.get('dfrom')) url += "&dfrom=" + encodeURIComponent(params.get('dfrom'));
    if (params.get('dto')) url += "&dto=" + encodeURIComponent(params.get('dto'));
    window.location = url;
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
