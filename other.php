<?php
$page_title = "Other Payments — General";
require_once __DIR__ . '/includes/config.php';

// No verification required — direct to checkout
if (isset($_POST['btnsubmit'])) {
    $payee = trim($_POST['payee_name'] ?? 'Guest Payer');
    $transid = 'GEN_' . date("HismdY") . '_' . substr(uniqid(), -4);
    header("Location: checkout?payee=".urlencode($payee)."&transid=".urlencode($transid)."&type=other");
    exit;
}
require_once __DIR__ . '/includes/header.php';
?>
<div style="max-width:780px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
    <a href="<?= $payments_base ?>" class="btn" style="background:#fff;border:1px solid var(--line); padding:7px 12px; font-size:12px">Back</a>
    <span style="color:var(--muted);font-weight:600; font-size:12px">Other Payments</span>
  </div>

  <div class="form-card">
    <div class="form-head" style="background:linear-gradient(135deg,#b45309,#f59e0b)">
      <i class="fa-solid fa-file-invoice"></i>
      <div>
        <div style="font-weight:800;font-size:18px">General / Other Payment</div>
        <div style="opacity:.9;font-size:13px">No locator or student number required — for alumni, parents, guests.</div>
      </div>
    </div>
    <div class="form-body">
      <div class="alert warn"><i class="fa-solid fa-circle-info"></i><div><strong>Flexible:</strong> Choose any particular, enter your name and contact, and pay. Receipt goes to your email.</div></div>

      <form method="post" id="otherForm">
        <div class="field">
          <label for="payee_name">Payer Name <span style="color:var(--err)">*</span></label>
          <input type="text" name="payee_name" id="payee_name" placeholder="Full name for receipt" required>
          <small style="color:var(--muted)">Add contact no. for reference (e.g., Juan Dela Cruz - 0912...)</small>
        </div>
        <div class="field">
          <label for="payee_contact">Contact / Email (for e-receipt)</label>
          <input type="text" name="payee_contact" id="payee_contact" placeholder="Optional: phone or email">
        </div>
        <button type="submit" name="btnsubmit" class="btn btn-primary" style="width:100%;padding:16px;background:linear-gradient(135deg,#b45309,#f59e0b)"><i class="fa-solid fa-arrow-right"></i> Continue to Payment Details</button>
      </form>

      <div style="border-top:1px solid var(--line);padding-top:14px;margin-top:4px">
        <h4 style="margin:0 0 8px"><i class="fa-solid fa-list"></i> What you can pay</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;color:var(--muted)">
          <span><i class="fa-solid fa-check" style="color:var(--ok)"></i> Activity / Alumni / Certificate</span>
          <span><i class="fa-solid fa-check" style="color:var(--ok)"></i> Good Moral / Transcript / CAV</span>
          <span><i class="fa-solid fa-check" style="color:var(--ok)"></i> Uniform / Books / ID</span>
          <span><i class="fa-solid fa-check" style="color:var(--ok)"></i> Any custom description</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
