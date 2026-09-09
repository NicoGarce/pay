<?php
$page_title = "How to Pay Online - Instructions";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
?>
<section style="text-align:center; max-width:720px; margin:0 auto 14px">
  <h1 style="margin:0; font-size:28px; font-family:'Plus Jakarta Sans',sans-serif">How to Pay Online</h1>
  <p style="color:var(--muted); margin:8px 0 0; font-size:14.5px">Choose your payment type. Each tab shows exactly what to do — with figures from the actual form.</p>
  <button onclick="window.print()" class="btn no-print" style="margin-top:12px; background:#fff; border:1px solid var(--line); padding:9px 16px; font-size:13px"><i class="fa-solid fa-print"></i> Print this guide</button>
</section>

<div class="tabs-navigation no-print" role="tablist" style="justify-content:center">
  <button class="tab-btn active" data-tab="new">New Enrollee</button>
  <button class="tab-btn" data-tab="enrolled">Enrolled</button>
  <button class="tab-btn" data-tab="other">Other Payment</button>
</div>

<div id="tab-new" class="tab-content active">
  <div class="print-only" style="text-align:center; margin-bottom:10px; padding:12px; background:#f1f5f9; border:1px solid #ddd; border-radius:12px">
    <div style="font-weight:800; font-size:14px; color:var(--blue); letter-spacing:.04em; text-transform:uppercase">New Enrollee Guide</div>
    </div>
  <div class="section" style="margin-top:0">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">1</span><strong>What you need</strong></div>
    <ul style="margin:0 0 14px 28px; color:var(--muted); font-size:14px; line-height:1.6">
      <li>Campus where you were advised</li>
      <li>Locator number — on your <strong style="color:var(--text)">white form</strong>, above your name</li>
    </ul>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">2</span><strong>Steps with figures</strong></div>
    <div class="steps-fig">
      <div class="step-row">
        <div class="step-text"><span class="step-num">1</span><span><strong>Select campus</strong> — pick where you were advised. The locator box appears after you choose.</span></div>
        <div class="fig"><div class="fig-ui"><span>Select Your Campus</span><span>▾</span></div><div style="font-size:11px; color:#94a3b8; margin-top:6px; text-align:center">Binan • GMA • Manila • Pangasinan • Isabela • Roxas • Medical University</div></div>
        <div class="fig-caption">Fig. 1 — Campus dropdown. Choose one to unlock verification.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num">2</span><span><strong>Enter locator</strong> — copy the number from the <strong>white form above your name</strong>, tap <em>Verify</em>.</span></div>
        <div class="fig"><div style="display:flex; gap:8px; align-items:end"><div style="flex:1; border:1px solid #dfe6f5; border-radius:10px; padding:10px; background:#fbfdff"><div style="font-size:10px; font-weight:800; color:#1d2a44; letter-spacing:.06em; text-transform:uppercase">Locator Number</div><div style="font-size:13px; color:var(--blue); font-weight:700; margin-top:4px">26417244</div></div><div style="background:var(--blue); color:#fff; padding:11px 14px; border-radius:10px; font-weight:800; font-size:12px">Verify</div></div></div>
        <div class="fig-caption">Fig. 2 — Locator field + Verify button. Wait for the result card below.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num">3</span><span><strong>Confirm name</strong> — check the card that appears (avatar + name + locator + campus), tap <em>Yes, it’s me</em>.</span></div>
        <div class="fig"><div class="verify-card" style="pointer-events:none; opacity:1"><div class="verify-card-top ok"><div class="verify-icon">✓</div><div><div class="verify-title">Verified</div><div class="verify-subtitle">Locator verified successfully!</div></div></div><div class="verify-card-body"><div class="verify-profile"><div class="verify-avatar">JD</div><div><div class="verify-name">Juan Dela Cruz</div><div class="verify-sub">Is this you?</div></div></div><div class="verify-meta"><span>26417244</span><span>Binan Campus</span></div></div></div></div>
        <div class="fig-caption">Fig. 3 — Verification card. Confirm only if the name is exactly yours.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num">4</span><span><strong>Proceed to payment</strong> — fill amount, description (e.g., Downpayment), school year/sem, and email for receipt.</span></div>
        <div class="fig"><div style="border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; display:grid; gap:8px"><div style="display:grid; grid-template-columns:1fr 1fr; gap:8px"><div style="border:1px solid #dfe6f5; border-radius:8px; padding:8px; font-size:11px"><span style="font-weight:700; color:var(--muted)">Amount</span><div style="font-weight:800; color:var(--blue)">₱ 5,000.00</div></div><div style="border:1px solid #dfe6f5; border-radius:8px; padding:8px; font-size:11px"><span style="font-weight:700; color:var(--muted)">Email</span><div style="font-weight:600">you@email.com</div></div></div><div style="border:1px solid #dfe6f5; border-radius:8px; padding:8px; font-size:11px"><span style="font-weight:700; color:var(--muted)">Description</span><div>JD (26417244) >> Downpayment, 2025-2026, 1st Sem</div></div></div></div>
        <div class="fig-caption">Fig. 4 — Checkout form. The description is auto-built from your verified name.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num">5</span><span><strong>Pay via DragonPay</strong> — choose <strong>Online Banking, E-Wallets, or Over the Counter</strong>, then complete. Keep your Transaction ID.</span></div>
        <div class="fig"><div style="display:grid;gap:8px;place-items:center"><img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" class="dp-logo" style="height:26px"><div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:center"><span style="background:var(--blue); color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Online Banking</span><span style="background:#10b981; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">E-Wallets</span><span style="background:#f59e0b; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Over the Counter</span></div></div></div>
        <div class="fig-caption">Fig. 5 — DragonPay options. You’ll be redirected to complete payment.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#10b981; color:#fff">6</span><span><strong>Present screenshot to cashier to claim official receipt</strong> — after payment, screenshot the DragonPay confirmation and present it to the cashier to claim your official receipt.</span></div>
        </div>
    </div>
    <div class="alert info" style="margin-top:14px"><div><strong>If not found:</strong> Finish advising first — locator is issued there.</div></div>
    <a href="<?= $payments_base ?>new-enrollee" class="btn btn-primary no-print" style="margin-top:14px; background:var(--blue); color:#fff; width:100%; justify-content:center">Pay as New Enrollee</a>
  </div>
