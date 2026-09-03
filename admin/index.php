<?php
$page_title = "Payments Admin";
require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();
require_once __DIR__ . '/../includes/header.php';

// stats
$totalPayments = 0;
$pendingCount = 0;
$res = @mysqli_query($con, "SELECT COUNT(*) as c FROM return_data");
if ($res) $totalPayments = (int)mysqli_fetch_assoc($res)['c'];
$res2 = @mysqli_query($con, "SELECT COUNT(*) as c FROM return_data WHERE status LIKE '%pending%' OR status='' OR status IS NULL");
if ($res2) $pendingCount = (int)mysqli_fetch_assoc($res2)['c'];
?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/" class="active">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content">
    <h2 style="margin:0 0 6px"><i class="fa-solid fa-gauge"></i> Dashboard</h2>
    <p style="color:var(--muted)">Standalone payments hub — all flows preserved, revamped.</p>
    <div class="kpi" style="margin-top:14px">
      <div><div style="color:var(--muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Total Transactions</div><strong><?= number_format($totalPayments) ?></strong><div style="color:var(--muted);font-size:12px">return_data</div></div>
      <div><div style="color:var(--muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Pending / Unknown</div><strong><?= number_format($pendingCount) ?></strong><div style="color:var(--muted);font-size:12px">status</div></div>
      <div><div style="color:var(--muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Hub Links</div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><a href="<?= $payments_base ?>new-enrollee" class="badge ok">New Enrollee</a><a href="<?= $payments_base ?>enrolled" class="badge ok">Enrolled</a><a href="<?= $payments_base ?>other" class="badge ok">Other</a></div></div>
      <div><div style="color:var(--muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Quick Actions</div><div style="display:grid;gap:8px;margin-top:8px"><a href="<?= $payments_base ?>admin/students" class="btn" style="background:var(--blue);color:#fff"><i class="fa-solid fa-users"></i> Manage Students</a><a href="<?= $payments_base ?>admin/monitoring" class="btn" style="background:#fff;border:1px solid var(--line)"><i class="fa-solid fa-chart-line"></i> View Payments</a></div></div>
    </div>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
