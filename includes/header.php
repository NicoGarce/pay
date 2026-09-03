<?php
// Payments Portal Header — standalone, only 3 featured payments in nav
$current = basename($_SERVER['PHP_SELF'], '.php');
$payments_base = $GLOBALS['payments_base'] ?? '/olp/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - UPHSL Payments' : 'UPHSL Online Payments' ?></title>
<link rel="icon" type="image/png" href="<?= $payments_base ?>assets/UPHSJ_LOGO_2026Edition.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $payments_base ?>assets/style.css?v=9">
</head>
<body>
<header class="pay-header">
  <div class="pay-header-inner">
    <a href="<?= $payments_base ?>" class="pay-brand">
      <img src="<?= $payments_base ?>assets/UPHSJ_LOGO_2026Edition.png" alt="UPHSL Logo">
      <div class="pay-brand-text">
        <span class="pay-brand-title">UPHS</span>
        <span class="pay-brand-sub">Online Payments</span>
      </div>
    </a>
    <nav class="pay-nav" id="payNav">
      <a href="<?= $payments_base ?>new-enrollee" class="pay-nav-link <?= $current==='new-enrollee'?'active':'' ?>">New Enrollees</a>
      <a href="<?= $payments_base ?>enrolled" class="pay-nav-link <?= $current==='enrolled'?'active':'' ?>">Enrolled</a>
      <a href="<?= $payments_base ?>other" class="pay-nav-link <?= $current==='other'?'active':'' ?>">Other Payments</a>
      <a href="<?= $payments_base ?>instructions" class="pay-nav-link <?= $current==='instructions'?'active':'' ?>">How to Pay</a>
      <?php if (isset($_SESSION['olp_user_role']) && $_SESSION['olp_user_role']==='super_admin'): ?>
        <a href="<?= $payments_base ?>admin/" class="pay-nav-link pay-nav-admin">Admin</a>
      <?php endif; ?>
    </nav>
    <button class="pay-burger" id="payBurger" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
  </div>
</header>
<main class="pay-main">
<script>
document.getElementById('payBurger')?.addEventListener('click',()=>{
  document.getElementById('payNav').classList.toggle('open');
});
</script>