</div>

<div id="tab-enrolled" class="tab-content">
  <div class="print-only" style="text-align:center; margin-bottom:10px; padding:12px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:12px">
    <div style="font-weight:800; font-size:14px; color:#065f46; letter-spacing:.04em; text-transform:uppercase">Enrolled Guide</div>
    </div>
  <div class="section" style="margin-top:0">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:#0e8a6b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">1</span><strong>What you need</strong></div>
    <ul style="margin:0 0 14px 28px; color:var(--muted); font-size:14px; line-height:1.6">
      <li>Campus</li>
      <li>Student ID — on your <strong style="color:var(--text)">yellow form</strong>, above your name</li>
    </ul>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:#0e8a6b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">2</span><strong>Steps with figures</strong></div>
    <div class="steps-fig">
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#ecfdf5;color:#065f46">1</span><span><strong>Select campus</strong>.</span></div>
        <div class="fig"><div class="fig-ui"><span>Select Your Campus</span><span>▾</span></div></div>
        <div class="fig-caption">Fig. 1 — Same campus selector as New Enrollee.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#ecfdf5;color:#065f46">2</span><span><strong>Enter Student ID</strong> — copy the number from the <strong>yellow form above your name</strong>, tap <em>Verify</em>.</span></div>
        <div class="fig"><div style="display:flex; gap:8px; align-items:end"><div style="flex:1; border:1px solid #dfe6f5; border-radius:10px; padding:10px; background:#fbfdff"><div style="font-size:10px; font-weight:800; color:#1d2a44; letter-spacing:.06em; text-transform:uppercase">Student Number</div><div style="font-size:13px; color:var(--blue); font-weight:700; margin-top:4px">2023-12345</div></div><div style="background:#0e8a6b; color:#fff; padding:11px 14px; border-radius:10px; font-weight:800; font-size:12px">Verify</div></div></div>
        <div class="fig-caption">Fig. 2 — Student number field. Use the exact number on your ID.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#ecfdf5;color:#065f46">3</span><span><strong>Confirm name</strong> — verify the card (avatar + student number + campus).</span></div>
        <div class="fig"><div class="verify-card" style="pointer-events:none"><div class="verify-card-top ok"><div class="verify-icon">✓</div><div><div class="verify-title">Verified</div><div class="verify-subtitle">Student verified successfully!</div></div></div><div class="verify-card-body"><div class="verify-profile"><div class="verify-avatar">MA</div><div><div class="verify-name">Maria Santos</div><div class="verify-sub">Is this you?</div></div></div><div class="verify-meta"><span>2023-12345</span><span>Manila Campus</span></div></div></div></div>
        <div class="fig-caption">Fig. 3 — Same confirmation card as New Enrollee.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#ecfdf5;color:#065f46">4</span><span><strong>Proceed to payment</strong> — amount, description, SY/sem, email.</span></div>
        <div class="fig"><div style="border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; font-size:11px; color:var(--muted)">Particulars: Tuition Fee • Back Account • Downpayment • SY 2024-2025, 1st Sem</div></div>
        <div class="fig-caption">Fig. 4 — More particulars available for enrolled students.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#ecfdf5;color:#065f46">5</span><span><strong>Pay via DragonPay</strong> — Online Banking / E-Wallets / Over the Counter.</span></div>
        <div class="fig"><div style="display:grid;gap:8px;place-items:center"><img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" class="dp-logo" style="height:26px"><div style="display:flex; gap:6px; justify-content:center"><span style="background:var(--blue); color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Online Banking</span><span style="background:#10b981; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">E-Wallets</span><span style="background:#f59e0b; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Over the Counter</span></div></div></div>
        <div class="fig-caption">Fig. 5 — Same DragonPay options for all types.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#065f46; color:#fff">6</span><span><strong>Present screenshot to cashier to claim official receipt</strong> — save/screenshot the DragonPay confirmation and present it to the cashier to claim your official receipt.</span></div>
        </div>
    </div>
    <div class="alert info" style="margin-top:14px"><div><strong>Tip:</strong> Use the exact student number on your ID/registration.</div></div>
    <a href="<?= $payments_base ?>enrolled" class="btn btn-primary no-print" style="margin-top:14px; background:linear-gradient(135deg,#0e8a6b,#15b88f); color:#fff; width:100%; justify-content:center; border:none">Pay as Enrolled</a>
  </div>
