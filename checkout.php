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
    // fallback description construction — clean, labeled, pipe-separated (better than "ABC ABC - 097... (ID) >> DOWNPAYMENT, SY, Sem")
    if (empty($parameters['description'])) {
        $payee_raw = trim($_POST['payee_name'] ?? $payee);
        $payee_upper = strtoupper($payee_raw);
        $contact_post = trim($_POST['contact_no'] ?? '');
        $desc_others = trim($_POST['desc_others'] ?? '');
        $desc_select = trim($_POST['descselect'] ?? '');
        $syfrom = trim($_POST['syfrom'] ?? '');
        $sem = trim($_POST['sem'] ?? '');
        $loc = trim($_POST['locno'] ?? $locno);
        $choice = $desc_others !== '' ? $desc_others : $desc_select;
        if($choice==='') $choice = ($type==='new' ? 'DOWNPAYMENT' : '');
        if ($choice !== '') {
            $id = '';
            if ($loc !== '' && !empty($studentno) && $loc !== $studentno) $id = $loc . '/' . $studentno;
            elseif ($loc !== '') $id = $loc;
            elseif (!empty($studentno)) $id = $studentno;
            $parts = [];
            if ($payee_upper !== '') $parts[] = $payee_upper;
            if ($id !== '') $parts[] = 'ID:' . $id;
            if ($contact_post !== '') $parts[] = 'Contact:' . $contact_post;
            $parts[] = $choice;
            $syPart = trim($syfrom . ($syfrom && $sem ? ' ' : '') . $sem);
            if ($syPart !== '') $parts[] = $syPart;
            $parameters['description'] = implode(' | ', $parts);
        }
    }

    if (!is_numeric($parameters['amount'])) $errors[] = 'Amount should be a number.';
    elseif ($parameters['amount'] <= 0) $errors[] = 'Amount should be greater than 0.';
    elseif (empty($parameters['email']) || !filter_var($parameters['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    if (empty($errors)) {
        $parameters['amount'] = number_format($parameters['amount'], 2, '.', '');
        // Tag OLP origin in description so uphsl.edu.ph retback can proxy to pay.uphsl.edu.ph/retback
        // (stripped for display in olp/retback.php). Also ensures DragonPay offline return can be routed.
        if (strpos($parameters['description'], 'OLP') === false) {
            $parameters['description'] .= ' | OLP';
        }
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
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
    <a href="<?= $payments_base ?><?= $type==='new'?'new-enrollee':($type==='enrolled'?'enrolled':'other') ?>" class="btn" style="background:#fff;border:1px solid var(--line); padding:7px 12px; font-size:12px">Back</a>
    <span style="font-weight:600;color:var(--blue); font-size:12px">Secure Checkout</span>
  </div>

  <div style="display:grid;grid-template-columns:1.1fr .9fr;gap:18px" class="checkout-grid">
    <div class="form-card">
      <div class="form-head" style="justify-content:space-between">
        <div style="display:flex;align-items:center;gap:14px">
          <i class="fa-solid fa-credit-card"></i>
          <div>
            <div style="font-weight:800">Complete Your Payment</div>
            <div style="opacity:.9;font-size:12px"><?= $type==='new'?'New Enrollee • Locator':($type==='enrolled'?'Enrolled • Student No':'Other / General') ?> — <?= htmlspecialchars($payee) ?></div>
          </div>
        </div>
        <img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" class="dp-logo dp-logo--dark" style="height:28px;flex-shrink:0" title="Secured by DragonPay">
      </div>
      <div class="form-body">
        <?php if(!empty($errors)): ?><div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i><div><?= implode('<br>', array_map('htmlspecialchars',$errors)) ?></div></div><?php endif; ?>

        <form method="post" id="payForm">
          <div class="row">
            <div class="field"><label>Transaction ID</label><input type="text" name="txnid" value="<?= htmlspecialchars($parameters['txnid']) ?>" readonly style="background:#f1f5f9"></div>
            <div class="field" id="field-amount"><label>Amount (PHP) <span style="color:var(--err)">*</span></label><input type="number" step="0.01" name="amount" id="amount" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" placeholder="e.g., 5000.00" required></div>
          </div>

          <div class="field" id="field-particulars">
            <label>Particulars <span style="color:var(--err)">*</span></label>
            <select name="descselect" id="descselect" required>
              <option value="">Select particular...</option>
              <?php foreach($particulars as $p): ?><option value="<?= htmlspecialchars($p) ?>" <?= (($_POST['descselect']??'')===$p?'selected':'') ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
            </select>
            <input type="text" name="desc_others" id="desc_others" placeholder="If not listed, type custom description here" style="margin-top:8px">
            <input type="hidden" name="description" id="description">
          </div>

          <div class="row">
            <div class="field" id="field-sy"><label>For School Year <span style="color:var(--err)">*</span></label>
              <select name="syfrom" id="syfrom" required>
                <option value="">Select school year...</option>
                <?php $d=date("Y"); while($d>=1980){$c=$d+1; echo '<option value="'.$d."-".$c.'">'.$d."-".$c.'</option>'; $d--;} ?>
              </select>
            </div>
            <div class="field" id="field-sem"><label>For Semester <span style="color:var(--err)">*</span></label>
              <select name="sem" id="sem" required>
                <option value="">Select semester...</option>
                <option value="1st Sem">1st Sem</option>
                <option value="2nd Sem">2nd Sem</option>
                <option value="Summer">Summer</option>
                <option value="Regular Semester ( for BED )">Regular Semester ( for BED )</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="field" id="field-payee"><label>Student / Payer Name <span style="color:var(--err)">*</span></label><input type="text" name="payee_name" id="payee_name" value="<?= htmlspecialchars($payee) ?>" required></div>
            <div class="field"><label><?= $type==='new'?'Locator Number':($type==='enrolled'?'Student Number':'Reference (optional)') ?></label><input type="text" name="locno" id="locno" value="<?= htmlspecialchars($locno ?: $studentno) ?>" <?= $type!=='other'?'readonly style="background:#eef2ff;color:var(--blue);font-weight:800;text-align:center"':'' ?> placeholder="<?= $type==='other'?'Optional' : '' ?>"></div>
          </div>

          <div class="field" id="field-contact"><label>Contact Number <small style="font-weight:600;color:var(--muted)">(will be appended to payer name)</small></label><input type="text" name="contact_no" id="contact_no" value="<?= htmlspecialchars($_POST['contact_no'] ?? '') ?>" placeholder="e.g., 09123456789" maxlength="20" inputmode="numeric" autocomplete="tel"></div>

          <div class="field" id="field-email"><label>Email Address <span style="color:var(--err)">*</span></label><input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="receipt will be sent here" required></div>

          <div id="reviewGate" style="display:none;background:#fffbe6;border:1px solid #fde68a;border-radius:12px;padding:12px;margin-bottom:10px">
            <div style="font-weight:800;font-size:13px;color:#92400e;margin-bottom:6px"><i class="fa-solid fa-eye"></i> Review your details</div>
            <div style="font-size:12px;color:#475569;margin-bottom:8px">Please check the <strong>Payment Summary</strong> on the right — amount, particulars, school year, semester, payer name and email must be correct before proceeding.</div>
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer"><input type="checkbox" id="confirmReview" style="margin-top:3px"> I have reviewed my payment details and confirm they are correct.</label>
          </div>
          <div id="payBtnWrap" style="display:none">
            <button type="submit" name="submit" class="btn btn-primary" style="width:100%;padding:14px 16px;font-size:15px;gap:10px" onclick="buildDesc()"><i class="fa-solid fa-lock"></i> Pay Now via <img src="<?= $payments_base ?>assets/dragonpay-xendit-logo-removebg-preview.png" alt="DragonPay" style="height:22px;background:#fff;padding:2px 6px;border-radius:6px;vertical-align:middle;margin-left:2px"></button>
            <small style="color:var(--muted);text-align:center;display:block;margin-top:6px">You will be redirected to DragonPay (Online Banking, E-Wallets, and Over the Counter).</small>
          </div>
          <div id="payBtnHint" style="text-align:center;font-size:12px;color:var(--muted);padding:10px;border:1px dashed var(--line);border-radius:12px;background:#f8fafc"><i class="fa-solid fa-circle-info"></i> Fill all required fields to review and continue.</div>
        </form>
      </div>
    </div>

    <div style="display:grid;gap:14px;align-content:start">
      <div class="pay-summary" id="paySummary">
        <div style="display:flex;align-items:center;gap:10px"><i class="fa-solid fa-receipt"></i><strong>Payment Summary</strong><span style="margin-left:auto;background:rgba(255,255,255,.18);padding:4px 8px;border-radius:999px;font-size:11px"><?= strtoupper($type) ?></span></div>
        <div class="row" style="font-size:14px"><span>Payee</span><strong id="sum-payee"><?= htmlspecialchars($payee) ?></strong></div>
        <div class="row" style="font-size:14px"><span>ID</span><strong id="sum-id"><?= htmlspecialchars($locno ?: $studentno ?: 'General') ?></strong></div>
        <div class="row" style="font-size:14px"><span>Transaction ID</span><strong id="sum-txnid" style="font-family:ui-monospace,monospace;font-size:12px;word-break:break-all"><?= htmlspecialchars($parameters['txnid']) ?></strong></div>
        <div class="row" style="font-size:14px"><span>Amount</span><strong id="sum-amount" style="color:#fff">—</strong></div>
        <div class="row" style="font-size:14px"><span>Particulars</span><strong id="sum-particulars">—</strong></div>
        <div class="row" style="font-size:12px"><span>Description</span><strong id="sum-desc" style="font-size:11px;word-break:break-word;font-weight:600">—</strong></div>
        <div class="row" style="font-size:14px"><span>Email</span><strong id="sum-email" style="font-size:12px;word-break:break-all">—</strong></div>
        <div class="row" style="font-size:14px"><span>School Year / Sem</span><strong id="sum-sy" style="font-size:12px">—</strong></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">Online Banking</span>
          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">E-Wallets</span>
          <span style="background:#fff;color:var(--blue);padding:6px 8px;border-radius:999px;font-size:11px;font-weight:800">Over the Counter</span>
        </div>
        <div id="sum-hint" style="font-size:11px;opacity:.85;background:rgba(255,255,255,.14);border:1px dashed rgba(255,255,255,.28);padding:8px 10px;border-radius:10px"><i class="fa-solid fa-circle-info"></i> Summary updates as you type — review before paying.</div>
      </div>
    </div>
  </div>
</div>

<script>
function getPayeeWithContact(){
  const raw=document.getElementById('payee_name')?.value?.trim()||'';
  const contact=document.getElementById('contact_no')?.value?.trim()||'';
  if(contact && raw && raw.indexOf(contact)===-1) return raw + ' - ' + contact;
  if(contact && !raw) return contact;
  return raw;
}
function buildDesc(){
  const payeeRaw=document.getElementById('payee_name')?.value?.trim()||'';
  const payeeUpper = payeeRaw ? payeeRaw.toUpperCase() : '';
  const contact=document.getElementById('contact_no')?.value?.trim()||'';
  const loc=document.getElementById('locno')?.value?.trim()||'';
  // also consider studentno from URL if loc empty (enrolled flow)
  const urlParams = new URLSearchParams(window.location.search);
  const studentno = (urlParams.get('studentno')||'').trim();
  const sel=document.getElementById('descselect')?.value||'';
  const other=document.getElementById('desc_others')?.value?.trim()||'';
  const sy=document.getElementById('syfrom')?.value||'';
  const sem=document.getElementById('sem')?.value||'';
  let choice = other!==''? other : sel;
  if(!choice){
    // fallback for new enrollee when nothing selected
    const typeParam = urlParams.get('type')||'';
    if(typeParam==='new') choice='DOWNPAYMENT';
  }
  let txt='';
  if(choice!==''){
    let id='';
    if(loc && studentno && loc!==studentno) id = loc + '/' + studentno;
    else if(loc) id = loc;
    else if(studentno) id = studentno;
    const parts=[];
    if(payeeUpper) parts.push(payeeUpper);
    if(id) parts.push('ID:'+id);
    if(contact) parts.push('Contact:'+contact);
    parts.push(choice);
    const syPart = [sy,sem].filter(Boolean).join(' ');
    if(syPart) parts.push(syPart);
    txt = parts.join(' | ');
  }
  const desc=document.getElementById('description');
  if(desc) desc.value=txt;
  return txt;
}
document.getElementById('payForm')?.addEventListener('submit', function(){ buildDesc(); });

// Dynamic summary + review gate (Pay Now appears only after review)
(function(){
  function fmtAmount(v){
    const n=parseFloat(String(v).replace(/,/g,''));
    if(isNaN(n) || n<=0) return '—';
    return '₱ ' + n.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  }
  function updateSummary(){
    const payee=getPayeeWithContact()||'—';
    const amt=document.getElementById('amount')?.value?.trim()||'';
    const sel=document.getElementById('descselect')?.value?.trim()||'';
    const other=document.getElementById('desc_others')?.value?.trim()||'';
    const choice = other!==''? other : sel;
    const email=document.getElementById('email')?.value?.trim()||'—';
    const sy=document.getElementById('syfrom')?.value||'';
    const sem=document.getElementById('sem')?.value||'';
    const txn=document.querySelector('input[name="txnid"]')?.value||'—';
    const desc=buildDesc();
    const set=(id,txt)=>{ const e=document.getElementById(id); if(e) e.textContent=txt||'—'; };
    set('sum-payee', payee);
    set('sum-txnid', txn);
    set('sum-amount', amt ? fmtAmount(amt) : '—');
    set('sum-particulars', choice||'—');
    set('sum-desc', desc||'—');
    set('sum-email', email);
    set('sum-sy', (sy||sem) ? (sy + (sy&&sem?' • ':'') + sem) : '—');
  }
  function isAllRequiredFilled(){
    const a=document.getElementById('amount')?.value?.trim()||'';
    const n=parseFloat(a); const amountOk = a!=='' && !isNaN(n) && n>0;
    const sel=document.getElementById('descselect')?.value?.trim()||'';
    const other=document.getElementById('desc_others')?.value?.trim()||'';
    const partOk = sel!=='' || other!=='';
    const syOk = document.getElementById('syfrom')?.value?.trim()!==''; 
    const semOk = document.getElementById('sem')?.value?.trim()!==''; 
    const payee=document.getElementById('payee_name')?.value?.trim()||'';
    const emailEl=document.getElementById('email');
    const emailOk = emailEl && emailEl.value.trim()!=='' && emailEl.checkValidity();
    return amountOk && partOk && syOk && semOk && payee!=='' && emailOk;
  }
  let gateWasVisible=false;
  function updatePayGate(){
    const allOk = isAllRequiredFilled();
    const gate=document.getElementById('reviewGate');
    const wrap=document.getElementById('payBtnWrap');
    const hint=document.getElementById('payBtnHint');
    const chk=document.getElementById('confirmReview');
    const confirmed = !!(chk && chk.checked);
    if(!gate || !wrap || !hint) return;
    if(allOk){
      const willShow = gate.style.display==='none' || gate.style.display==='';
      gate.style.display='block';
      hint.style.display='none';
      if(willShow && !gateWasVisible) gate.scrollIntoView({behavior:'smooth',block:'nearest'});
      gateWasVisible=true;
      if(confirmed){
        wrap.style.display='block';
        document.getElementById('paySummary')?.classList.add('pay-summary--ready');
      } else {
        wrap.style.display='none';
      }
    } else {
      gate.style.display='none';
      wrap.style.display='none';
      hint.style.display='block';
      gateWasVisible=false;
      if(chk) chk.checked=false;
    }
  }
  function attachSummary(){
    const ids=['amount','descselect','desc_others','payee_name','contact_no','email','syfrom','sem','locno'];
    ids.forEach(id=>{
      const el=document.getElementById(id);
      if(!el) return;
      el.addEventListener('input', ()=>{ updateSummary(); updatePayGate(); });
      el.addEventListener('change', ()=>{ updateSummary(); updatePayGate(); });
    });
    const chk=document.getElementById('confirmReview');
    if(chk) chk.addEventListener('change', updatePayGate);
    updateSummary(); updatePayGate();
    setTimeout(()=>{ updateSummary(); updatePayGate(); },300);
    setTimeout(()=>{ updateSummary(); updatePayGate(); },1000);
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', attachSummary);
  else attachSummary();
  window._updateSummary = updateSummary;
  window._updatePayGate = updatePayGate;
})();


// Sequential field guidance — Amount → Particulars → School Year → Semester
(function(){
  const order = [
    { fieldId:'field-amount', inputs:['amount'], label:'Amount' },
    { fieldId:'field-particulars', inputs:['descselect','desc_others'], label:'Particulars', isGroup:true },
    { fieldId:'field-sy', inputs:['syfrom'], label:'School Year' },
    { fieldId:'field-sem', inputs:['sem'], label:'Semester' },
    { fieldId:'field-contact', inputs:['contact_no'], label:'Contact Number' },
    { fieldId:'field-email', inputs:['email'], label:'Email' },
  ];

  function isFilled(entry){
    if(entry.isGroup){
      const sel=document.getElementById('descselect')?.value?.trim()||'';
      const oth=document.getElementById('desc_others')?.value?.trim()||'';
      return sel!=='' || oth!=='';
    }
    const el=document.getElementById(entry.inputs[0]);
    if(!el) return false;
    if(el.type==='email'){
      const v=el.value.trim();
      return v!=='' && el.checkValidity();
    }
    return el.value.trim()!=='';
  }

  let currentSeqId=null;
  function clearSeq(){
    document.querySelectorAll('.seq-next').forEach(e=>e.classList.remove('seq-next'));
    document.querySelectorAll('.seq-badge').forEach(e=>e.remove());
    document.querySelectorAll('.seq-hint').forEach(e=>e.remove());
    currentSeqId=null;
  }

  function updateSeq(){
    let firstEmpty=null;
    for(const e of order){
      if(!isFilled(e)){ firstEmpty=e; break; }
    }
    const newId = firstEmpty ? firstEmpty.fieldId : null;
    if(newId === currentSeqId) return;
    clearSeq();
    currentSeqId = newId;
    if(!firstEmpty) return;
    const container=document.getElementById(firstEmpty.fieldId);
    if(!container) return;
    container.classList.add('seq-next');
    const label=container.querySelector('label');
    if(label && !label.querySelector('.seq-badge')){
      const badge=document.createElement('span');
      badge.className='seq-badge';
      const idx=order.indexOf(firstEmpty);
      badge.textContent = idx===0 ? ' Start here \u2192' : ' Next \u2192';
      badge.setAttribute('aria-label','Required next: '+firstEmpty.label);
      label.appendChild(badge);
    }
    if(!container.querySelector('.seq-hint')){
      const hint=document.createElement('div');
      hint.className='seq-hint';
      const idx=order.indexOf(firstEmpty);
      hint.textContent = idx===0 ? 'Fill this field first to continue' : 'Required — fill this next (you may skip, but this is recommended order)';
      container.appendChild(hint);
    }
    if(window._updatePayGate) window._updatePayGate();
  }

  function attach(){
    order.forEach(entry=>{
      entry.inputs.forEach(id=>{
        const el=document.getElementById(id);
        if(!el || el.dataset.seqAttached) return;
        el.dataset.seqAttached='1';
        el.addEventListener('input', ()=>{ updateSeq(); if(window._updateSummary) window._updateSummary(); if(window._updatePayGate) window._updatePayGate(); });
        el.addEventListener('change', ()=>{ updateSeq(); if(window._updateSummary) window._updateSummary(); if(window._updatePayGate) window._updatePayGate(); });
        el.addEventListener('blur', updateSeq);
      });
    });
  }

  function initSeq(){
    attach();
    updateSeq();
    setTimeout(updateSeq, 400);
    setTimeout(updateSeq, 1200);
  }
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', initSeq);
  } else {
    initSeq();
  }
})();
</script>

<style>
@media(max-width:900px){ .checkout-grid{grid-template-columns:1fr !important} }
/* sequential guidance - steady highlight, no blink */
.seq-next{position:relative}
.seq-next input, .seq-next select{border-color:var(--blue) !important;background:#fff !important;box-shadow:0 0 0 3px rgba(28,77,161,.15) !important}
.seq-badge{display:inline-flex;align-items:center;gap:4px;margin-left:8px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:800;background:var(--blue);color:#fff;letter-spacing:.02em;vertical-align:middle}
.seq-hint{font-size:11.5px;font-weight:600;color:var(--blue);background:#eef2ff;border:1px dashed #c7d7f5;border-radius:8px;padding:6px 10px;margin-top:6px;line-height:1.4}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


