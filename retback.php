<?php
/**
 * OLP — DragonPay Return (retback) — Standalone
 * Backend parity with uphsledu/online_payment/retback.php & retback_ts.php
 * but fully redesigned UI using OLP design system (header/footer + style.css).
 *
 * DragonPay redirects here via GET:
 *   txnid, refno, status (S/F/P/U/R/K/V/A), message, digest, param1 (amount), param2 (desc)
 * Example: /retback.php?txnid=20-1234-567&refno=PAMKFB89&status=P&message=...&digest=...&param1=5000.00&param2=DESC
 */
$page_title = "Payment Result";
require_once __DIR__ . '/includes/config.php';

// Load DragonPay secret if available (for optional digest verification — never fatal)
if (is_file(__DIR__ . '/app/config/dragonpay.php')) { require_once __DIR__ . '/app/config/dragonpay.php'; }
if (!defined('MERCHANT_PASSWORD') && defined('DRAGONPAY_MERCHANT_PASSWORD')) {
    define('MERCHANT_PASSWORD', DRAGONPAY_MERCHANT_PASSWORD);
}

// ------------------------------------------------------------------
// 1) Normalize inputs (GET is DragonPay's redirect; be tolerant)
//    DragonPay OfflineGateway returns: txnid, refno, status, amount, message,
//    merchantid, param1, param2, signature, signatures, settledate, expiry, procid
//    Older docs used: digest (sha1). New uses signature (hex) + signatures (base64)
// ------------------------------------------------------------------
$txnid  = trim($_GET['txnid']  ?? '');
$refno  = trim($_GET['refno']  ?? '');
$rawStatus = strtoupper(trim($_GET['status'] ?? ''));
$dragonMsg = $_GET['message'] ?? '';          // DragonPay's own message (optional)
$digest    = $_GET['digest']  ?? '';
$signature = $_GET['signature'] ?? '';        // new: hex SHA? (OfflineGateway)
$signatures= $_GET['signatures'] ?? '';       // new: base64 RSA?
$param1    = $_GET['param1']  ?? '';          // amount (forwarded)
$param2    = $_GET['param2']  ?? '';          // description (forwarded)
$amountParam = $_GET['amount'] ?? '';         // new: DragonPay amount (may duplicate param1)
$merchantidParam = $_GET['merchantid'] ?? '';
$settledate = $_GET['settledate'] ?? '';
$expiryParam = $_GET['expiry'] ?? '';
$procidParam = $_GET['procid'] ?? '';
$billerParam = $_GET['billerId'] ?? '';
// Backward compat: some flows POST? also accept POST
if ($txnid === '' && isset($_POST['txnid']))  $txnid = trim($_POST['txnid']);
if ($refno === '' && isset($_POST['refno']))  $refno = trim($_POST['refno']);
if ($rawStatus === '' && isset($_POST['status'])) $rawStatus = strtoupper(trim($_POST['status']));
if ($dragonMsg === '' && isset($_POST['message'])) $dragonMsg = $_POST['message'];
if ($digest === '' && isset($_POST['digest'])) $digest = $_POST['digest'];
if ($signature === '' && isset($_POST['signature'])) $signature = $_POST['signature'];
if ($signatures === '' && isset($_POST['signatures'])) $signatures = $_POST['signatures'];
if ($param1 === '' && isset($_POST['param1'])) $param1 = $_POST['param1'];
if ($param2 === '' && isset($_POST['param2'])) $param2 = $_POST['param2'];
if ($amountParam === '' && isset($_POST['amount'])) $amountParam = $_POST['amount'];
if ($settledate === '' && isset($_POST['settledate'])) $settledate = $_POST['settledate'];