</div>

<div id="tab-other" class="tab-content">
  <div class="print-only" style="text-align:center; margin-bottom:10px; padding:12px; background:#fffbeb; border:1px solid #fde68a; border-radius:12px">
    <div style="font-weight:800; font-size:14px; color:#92400e; letter-spacing:.04em; text-transform:uppercase">Other Payment Guide</div>
    </div>
  <div class="section" style="margin-top:0">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">1</span><strong>What you need</strong></div>
    <ul style="margin:0 0 14px 28px; color:var(--muted); font-size:14px; line-height:1.6">
      <li>Payer name (for receipt) — add phone if you like (e.g., Juan Dela Cruz - 0912...)</li>
    </ul>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px"><span style="width:28px;height:28px;border-radius:999px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">2</span><strong>Steps with figures</strong></div>
    <div class="steps-fig">
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#fffbeb;color:#92400e; border:1px solid #fde68a">1</span><span><strong>Enter payer name</strong> — as it should appear on receipt.</span></div>
        <div class="fig"><div style="border:1px solid #dfe6f5; border-radius:10px; padding:10px; background:#fbfdff"><div style="font-size:10px; font-weight:800; color:#1d2a44; letter-spacing:.06em; text-transform:uppercase">Payer Name</div><div style="font-size:13px; color:var(--text); font-weight:700; margin-top:4px">Juan Dela Cruz - 09123456789</div></div></div>
        <div class="fig-caption">Fig. 1 — Payer name field. No verification step.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#fffbeb;color:#92400e; border:1px solid #fde68a">2</span><span><strong>Continue to payment</strong> — amount, description, SY/sem, email.</span></div>
        <div class="fig"><div style="border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; font-size:11px; color:var(--muted)">Amount: ₱ 1,500 • Description: Good Moral • SY 2024-2025 • Email: you@email.com</div></div>
        <div class="fig-caption">Fig. 2 — No locator/student number needed — just fill the form.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#fffbeb;color:#92400e; border:1px solid #fde68a">3</span><span><strong>Pay via DragonPay</strong> — Online Banking / E-Wallets / Over the Counter.</span></div>
        <div class="fig"><div style="display:grid;gap:8px;place-items:center"><img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" class="dp-logo" style="height:26px"><div style="display:flex; gap:6px; justify-content:center"><span style="background:var(--blue); color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Online Banking</span><span style="background:#10b981; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">E-Wallets</span><span style="background:#f59e0b; color:#fff; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800">Over the Counter</span></div></div></div>
        <div class="fig-caption">Fig. 3 — Same DragonPay step for everyone.</div>
      </div>
      <div class="step-row">
        <div class="step-text"><span class="step-num" style="background:#92400e; color:#fff">4</span><span><strong>Present screenshot to cashier to claim official receipt</strong> — save/screenshot the DragonPay confirmation and present it to the cashier to claim your official receipt.</span></div>
        </div>
    </div>
    <div class="alert warn" style="margin-top:14px"><div><strong>No verification needed</strong> — for alumni, parents, guests, or any general fee.</div></div>
    <a href="<?= $payments_base ?>other" class="btn btn-primary no-print" style="margin-top:14px; background:linear-gradient(135deg,#b45309,#f59e0b); color:#fff; width:100%; justify-content:center; border:none">Pay Other Fees</a>
  </div>
</div>

<div class="section after-payment" style="margin-top:14px">
  <h3 style="margin:0 0 8px; font-size:16px">After payment</h3>
  <ul style="margin:0 0 0 18px; color:var(--muted); font-size:13.5px; line-height:1.7">
    <li>You’ll be redirected to DragonPay to complete payment.</li>
    <li>Receipt is sent to the email you entered — keep your Transaction ID.</li>
    <li><strong style="color:var(--text)">Present a screenshot of the DragonPay confirmation to the cashier to claim official receipt</strong> — save or screenshot the success page and show it at the cashier window.</li>
    <li>For help: Accounting (02) 779-5310 — have your Transaction ID ready.</li>
  </ul>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-'+btn.dataset.tab).classList.add('active');
    history.replaceState({},'', '#'+btn.dataset.tab);
  });
});
if(location.hash){
  const h=location.hash.replace('#','');
  const b=document.querySelector('.tab-btn[data-tab="'+h+'"]');
  if(b) b.click();
}
document.querySelectorAll('.print-date').forEach(el=>{
  el.textContent = new Date().toLocaleDateString('en-PH', {year:'numeric', month:'long', day:'numeric'});
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
