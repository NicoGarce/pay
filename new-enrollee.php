<?php
$page_title = "New Enrollees — Locator Payment";
require_once __DIR__ . '/includes/config.php';

$selected_campus = isset($_GET['campus']) ? strtoupper(trim($_GET['campus'])) : '';

// verification helpers
function findStudentByLocator($con, $locator, $campid) {
    $locator = trim($locator);
    if ($locator === '') return null;
    $table = mapCampusToTmpTable($campid);
    if ($table === null) return null;
    $t = str_replace("`","",$table);
    $loc = mysqli_real_escape_string($con, $locator);
    $sql = "SELECT `stud_name` FROM `{$t}` WHERE `locator_num`='".$loc."' LIMIT 1";
    $res = @mysqli_query($con, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = trim($row['stud_name'] ?? '');
        return $name !== '' ? $name : null;
    }
    return null;
}

if (isset($_POST["verify_locator"])) {
    header('Content-Type: application/json');
    $locno = trim($_POST['locno'] ?? '');
    $campid = $_POST['campid'] ?? '';
    $table = mapCampusToTmpTable($campid);
    if ($table === null) {
        echo json_encode(['success'=>false,'message'=>'Invalid campus selected.']); exit;
    }
    if (!tableExists($con, $table)) {
        echo json_encode(['success'=>false,'message'=>'Campus verification data not available.']); exit;
    }
    $name = findStudentByLocator($con, $locno, $campid);
    if ($name && $locno !== '') {
        echo json_encode(['success'=>true,'name'=>$name,'message'=>'Locator verified successfully!']); exit;
    } else {
        echo json_encode(['success'=>false,'message'=>'Locator number not found. Please proceed with advising before attempting payment.','advice'=>true]); exit;
    }
}