// ------------------------------------------------------------------
// 2) Map status code → human label + UI meta
// ------------------------------------------------------------------
$statusMap = [
    'S' => ['label' => 'Success',    'color' => 'success', 'icon' => 'fa-circle-check',       'desc' => 'Payment confirmed. Your transaction was successful.'],
    'F' => ['label' => 'Failure',    'color' => 'failed',  'icon' => 'fa-circle-xmark',       'desc' => 'Payment failed or was cancelled. No amount was charged.'],
    'P' => ['label' => 'Pending',    'color' => 'pending', 'icon' => 'fa-clock',              'desc' => 'Payment is pending. Please complete it via your chosen channel and check your email.'],
    'U' => ['label' => 'Unknown',    'color' => 'pending', 'icon' => 'fa-circle-question',    'desc' => 'Status unknown. Please check your email or contact support.'],
    'R' => ['label' => 'Refund',     'color' => 'refund',  'icon' => 'fa-rotate-left',        'desc' => 'Payment has been refunded.'],
    'K' => ['label' => 'Chargeback', 'color' => 'failed',  'icon' => 'fa-triangle-exclamation','desc' => 'Chargeback issued. Please contact support.'],
    'V' => ['label' => 'Void',       'color' => 'failed',  'icon' => 'fa-ban',                'desc' => 'Transaction was voided.'],
    'A' => ['label' => 'Authorized', 'color' => 'pending', 'icon' => 'fa-shield-halved',      'desc' => 'Payment authorized — awaiting capture.'],
];
$statusInfo = $statusMap[$rawStatus] ?? ['label' => ($rawStatus ?: 'Unknown'), 'color' => 'pending', 'icon' => 'fa-circle-info', 'desc' => 'We could not determine the final status. Please keep your reference number.'];
$statusLabel = $statusInfo['label'];
$statusColor = $statusInfo['color'];
$statusIcon  = $statusInfo['icon'];

// sanitize amount — DragonPay now sends both param1 (forwarded) and amount (gateway amount); prefer param1, fall back to amount
$amountSource = $param1 !== '' ? $param1 : $amountParam;
$amountRaw = is_numeric($amountSource) ? (float)$amountSource : 0.0;
$amountFormatted = $amountSource !== '' && is_numeric($amountSource) ? number_format((float)$amountSource, 2, '.', ',') : ($amountSource !== '' ? $amountSource : '—');
$description = $param2 !== '' ? $param2 : ($dragonMsg !== '' ? $dragonMsg : '—');
// Strip internal OLP marker for display (if checkout appended " | OLP")
$descriptionDisplay = preg_replace('/\s*\|\s*OLP\s*$/', '', $description);
$descriptionDisplay = preg_replace('/\s*\(OLP\)\s*$/', '', $descriptionDisplay);

// ------------------------------------------------------------------
// 3) Optional digest/signature verification (non-blocking, log only)
//    Legacy: digest = sha1(txnid:refno:status:message:password)
//    New OfflineGateway: signature (hex) + signatures (RSA base64) — not verified here (needs RSA pubkey),
//    just displayed. We still attempt sha1 check if digest or signature looks like sha1.
// ------------------------------------------------------------------
$digestValid = null; // null = skipped, true/false = checked
$digestNote = '';
$sigToCheck = $digest !== '' ? $digest : $signature;
if ($sigToCheck !== '' && defined('MERCHANT_PASSWORD') && $txnid !== '' && $refno !== '' && $rawStatus !== '') {
    // Try sha1 candidates
    $candidates = [];
    $candidates[] = implode(':', [$txnid, $refno, $rawStatus, $dragonMsg, MERCHANT_PASSWORD]);
    $candidates[] = implode(':', [$txnid, $refno, $rawStatus, '', MERCHANT_PASSWORD]);
    if ($param2 !== '') $candidates[] = implode(':', [$txnid, $refno, $rawStatus, $param2, MERCHANT_PASSWORD]);
    // Some newer docs use amount in digest
    if ($amountSource !== '') $candidates[] = implode(':', [$txnid, $refno, $rawStatus, $amountSource, $dragonMsg, MERCHANT_PASSWORD]);
    $matched = false;
    foreach ($candidates as $c) {
        if (hash_equals(strtolower(sha1($c)), strtolower($sigToCheck))) { $matched = true; break; }
        // also try hash_hmac sha256
        if (hash_equals(strtolower(hash_hmac('sha256', $c, MERCHANT_PASSWORD)), strtolower($sigToCheck))) { $matched = true; break; }
    }
    $digestValid = $matched;
    $digestNote = $matched ? 'Digest/signature verified.' : 'Signature not verified — displayed for info only (postback is authoritative).';
    if ($signatures !== '') $digestNote .= ' Signatures present.';
} elseif ($signatures !== '' || $signature !== '') {
    $digestNote = 'Signature provided (OfflineGateway) — showing return as-is. Final status via postback.';
    $digestValid = null;
} elseif ($digest === '' && $signature === '') {
    $digestNote = 'No digest/signature provided — showing DragonPay return as-is. Final status via postback.';
}

