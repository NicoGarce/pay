<?php
$page_title = "Checkout - Secure Payment";
require_once __DIR__ . '/includes/config.php';

// DragonPay credentials — MUST be in ignored config (app/config/dragonpay.php), never here
// Create that file from dragonpay.example.php — it is .gitignored so secrets never commit
if (is_file(__DIR__ . '/app/config/dragonpay.php')) { require_once __DIR__ . '/app/config/dragonpay.php'; }
if (!defined('MERCHANT_ID') && defined('DRAGONPAY_MERCHANT_ID')) define('MERCHANT_ID', DRAGONPAY_MERCHANT_ID);
if (!defined('MERCHANT_PASSWORD') && defined('DRAGONPAY_MERCHANT_PASSWORD')) define('MERCHANT_PASSWORD', DRAGONPAY_MERCHANT_PASSWORD);
if (!defined('MERCHANT_ID') || !defined('MERCHANT_PASSWORD')) {
    http_response_code(500);
    die("DragonPay not configured — create app/config/dragonpay.php from dragonpay.example.php");
}

define('ENV_TEST', 0);

define('ENV_LIVE', 1);

$environment = (defined('DRAGONPAY_ENV') && DRAGONPAY_ENV === 'test') ? ENV_TEST : ENV_LIVE;

$payee = $_GET['payee'] ?? ($_GET['payee_name'] ?? '');
$transid = $_GET['transid'] ?? '';
$locno = $_GET['locno'] ?? '';
$studentno = $_GET['studentno'] ?? '';
$type = $_GET['type'] ?? ( !empty($locno) ? 'new' : (!empty($studentno) ? 'enrolled' : 'other') );
$campus = $_GET['campus'] ?? '';

if (isset($_GET['payee']) && $transid === '') {
    // generate fallback transid
    $transid = ($type==='new' ? ($locno?:'GEN') : ($type==='enrolled' ? ($studentno?:'GEN') : 'GEN')) . "_" . date("HismdY");
}

// handle dragonpay submission
$errors = [];
$is_link = false;
$parameters = [
    'merchantid' => MERCHANT_ID,
    'txnid' => $transid,
    'amount' => 0,
    'ccy' => 'PHP',
    'description' => '',
    'email' => '',
];
$fields = [
    'txnid' => ['label'=>'Transaction ID','type'=>'text'],
    'amount' => ['label'=>'Amount','type'=>'number'],
    'description' => ['label'=>'Payment Description','type'=>'text'],
    'email' => ['label'=>'Email','type'=>'email'],
];

