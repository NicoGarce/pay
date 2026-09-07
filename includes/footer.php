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
  <div class="pay-footer-bottom">© <?= date('Y') ?> UPHSL • Secure payments via DragonPay</div>
</footer>
</body>
</html>