// ------------------------------------------------------------------
// 4) Backend — update return_data (parity with uphsledu retback.php)
//    Columns: transdate, refno, status (human label), amount, message (description)
//    WHERE txnid = ?
// ------------------------------------------------------------------
$dbOk = false;
$dbError = '';
if (isset($con) && $con instanceof mysqli && $txnid !== '') {
    // Ensure charset
    @mysqli_set_charset($con, 'utf8mb4');
    $stmt = @mysqli_prepare($con, "UPDATE return_data SET transdate=NOW(), refno=?, status=?, amount=?, message=? WHERE txnid=?");
    if ($stmt) {
        $msgToStore = $param2 !== '' ? $param2 : $dragonMsg;
        // bind: refno(s), status(s), amount(d), message(s), txnid(s)
        mysqli_stmt_bind_param($stmt, "ssdss", $refno, $statusLabel, $amountRaw, $msgToStore, $txnid);
        $exec = @mysqli_stmt_execute($stmt);
        $affected = @mysqli_stmt_affected_rows($stmt);
        @mysqli_stmt_close($stmt);
        if ($exec) {
            $dbOk = true;
            // If no row matched (new txnid never inserted via checkout — e.g., direct test), insert gracefully
            if ($affected === 0) {
                $ins = @mysqli_prepare($con, "INSERT INTO return_data (txnid, refno, status, amount, message, transdate) VALUES (?,?,?,?,?,NOW())");
                if ($ins) {
                    @mysqli_stmt_bind_param($ins, "ssdss", $txnid, $refno, $statusLabel, $amountRaw, $msgToStore);
                    @mysqli_stmt_execute($ins);
                    @mysqli_stmt_close($ins);
                }
            }
        } else {
            $dbError = 'Update failed: ' . mysqli_error($con);
        }
    } else {
        $dbError = 'Prepare failed: ' . mysqli_error($con);
        // Fallback: legacy escaped query (should never be needed)
        $q = sprintf("UPDATE return_data SET transdate=NOW(), refno='%s', status='%s', amount='%s', message='%s' WHERE txnid='%s'",
            mysqli_real_escape_string($con, $refno),
            mysqli_real_escape_string($con, $statusLabel),
            mysqli_real_escape_string($con, (string)$amountRaw),
            mysqli_real_escape_string($con, $param2 !== '' ? $param2 : $dragonMsg),
            mysqli_real_escape_string($con, $txnid)
        );
        if (@mysqli_query($con, $q)) { $dbOk = true; $dbError = ''; }
    }
} elseif ($txnid === '') {
    $dbError = 'Missing txnid — nothing to update.';
} else {
    $dbError = 'DB not available — result shown from DragonPay redirect only.';
}

// For display, also fetch transdate if we can
$transdateDisplay = date('M d, Y h:i A');
if (isset($con) && $con instanceof mysqli && $txnid !== '') {
    $rs = @mysqli_query($con, "SELECT transdate FROM return_data WHERE txnid='".mysqli_real_escape_string($con,$txnid)."' ORDER BY transdate DESC LIMIT 1");
    if ($rs && ($row = mysqli_fetch_assoc($rs)) && !empty($row['transdate'])) {
        $transdateDisplay = date('M d, Y h:i A', strtotime($row['transdate']));
    }
}