if (isset($_POST['submit'])) {
    // count txnid similar to original
    $tid = $transid;
    // recount
    $sqlCnt = "SELECT (COUNT(*)+1) as total FROM return_data WHERE txnid LIKE '%". mysqli_real_escape_string($con, $_GET['transid'] ?? $transid) ."%'";
    $resCnt = @mysqli_query($con, $sqlCnt);
    $total=0;
    if ($resCnt) while($r=mysqli_fetch_array($resCnt)){ $total+=$r["total"]; }
    if (isset($_GET["transid"])) {
        $tid = $_GET["transid"];
        if (trim($_GET["payee"] ?? '')!="") { $tid=$tid."_".$total; }
    }
    $parameters['txnid'] = $tid;

    foreach (['txnid','amount','description','email'] as $k) {
        if (isset($_POST[$k])) $parameters[$k] = trim($_POST[$k]);
    }
    // fallback description construction if empty (same as payment.php)
    if (empty($parameters['description'])) {
        $payee_name = trim($_POST['payee_name'] ?? $payee);
        $desc_others = trim($_POST['desc_others'] ?? '');
        $desc_select = trim($_POST['descselect'] ?? '');
        $syfrom = trim($_POST['syfrom'] ?? '');
        $sem = trim($_POST['sem'] ?? '');
        $loc = trim($_POST['locno'] ?? $locno);
        $choice = $desc_others !== '' ? $desc_others : $desc_select;
        if ($choice !== '') {
            $txt = strtoupper($payee_name);
            if ($loc !== '') $txt .= ' ('.$loc.')';
            if (!empty($studentno)) $txt .= ' ('.$studentno.')';
            $txt .= ' >> ' . $choice;
            if ($syfrom!=='') $txt .= ', ' . $syfrom;
            if ($sem!=='') $txt .= ', ' . $sem;
            $parameters['description'] = $txt;
        } elseif ($type==='new') {
            $payeeU = strtoupper($payee_name);
            $txt = $payeeU ? $payeeU . ($loc!==''?" (".$loc.")":'') . ' >> DOWNPAYMENT' : 'DOWNPAYMENT';
            if ($syfrom!=='') $txt .= ', ' . $syfrom;
            if ($sem!=='') $txt .= ', ' . $sem;
            $parameters['description'] = $txt;
        }
    }

    if (!is_numeric($parameters['amount'])) $errors[] = 'Amount should be a number.';
    elseif ($parameters['amount'] <= 0) $errors[] = 'Amount should be greater than 0.';
    elseif (empty($parameters['email']) || !filter_var($parameters['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    if (empty($errors)) {
        $parameters['amount'] = number_format($parameters['amount'], 2, '.', '');
        @mysqli_query($con, "INSERT INTO return_data (txnid) VALUES('".mysqli_real_escape_string($con,$parameters['txnid'])."')");
        $parameters['key'] = MERCHANT_PASSWORD;
        $digest_string = implode(':', $parameters);
        unset($parameters['key']);
        $parameters['digest'] = sha1($digest_string);
        $url = ($environment==ENV_TEST) ? 'http://test.dragonpay.ph/Pay.aspx?' : 'https://gw.dragonpay.ph/Pay.aspx?';
        $params = "&param1=".$parameters['amount']."&param2=".$parameters['description'];
        $url .= http_build_query($parameters,'','&').$params;
        header("Location: $url"); exit;
    }
}

// Determine particular options per type
$particulars = [];
if ($type==='new') {
    $particulars = ['DOWNPAYMENT','RESERVATION FEE (Basic Education)','RESERVATION FEE (College)'];
} elseif ($type==='enrolled') {
    $particulars = ['DOWNPAYMENT','TUITION FEE','BACK ACCOUNT','RESERVATION FEE (Basic Education)','RESERVATION FEE (College)','ACTIVITY FEE','ADDING/DROPPING FEE','ALUMNI ASSOCIATION MEMBERSIP','AUTHENTICATION','BAR UNIFORM','BASIC OCCUPATIONAL SAFETY & HEALTH','BASIC TRAINING','CAV','CERTIFICATE OF BASIC TRAINING','CERTIFICATION','CHANGE','CHEF UNIFORM','CLASS PICTURE','COMPLETION FORM','COPY OF GRADES','COUNCIL FEE','DIPLOMA','GRADUATION FEE','GRADUATION PIN','RESEARCH FEE','TRANSCRIPT OF RECORDS','USC-PE UNIFORM','YEARBOOK'];
} else {
    $particulars = ['DOWNPAYMENT','TUITION FEE','BACK ACCOUNT','RESERVATION FEE (Basic Education)','RESERVATION FEE (College)','ACTIVITY FEE','ADDING/DROPPING FEE','AUTHENTICATION','CAV','CERTIFICATION','COPY OF GRADES','DIPLOMA','GOOD MORAL','TRANSCRIPT OF RECORDS','YEARBOOK','CUSTOM - Type Below'];
}

require_once __DIR__ . '/includes/header.php';
?>
<div style="max-width:900px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
    <a href="<?= $payments_base ?><?= $type==='new'?'new-enrollee':($type==='enrolled'?'enrolled':'other') ?>" class="btn" style="background:#fff;border:1px solid var(--line)"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <span style="font-weight:800;color:var(--blue)"><i class="fa-solid fa-lock"></i> Secure Checkout</span>
    <span style="margin-left:auto;font-size:12px;color:var(--muted)">DragonPay • Encrypted</span>
  </div>

  <div style="display:grid;grid-template-columns:1.1fr .9fr;gap:18px" class="checkout-grid">
    <div class="form-card">
      <div class="form-head">
        <i class="fa-solid fa-credit-card"></i>
        <div>
          <div style="font-weight:800">Complete Your Payment</div>
          <div style="opacity:.9;font-size:12px"><?= $type==='new'?'New Enrollee • Locator':($type==='enrolled'?'Enrolled • Student No':'Other / General') ?> â€” <?= htmlspecialchars($payee) ?></div>
        </div>
      </div>
      <div class="form-body">
        <?php if(!empty($errors)): ?><div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('htmlspecialchars',$errors)) ?></div></div><?php endif; ?>

        <form method="post" id="payForm">
          <div class="row">
            <div class="field"><label>Transaction ID</label><input type="text" name="txnid" value="<?= htmlspecialchars($parameters['txnid']) ?>" readonly style="background:#f1f5f9"></div>
            <div class="field"><label>Amount (PHP) <span style="color:var(--err)">*</span></label><input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" placeholder="e.g., 5000.00" required></div>
          </div>

          <div class="field">
            <label>Particulars <span style="color:var(--err)">*</span></label>
            <select name="descselect" id="descselect" required>
              <option value="">Select particular...</option>
              <?php foreach($particulars as $p): ?><option value="<?= htmlspecialchars($p) ?>" <?= (($_POST['descselect']??'')===$p?'selected':'') ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
            </select>
            <input type="text" name="desc_others" id="desc_others" placeholder="If not listed, type custom description here" style="margin-top:8px">
            <input type="hidden" name="description" id="description">
          </div>

          <div class="row">
            <div class="field"><label>For School Year</label>
              <select name="syfrom" id="syfrom">
                <?php $d=date("Y"); while($d>=1980){$c=$d+1; echo '<option value="'.$d."-".$c.'">'.$d."-".$c.'</option>'; $d--;} ?>
              </select>
            </div>
            <div class="field"><label>For Semester</label>
              <select name="sem" id="sem">
                <option value="1st Sem">1st Sem</option>
                <option value="2nd Sem">2nd Sem</option>
                <option value="Summer">Summer</option>
                <option value="Regular Semester ( for BED )">Regular Semester ( for BED )</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="field"><label>Student / Payer Name <span style="color:var(--err)">*</span></label><input type="text" name="payee_name" id="payee_name" value="<?= htmlspecialchars($payee) ?>" required></div>
            <div class="field"><label><?= $type==='new'?'Locator Number':($type==='enrolled'?'Student Number':'Reference (optional)') ?></label><input type="text" name="locno" id="locno" value="<?= htmlspecialchars($locno ?: $studentno) ?>" <?= $type!=='other'?'readonly style="background:#eef2ff;color:var(--blue);font-weight:800;text-align:center"':'' ?> placeholder="<?= $type==='other'?'Optional' : '' ?>"></div>
          </div>

          <div class="field"><label>Email Address <span style="color:var(--err)">*</span></label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="receipt will be sent here" required></div>

          <button type="submit" name="submit" class="btn btn-primary" style="width:100%;padding:16px;font-size:16px" onclick="buildDesc()"><i class="fa-solid fa-lock"></i> Pay Now via DragonPay</button>
          <small style="color:var(--muted);text-align:center;display:block;margin-top:6px">You will be redirected to DragonPay (Online Banking, E-Wallets, and Over the Counter).</small>
        </form>
      </div>
    </div>

    <div style="display:grid;gap:14px;align-content:start">
      <div class="pay-summary">
        <div style="display:flex;align-items:center;gap:10px"><i class="fa-solid fa-receipt"></i><strong>Payment Summary</strong><span style="margin-left:auto;background:rgba(255,255,255,.14);padding:4px 8px;border-radius:999px;font-size:11px"><?= strtoupper($type) ?></span></div>
        <div class="row" style="font-size:14px"><span>Payee</span><strong><?= htmlspecialchars($payee) ?></strong></div>
        <div class="row" style="font-size:14px"><span>ID</span><strong><?= htmlspecialchars($locno ?: $studentno ?: 'General') ?></strong></div>
        <div class="row" style="font-size:14px"><span>Gateway</span><strong>DragonPay</strong></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">

          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">Online Banking</span>

          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">E-Wallets</span>

          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">Over the Counter</span>

        </div>
      </div>
      <div class="section" style="margin:0">
        <h4 style="margin:0 0 8px"><i class="fa-solid fa-circle-info" style="color:var(--blue)"></i> Need help?</h4>
        <p style="color:var(--muted);font-size:13px">Contact UPHS Accounting: (02) 779-5310 • (049) 554-5150. Keep your Transaction ID for reference.</p>
      </div>
    </div>
  </div>
</div>

<script>
function buildDesc(){
  const payee=document.getElementById('payee_name')?.value?.toUpperCase()||'';
  const loc=document.getElementById('locno')?.value||'';
  const sel=document.getElementById('descselect')?.value||'';
  const other=document.getElementById('desc_others')?.value?.trim()||'';
  const sy=document.getElementById('syfrom')?.value||'';
  const sem=document.getElementById('sem')?.value||'';
  const choice = other!==''? other : sel;
  let txt='';
  if(choice!==''){
    txt = payee;
    if(loc) txt += ' ('+loc+')';
    txt += ' >> ' + choice;
    if(sy) txt += ', ' + sy;
    if(sem) txt += ', ' + sem;
  }
  const desc=document.getElementById('description');
  if(desc) desc.value=txt;
}
document.getElementById('payForm')?.addEventListener('submit', buildDesc);
</script>

<style>
@media(max-width:900px){ .checkout-grid{grid-template-columns:1fr !important} }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


