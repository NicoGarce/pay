<?php
$page_title = "Payments Hub";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero" style="text-align:center; padding:48px 36px">
  <span class="hero-badge" style="background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28)">University of Perpetual Help System</span>
  <h1>Online Payment</h1>
  <p style="margin:12px auto 0; max-width:640px">Secure and convenient online payments for everyone — students, parents, alumni, and guests. Pay tuition, enrollment fees, and other university charges anytime, anywhere via DragonPay (Online Banking, E-Wallets, and Over the Counter).</p>
  <div style="margin:16px auto 0;display:inline-flex;align-items:center;gap:10px;background:rgba(255,255,255,.95);border:1px solid rgba(255,255,255,.28);padding:8px 14px;border-radius:999px;backdrop-filter:blur(6px)">
    <img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" style="height:26px;width:auto;object-fit:contain">
  </div>
  <div class="hero-actions" style="justify-content:center; margin-top:22px">
    <a href="#pay-options" class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Start Payment</a>
    <a href="<?= $payments_base ?>instructions" class="btn btn-ghost" style="background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.28)"><i class="fa-solid fa-circle-question"></i> How to Pay</a>
  </div>
</section>

<section id="pay-options" class="cards">
  <!-- New Enrollees -->
  <article class="pay-card featured">
    <div class="pay-card-icon" style="background:linear-gradient(135deg,var(--blue),#6ea0ff)"><i class="fa-solid fa-user-plus"></i></div>
    <h3>New Enrollees</h3>
    <p>For students <strong>not previously enrolled</strong>. Only your <strong>locator number</strong> is required. Verifies against temporary student tables.</p>
    <ul>
      <li><i class="fa-solid fa-circle-check"></i> Locator number verification (Binan + all campuses)</li>
      <li><i class="fa-solid fa-circle-check"></i> Auto-fill name from advising records</li>
      <li><i class="fa-solid fa-circle-check"></i> Downpayment / Reservation ready</li>
    </ul>
    <a href="<?= $payments_base ?>new-enrollee" class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Pay as New Enrollee</a>
  </article>

  <!-- Enrolled -->
  <article class="pay-card">
    <div class="pay-card-icon" style="background:linear-gradient(135deg,#0e8a6b,#34d399)"><i class="fa-solid fa-id-card"></i></div>
    <h3>Enrolled Students</h3>
    <p>For <strong>currently enrolled</strong> students with a valid <strong>student number</strong>. Full particulars available (tuition, back account, etc.).</p>
    <ul>
      <li><i class="fa-solid fa-circle-check"></i> Student number verification per campus table</li>
      <li><i class="fa-solid fa-circle-check"></i> All particulars incl. Tuition Fee / Back Account</li>
      <li><i class="fa-solid fa-circle-check"></i> SY & Semester tagging</li>
    </ul>
    <a href="<?= $payments_base ?>enrolled" class="btn btn-primary" style="background:linear-gradient(135deg,#0e8a6b,#15b88f)"><i class="fa-solid fa-arrow-right"></i> Pay as Enrolled</a>
  </article>

  <!-- Other -->
  <article class="pay-card">
    <div class="pay-card-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fa-solid fa-file-invoice"></i></div>
    <h3>Other Payments</h3>
    <p><strong>General payments</strong> with <strong>no locator or student number</strong> required. For alumni, parents, guests, or miscellaneous fees.</p>
    <ul>
      <li><i class="fa-solid fa-circle-check"></i> No ID verification — open form</li>
      <li><i class="fa-solid fa-circle-check"></i> Any description (activity, cert, etc.)</li>
      <li><i class="fa-solid fa-circle-check"></i> Email receipt to any address</li>
    </ul>
    <a href="<?= $payments_base ?>other" class="btn btn-primary" style="background:linear-gradient(135deg,#d97706,#ffb84d)"><i class="fa-solid fa-arrow-right"></i> Pay Other Fees</a>
  </article>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
