<?php
/**
 * OLP — Campus Tables Initialization
 * Imported from uphsledu/online_payment/init_campus_tables.php — standalone
 * Run once to create all campus + tmp tables. Now protected by admin auth.
 */
require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();
require_once __DIR__ . '/../includes/campus_table_manager.php';

$result = ensureCampusTablesExist($con);
logTableCreation($con, $result);
$tmpResult = ensureTmpStudentTablesExist($con);
logTableCreation($con, $tmpResult);

$page_title = "Init Campus Tables";
require_once __DIR__ . '/../includes/header.php';
$payments_base = $GLOBALS['payments_base'] ?? '/';
?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/transaction">OR Settlement</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/init_campus_tables" class="active">Init Tables</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content">
  <h2 style="margin:0 0 8px;color:var(--blue);font-family:'Plus Jakarta Sans',sans-serif">Campus Tables Initialization</h2>
  <?php if ($result['total_created'] > 0): ?>
    <div class="alert ok"><i class="fa-solid fa-circle-check"></i><div>Successfully created <?= $result['total_created'] ?> new campus table(s): <strong><?= htmlspecialchars(implode(', ', $result['created'])) ?></strong></div></div>
  <?php else: ?>
    <div class="alert info"><i class="fa-solid fa-circle-info"></i><div>No new campus tables needed — all exist.</div></div>
  <?php endif; ?>
  <?php if ($result['total_existing'] > 0): ?>
    <div class="alert info" style="margin-top:10px"><i class="fa-solid fa-list"></i><div><?= $result['total_existing'] ?> existing: <?= htmlspecialchars(implode(', ', $result['existing'])) ?></div></div>
  <?php endif; ?>
  <?php if ($tmpResult['total_created'] > 0): ?>
    <div class="alert ok" style="margin-top:10px"><i class="fa-solid fa-circle-check"></i><div>Created <?= $tmpResult['total_created'] ?> tmp tables: <strong><?= htmlspecialchars(implode(', ', $tmpResult['created'])) ?></strong></div></div>
  <?php endif; ?>
  <?php if ($tmpResult['total_existing'] > 0): ?>
    <div class="alert info" style="margin-top:10px"><i class="fa-solid fa-list"></i><div><?= $tmpResult['total_existing'] ?> tmp existing: <?= htmlspecialchars(implode(', ', $tmpResult['existing'])) ?></div></div>
  <?php endif; ?>
  <div style="margin-top:16px;padding:14px;background:#f8fafc;border:1px solid var(--line);border-radius:12px;font-size:13px;color:var(--muted)">
    <strong>All tables:</strong> binan (UPHB), medical_university (UPHMU), gma (UPHG), manila (UPHM), pangasinan (PHCP), isabela (UPHI), roxas (UPHR) + tmp variants.<br>
    Auto-created on every request via <code>includes/config.php:13-14</code> — this page is just for manual verification.
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