if (isset($_POST["btnsubmit"])) {
    date_default_timezone_set("Asia/Manila");
    $campid = $_POST["campid"] ?? '';
    $locno = trim($_POST["locno"] ?? '');
    $name = findStudentByLocator($con, $locno, $campid);
    if ($name && $locno !== '') {
        $transid = $campid ."_". date("HismdY");
        header("Location: checkout?payee=".urlencode($name)."&transid=".urlencode($transid)."&locno=".urlencode($locno)."&type=new");
        exit;
    } else {
        $error_msg = "Entered locator number does not exist or is not validated.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div style="max-width:780px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
    <a href="<?= $payments_base ?>" class="btn" style="background:#fff;border:1px solid var(--line); padding:7px 12px; font-size:12px">Back</a>
    <span style="color:var(--muted);font-weight:600; font-size:12px">New Enrollees</span>
  </div>

  <div class="form-card">
    <div class="form-head">
      <i class="fa-solid fa-user-plus"></i>
      <div>
        <div style="font-weight:800;font-size:18px">New Enrollee Payment</div>
        <div style="opacity:.9;font-size:13px">For students not previously enrolled — locator number only.</div>
      </div>
    </div>
    <div class="form-body">
      <?php if (!empty($error_msg)): ?><div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i><div><?= htmlspecialchars($error_msg) ?></div></div><?php endif; ?>

      <form method="post" id="newForm">
        <div class="field">
          <label for="campid">Select Your Campus</label>
          <select name="campid" id="campid" required onchange="resetVerification()">
            <option value="">Choose your campus...</option>
            <option value="UPHB" <?= $selected_campus==='UPHB'?'selected':'' ?>>Binan Campus</option>
            <option value="UPHMU" <?= $selected_campus==='UPHMU'?'selected':'' ?>>Medical University</option>
            <option value="UPHG" <?= $selected_campus==='UPHG'?'selected':'' ?>>GMA Campus</option>
            <option value="UPHM" <?= $selected_campus==='UPHM'?'selected':'' ?>>Manila Campus</option>
            <option value="PHCP" <?= $selected_campus==='PHCP'?'selected':'' ?>>Pangasinan Campus</option>
            <option value="UPHI" <?= $selected_campus==='UPHI'?'selected':'' ?>>Isabela Campus</option>
            <option value="UPHR" <?= $selected_campus==='UPHR'?'selected':'' ?>>Roxas Campus</option>
          </select>
          <small style="color:var(--muted)">Choose where you were advised.</small>
        </div>

        <div id="locator-section" style="display:none">
          <div style="background:var(--bg);border:1px solid var(--line);border-radius:14px;padding:16px;margin-top:8px">
            <h4 style="margin:0 0 8px;color:var(--blue)"><i class="fa-solid fa-magnifying-glass"></i> Verify Locator Number</h4>
            <div id="submit-help" style="text-align:center;color:var(--muted);font-size:13px;margin-bottom:10px">Please verify your locator number before proceeding</div>
            <div class="verify-box">
              <div class="field" style="margin:0"><label for="locno">Locator Number</label><input type="text" name="locno" id="locno" maxlength="20" placeholder="Enter locator number" oninput="onLocInput()" required></div>
              <button type="button" id="verifyBtn" onclick="verifyLocator()" class="btn-verify"><i class="fa-solid fa-magnifying-glass"></i> Verify</button>
            </div>
            <div id="verification-result" style="margin-top:12px"></div>
          </div>
        </div>

        <div id="submit-section" style="display:none;margin-top:14px">
          <button type="submit" name="btnsubmit" id="btnsubmit" class="btn btn-primary" style="width:100%;padding:16px;font-size:16px" disabled><i class="fa-solid fa-credit-card"></i> Proceed to Payment</button>
        </div>
      </form>

      <div class="alert info"><i class="fa-solid fa-circle-info"></i><div><strong>Note:</strong> Locator numbers are issued during advising. If not found, please complete advising first.</div></div>
    </div>
  </div>
</div>

<script>
let isLocatorVerified=false;
function resetVerification(){
  const campid=document.getElementById('campid').value;
  const sec=document.getElementById('locator-section');
  if(campid!==''){ sec.style.display='block'; const url=new URL(window.location.href); url.searchParams.set('campus',campid); history.replaceState({},'',url); }
  else { sec.style.display='none'; const url=new URL(window.location.href); url.searchParams.delete('campus'); history.replaceState({},'',url); }
  isLocatorVerified=false;
  document.getElementById('verification-result').innerHTML='';
  document.getElementById('submit-help').innerHTML='Please verify your locator number before proceeding';
  toggleSubmit();
}
function onLocInput(){ isLocatorVerified=false; document.getElementById('verification-result').innerHTML=''; document.getElementById('submit-help').innerHTML='Please verify your locator number before proceeding'; toggleSubmit(); }
function toggleSubmit(){
  const l=document.getElementById('locno'); const btn=document.getElementById('btnsubmit'); const sec=document.getElementById('submit-section');
  if(isLocatorVerified && l && l.value.trim()!==''){ sec.style.display='block'; btn.disabled=false; } else { sec.style.display='none'; if(btn) btn.disabled=true; }
}
function confirmLocator(){ isLocatorVerified=true; document.getElementById('submit-help').innerHTML='✓ Name confirmed! You may now proceed.'; toggleSubmit(); setTimeout(()=>document.getElementById('btnsubmit')?.scrollIntoView({behavior:'smooth',block:'center'}),100); }
function rejectLocator(){ isLocatorVerified=false; document.getElementById('locno').value=''; document.getElementById('verification-result').innerHTML=''; document.getElementById('submit-help').innerHTML='Please verify your locator number before proceeding'; toggleSubmit(); }
function verifyLocator(){
  const locno=document.getElementById('locno').value.trim();
  const campid=document.getElementById('campid').value;
  const resultDiv=document.getElementById('verification-result');
  const verifyBtn=document.getElementById('verifyBtn');
  if(!locno){ alert('Please enter a locator number first.'); return; }
  if(!campid){ alert('Please select a campus first.'); return; }
  verifyBtn.disabled=true; verifyBtn.innerHTML='Verifying...'; resultDiv.innerHTML='<div class="alert info">Verifying locator...</div>';
  const fd=new FormData(); fd.append('verify_locator','1'); fd.append('locno',locno); fd.append('campid',campid);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
    if(data.success){
      const campLabel = document.getElementById('campid').selectedOptions[0]?.text || campid;
      const initials = data.name.trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase().substring(0,2) || '●';
      resultDiv.innerHTML='<div class="verify-card">'
        +'<div class="verify-card-top ok"><div class="verify-icon"><i class="fa-solid fa-check"></i></div><div><div class="verify-title">Verified</div><div class="verify-subtitle">'+data.message+'</div></div></div>'
        +'<div class="verify-card-body">'
          +'<div class="verify-profile"><div class="verify-avatar">'+initials+'</div><div><div class="verify-name">'+data.name+'</div><div class="verify-sub">Is this you? Tap Confirm if correct</div></div></div>'
          +'<div class="verify-meta"><span>'+locno+'</span><span>'+campLabel+'</span></div>'
          +'<div class="verify-hint">Found in campus records. Confirm to proceed with payment.</div>'
        +'</div>'
        +'<div class="verify-actions"><button type="button" onclick="confirmLocator()" class="btn btn-primary"><i class="fa-solid fa-check"></i> Yes, it’s me</button><button type="button" onclick="rejectLocator()" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Not mine</button></div>'
      +'</div>';
      document.getElementById('submit-help').innerHTML='✓ Found — confirm your name';
    } else {
      isLocatorVerified=false;
      if(data.advice){ resultDiv.innerHTML='<div class="verify-card"><div class="verify-card-top warn"><div class="verify-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="verify-title">Not found</div><div class="verify-subtitle">No matching locator</div></div></div><div class="verify-card-body"><div class="verify-hint">'+data.message+'</div></div></div>'; document.getElementById('submit-help').innerHTML='Locator not found. Please complete advising.'; }
      else { resultDiv.innerHTML='<div class="verify-card"><div class="verify-card-top err"><div class="verify-icon"><i class="fa-solid fa-circle-xmark"></i></div><div><div class="verify-title">Failed</div><div class="verify-subtitle">Verification failed</div></div></div><div class="verify-card-body"><div class="verify-hint">'+data.message+'</div></div></div>'; document.getElementById('submit-help').innerHTML='Please verify your locator number before proceeding'; }
    }
    toggleSubmit();
  }).catch(()=>{ resultDiv.innerHTML='<div class="verify-card"><div class="verify-card-header err"><i class="fa-solid fa-circle-xmark"></i> Error</div><div class="verify-card-body"><div class="verify-hint">Error verifying. Please try again.</div></div></div>'; toggleSubmit(); }).finally(()=>{ verifyBtn.disabled=false; verifyBtn.innerHTML='<i class="fa-solid fa-magnifying-glass"></i> Verify'; });
}
document.addEventListener('DOMContentLoaded',()=>{
  const campid=document.getElementById('campid').value;
  if(campid!=='') document.getElementById('locator-section').style.display='block';
  const l=document.getElementById('locno');
  if(l){ l.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); verifyLocator(); } }); }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