require_once __DIR__ . '/includes/header.php';
$payments_base = $GLOBALS['payments_base'] ?? '/';
?>
<style>
/* retback-specific — uses OLP tokens */
.retback-wrap{max-width:860px;margin:0 auto}
.retback-hero{border-radius:20px;overflow:hidden;border:1px solid var(--line);box-shadow:0 18px 40px rgba(15,32,64,.10);background:#fff}
.retback-hero-head{padding:22px 24px;display:flex;align-items:center;gap:16px;color:#fff;position:relative}
.retback-hero-head.success{background:linear-gradient(135deg,#0e9f6e,#0fb981)}
.retback-hero-head.pending{background:linear-gradient(135deg,#d97706,#f59e0b)}
.retback-hero-head.failed{background:linear-gradient(135deg,#dc2626,#ef4444)}
.retback-hero-head.refund{background:linear-gradient(135deg,#1c4da1,#3b82f6)}
.retback-hero-head.unknown{background:linear-gradient(135deg,#475569,#64748b)}
.retback-hero-icon{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.retback-hero-title{font-family:"Plus Jakarta Sans",sans-serif;font-weight:800;font-size:22px;line-height:1.1;margin:0}
.retback-hero-sub{opacity:.92;font-size:13px;margin-top:4px;line-height:1.5}
.retback-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);font-weight:800;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
.retback-body{padding:22px}
.retback-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.retback-grid{grid-template-columns:1fr}}
.retback-field{background:#f8fafc;border:1px solid #eef2f7;border-radius:14px;padding:14px}
.retback-field label{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px}
.retback-field strong{font-size:15px;color:#0f2040;word-break:break-word}
.retback-field .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px}
.desc-box{background:#fff;border:1px dashed #cbd5e1;border-radius:14px;padding:14px}
.desc-box label{font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b}
.desc-box p{margin:6px 0 0;font-weight:700;color:#1e293b;word-break:break-word}
.meta-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.meta-row span{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#334155}
.meta-row span i{color:var(--blue)}
.retback-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.retback-actions .btn{flex:1 1 160px;justify-content:center}
.retback-note{margin-top:14px;padding:12px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;font-size:13px;color:#475569;display:flex;gap:10px}
.retback-note i{margin-top:2px;color:var(--blue)}
.amount-big{font-size:22px;font-weight:900;color:#0f2040;letter-spacing:-.02em}
@media print{
  .no-print{display:none !important}
  .retback-hero{box-shadow:none}
  .retback-hero-head{ -webkit-print-color-adjust:exact; print-color-adjust:exact}
}
</style>

<div class="retback-wrap">
  <!-- breadcrumb -->
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px" class="no-print">
    <a href="<?= $payments_base ?>" class="btn" style="background:#fff;border:1px solid var(--line);padding:8px 14px;font-size:12px">← Back to Home</a>
    <a href="<?= $payments_base ?>instructions" class="btn" style="background:#fff;border:1px solid var(--line);padding:8px 14px;font-size:12px"><i class="fa-solid fa-circle-question"></i> How to Pay</a>
    <span style="margin-left:auto;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">DragonPay • Encrypted</span>
  </div>

  <div class="retback-hero">
    <div class="retback-hero-head <?= htmlspecialchars($statusColor) ?>">
      <div class="retback-hero-icon"><i class="fa-solid <?= htmlspecialchars($statusIcon) ?>"></i></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <h1 class="retback-hero-title"><?= $statusLabel === 'Success' ? 'Payment Successful' : ($statusLabel==='Pending' ? 'Payment Pending' : ($statusLabel==='Failure' ? 'Payment Failed' : 'Payment '.$statusLabel)) ?></h1>
          <span class="retback-badge"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($statusLabel) ?> • <?= htmlspecialchars($rawStatus ?: '—') ?></span>
        </div>
        <div class="retback-hero-sub"><?= htmlspecialchars($statusInfo['desc']) ?> <?= $digestValid===false ? ' — '.htmlspecialchars($digestNote) : '' ?></div>
      </div>
    </div>

    <div class="retback-body">
      <!-- amount highlight for success/pending -->
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
        <div>
          <div style="font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b">Amount</div>
          <div class="amount-big">₱ <?= htmlspecialchars($amountFormatted) ?> <span style="font-size:12px;font-weight:700;color:#64748b">PHP</span></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b">Transaction Date</div>
          <div style="font-weight:700;color:#0f2040;font-size:13px"><i class="fa-regular fa-clock" style="color:var(--blue)"></i> <?= htmlspecialchars($transdateDisplay) ?></div>
        </div>
      </div>

      <div class="retback-grid">
        <div class="retback-field">
          <label><i class="fa-solid fa-hashtag" style="color:var(--blue)"></i> Transaction No.</label>
          <strong class="mono"><?= $txnid !== '' ? htmlspecialchars($txnid) : '—' ?></strong>
        </div>
        <div class="retback-field">
          <label><i class="fa-solid fa-receipt" style="color:var(--blue)"></i> Reference No.</label>
          <strong class="mono"><?= $refno !== '' ? htmlspecialchars($refno) : '— (generated after payment)' ?></strong>
        </div>
        <div class="retback-field">
          <label><i class="fa-solid fa-flag" style="color:var(--blue)"></i> Status</label>
          <strong><?= htmlspecialchars($statusLabel) ?> <?= $rawStatus ? '<span style="color:#64748b;font-weight:700">(' . htmlspecialchars($rawStatus) . ')</span>' : '' ?></strong>
          <div style="font-size:12px;color:#64748b;margin-top:4px"><?= htmlspecialchars($statusInfo['desc']) ?></div>
        </div>
        <div class="retback-field">
          <label><i class="fa-solid fa-envelope" style="color:var(--blue)"></i> Email receipt</label>
          <strong style="font-size:13px">Check the email you provided at checkout</strong>
          <div style="font-size:12px;color:#64748b;margin-top:4px">Instructions for OTC / banking were sent there.</div>
        </div>
      </div>

      <div class="desc-box" style="margin-top:14px">
        <label><i class="fa-solid fa-align-left" style="color:var(--blue)"></i> Description / Particulars</label>
        <p><?= $descriptionDisplay !== '—' ? htmlspecialchars($descriptionDisplay) : '<span style="color:#94a3b8">— No description captured —</span>' ?></p>
        <?php if ($dragonMsg !== '' && $dragonMsg !== $param2): ?>
          <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #e2e8f0;font-size:12px;color:#475569">
            <strong style="color:#334155">DragonPay message:</strong> <?= htmlspecialchars($dragonMsg) ?>
          </div>
        <?php endif; ?>
        <?php if ($merchantidParam !== '' || $settledate !== '' || $procidParam !== ''): ?>
          <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #e2e8f0;font-size:11px;color:#64748b;display:grid;gap:4px">
            <?php if ($merchantidParam): ?><div><strong>Merchant:</strong> <?= htmlspecialchars($merchantidParam) ?> <?php if ($procidParam): ?>• <strong>Proc:</strong> <?= htmlspecialchars($procidParam) ?><?php endif; ?></div><?php endif; ?>
            <?php if ($settledate): ?><div><strong>Settle:</strong> <?= htmlspecialchars($settledate) ?> <?php if ($expiryParam): ?>• <strong>Expiry:</strong> <?= htmlspecialchars($expiryParam) ?><?php endif; ?></div><?php endif; ?>
            <?php if ($amountParam !== '' && $amountParam !== $param1): ?><div><strong>Gateway amount:</strong> <?= htmlspecialchars($amountParam) ?> • <strong>Forwarded param1:</strong> <?= htmlspecialchars($param1) ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($signature !== '' || $signatures !== '' || $digest !== ''): ?>
          <div style="margin-top:8px;padding:8px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;font-size:10px;color:#64748b;word-break:break-all">
            <?php if ($signature): ?><div><strong>Signature:</strong> <?= htmlspecialchars($signature) ?></div><?php endif; ?>
            <?php if ($digest && $digest !== $signature): ?><div><strong>Digest:</strong> <?= htmlspecialchars($digest) ?></div><?php endif; ?>
            <?php if ($signatures): ?><div style="margin-top:4px"><strong>Signatures (RSA):</strong> <?= htmlspecialchars(substr($signatures,0,120)) ?><?= strlen($signatures)>120 ? '...' : '' ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="meta-row">
          <span><i class="fa-solid fa-fingerprint"></i> TXN: <?= $txnid ? htmlspecialchars($txnid) : '—' ?></span>
          <?php if ($refno): ?><span><i class="fa-solid fa-barcode"></i> REF: <?= htmlspecialchars($refno) ?></span><?php endif; ?>
          <span><i class="fa-solid fa-shield"></i> <?= $digestValid===true ? 'Digest OK' : ($digestValid===false ? 'Digest mismatch' : 'Return view') ?></span>
          <?php if ($settledate): ?><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('M d, h:i A', strtotime($settledate))) ?></span><?php endif; ?>
        </div>
      </div>

      <?php if ($statusColor==='pending'): ?>
        <div class="retback-note" style="border-color:#fde68a;background:#fffbeb">
          <i class="fa-solid fa-circle-info" style="color:#d97706"></i>
          <div>
            <strong style="color:#92400e">What to do next (Pending):</strong><br>
            If you chose <strong>Banks / E-Wallets / OTC (Bayad Center, Cebuana, etc.)</strong>, follow the instructions sent to your email to complete the deposit. Your <strong>Reference No. <?= $refno ? htmlspecialchars($refno) : '(see above once generated)' ?></strong> is required at the counter. Final confirmation comes via DragonPay <em>postback</em> — this page may show <em>Pending</em> until then.
          </div>
        </div>
      <?php elseif ($statusColor==='success'): ?>
        <div class="retback-note" style="border-color:#a7f3d0;background:#ecfdf5">
          <i class="fa-solid fa-circle-check" style="color:#0e9f6e"></i>
          <div>
            <strong style="color:#065f46">You're all set.</strong> Keep your Reference No. <strong><?= htmlspecialchars($refno) ?></strong> for your records. A receipt was also sent to your email. You may print this page as proof.
          </div>
        </div>
      <?php elseif ($statusColor==='failed'): ?>
        <div class="retback-note" style="border-color:#fecaca;background:#fef2f2">
          <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626"></i>
          <div>
            <strong style="color:#7f1d1d">Payment not completed.</strong> You were not charged. You may try again from <a href="<?= $payments_base ?>" style="font-weight:800">Payments Hub</a> or choose a different channel.
          </div>
        </div>
      <?php endif; ?>

      <?php if ($dbError): ?>
        <div class="retback-note" style="border-color:#fecaca;background:#fef2f2">
          <i class="fa-solid fa-database" style="color:#dc2626"></i>
          <div style="font-size:12px"><strong>DB note:</strong> <?= htmlspecialchars($dbError) ?> — display still reflects DragonPay redirect; final record will be updated via postback.</div>
        </div>
      <?php endif; ?>

      <div class="retback-actions no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()" style="background:var(--blue);color:#fff;border-color:var(--blue)"><i class="fa-solid fa-print"></i> Print Receipt</button>
        <a href="<?= $payments_base ?>" class="btn" style="background:#fff;border:1px solid var(--line)"><i class="fa-solid fa-house"></i> Back to Payments Hub</a>
        <a href="<?= $payments_base ?>instructions" class="btn" style="background:#0f2040;color:#fff;border-color:#0f2040"><i class="fa-solid fa-circle-question"></i> Need Help?</a>
      </div>

      <div style="margin-top:12px;font-size:11px;color:#94a3b8;text-align:center" class="no-print">
        Final authoritative status is via DragonPay <em>postback</em> (server-to-server). This return page is for your receipt — keep your Transaction No. <strong><?= $txnid ? htmlspecialchars($txnid) : '—' ?></strong>.
        <?php if ($digestNote): ?><br><?= htmlspecialchars($digestNote) ?><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- help card -->
  <div style="margin-top:14px;background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;gap:12px;align-items:flex-start" class="no-print">
    <div style="width:36px;height:36px;border-radius:999px;background:#eef2ff;color:var(--blue);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-headset"></i></div>
    <div style="font-size:13px;color:#475569;line-height:1.6">
      <strong style="color:#0f2040">Need to confirm?</strong> Save your <strong>TXN <?= $txnid ? htmlspecialchars($txnid) : '—' ?></strong> and <strong>REF <?= $refno ? htmlspecialchars($refno) : '—' ?></strong>. Show this receipt + your email instructions at the cashier/accounting window if needed. For issues, contact UPHSL Accounting with these numbers.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
