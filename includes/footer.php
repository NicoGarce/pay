<?php
// Ensure payments_base is available in footer
$payments_base = $GLOBALS['payments_base'] ?? '/';
?>
</main>
<footer class="pay-footer">
  <div class="pay-footer-inner" style="justify-content:center; text-align:center">
    <div class="pay-footer-brand" style="justify-content:center">
      <img src="<?= $payments_base ?>assets/UPHSJ_LOGO_2026Edition.png" alt="UPHSL Logo">
      <div>
        <strong>University of Perpetual Help System</strong>
      </div>
    </div>
  </div>
    <div class="pay-footer-bottom" style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap">© <?= date('Y') ?> UPHSL <img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" class="dp-logo dp-logo--sm" style="height:20px"></div>
</footer>
</body>
</html>
