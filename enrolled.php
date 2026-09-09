<?php
$page_title = "Enrolled Students — Student Number Payment";
require_once __DIR__ . '/includes/config.php';
$selected_campus = isset($_GET['campus']) ? strtoupper(trim($_GET['campus'])) : '';

function findStudentByNumber($con,$studentNumber,$campid){
    $studentNumber=trim($studentNumber);
    if($studentNumber==='') return null;
    $table=mapCampusToTable($campid);
    if($table===null) return null;
    $t=str_replace("`","",$table);
    $stud=mysqli_real_escape_string($con,$studentNumber);
    $sql="SELECT `lname`,`fname` FROM `{$t}` WHERE `stud_num`='".$stud."' LIMIT 1";
    $res=@mysqli_query($con,$sql);
    if($res && ($row=mysqli_fetch_assoc($res))){
        $lname=trim($row['lname']??''); $fname=trim($row['fname']??'');
        $name=trim($fname . (($fname!==''&&$lname!=='')?' ':'') . $lname);
        return $name!==''? $name : null;
    }
    return null;
}

if(isset($_POST["verify_student"])){
    header('Content-Type: application/json');
    $studno=trim($_POST['studentno']??'');
    $campid=$_POST['campid']??'';
    if($studno===''){ echo json_encode(['success'=>false,'message'=>'Student number is required.']); exit; }
    if($campid===''){ echo json_encode(['success'=>false,'message'=>'Campus selection is required.']); exit; }
    $table=mapCampusToTable($campid);
    if($table===null){ echo json_encode(['success'=>false,'message'=>'Invalid campus selected.']); exit; }
    if(!tableExists($con,$table)){ echo json_encode(['success'=>false,'message'=>'Campus database not available.']); exit; }
    $name=findStudentByNumber($con,$studno,$campid);
    if($name){ echo json_encode(['success'=>true,'name'=>$name,'message'=>'Student verified successfully!']); }
    else { echo json_encode(['success'=>false,'message'=>'Student number not found. Please check your student number and campus selection.']); }
    exit;
}

if(isset($_POST["btnsubmit"])){
    date_default_timezone_set("Asia/Manila");
    $campid=$_POST["campid"]??'';
    $studno=trim($_POST["studentno"]??'');
    $transid=$campid ."_". date("HismdY");
    $name=findStudentByNumber($con,$studno,$campid);
    if($name && $studno!==''){
        header("Location: checkout?payee=".urlencode($name)."&transid=".urlencode($transid)."&studentno=".urlencode($studno)."&type=enrolled&campus=".urlencode($campid));
        exit;
    } else {
        $error_msg="We couldn't verify the student number. Please review your campus and student number, then try again.";
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<div style="max-width:780px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
    <a href="<?= $payments_base ?>" class="btn" style="background:#fff;border:1px solid var(--line); padding:7px 12px; font-size:12px">Back</a>
    <span style="color:var(--muted);font-weight:600; font-size:12px">Enrolled Students</span>
  </div>

  <div class="form-card">
    <div class="form-head">
      <i class="fa-solid fa-id-card"></i>
      <div>
        <div style="font-weight:800;font-size:18px">Enrolled Student Payment</div>
        <div style="opacity:.9;font-size:13px">For currently enrolled students — student number required.</div>
      </div>
    </div>
    <div class="form-body">
      <?php if(!empty($error_msg)): ?><div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i><div><?= htmlspecialchars($error_msg) ?></div></div><?php endif; ?>
      <form method="post" id="enrolledForm">
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
        </div>

        <div id="verification-section" style="display:none">
          <div style="background:var(--bg);border:1px solid var(--line);border-radius:14px;padding:16px;margin-top:8px">
            <h4 style="margin:0 0 8px;color:var(--blue)"><i class="fa-solid fa-shield-check"></i> Verify Student Number</h4>
            <div id="submit-help" style="text-align:center;color:var(--muted);font-size:13px;margin-bottom:10px">Please verify your student number before proceeding</div>
            <div class="verify-box">
              <div class="field" style="margin:0"><label for="studentno">Student Number</label><input type="text" name="studentno" id="studentno" maxlength="50" placeholder="Enter your student number" oninput="onInput()" required></div>
              <button type="button" id="verifyBtn" onclick="verifyStudent()" class="btn-verify"><i class="fa-solid fa-magnifying-glass"></i> Verify</button>
            </div>
            <div id="verification-result" style="margin-top:12px"></div>
          </div>
        </div>

        <div id="submit-section" style="display:none;margin-top:14px">
          <button type="submit" name="btnsubmit" id="btnsubmit" class="btn btn-primary" style="width:100%;padding:16px;font-size:16px;background:linear-gradient(135deg,#0e8a6b,#15b88f)" disabled><i class="fa-solid fa-credit-card"></i> Proceed to Payment</button>
        </div>
      </form>
      <div class="alert info"><i class="fa-solid fa-circle-info"></i><div><strong>Enrolled students</strong> can pay tuition, back accounts, and all particulars. Your name is auto-verified against campus records.</div></div>
    </div>
  </div>
</div>

<script>
let isStudentVerified=false;
function onInput(){ isStudentVerified=false; document.getElementById('verification-result').innerHTML=''; document.getElementById('submit-help').innerHTML='Please verify your student number before proceeding'; toggleSubmit(); }
function resetVerification(){
  const campid=document.getElementById('campid').value;
  const sec=document.getElementById('verification-section');
  if(campid!==''){ sec.style.display='block'; const u=new URL(window.location.href); u.searchParams.set('campus',campid); history.replaceState({},'',u); }
  else { sec.style.display='none'; const u=new URL(window.location.href); u.searchParams.delete('campus'); history.replaceState({},'',u); }
  isStudentVerified=false; document.getElementById('verification-result').innerHTML=''; document.getElementById('submit-help').innerHTML='Please verify your student number before proceeding'; toggleSubmit();
}
function toggleSubmit(){ const s=document.getElementById('studentno'); const btn=document.getElementById('btnsubmit'); const sec=document.getElementById('submit-section'); if(isStudentVerified && s && s.value.trim()!==''){ sec.style.display='block'; btn.disabled=false; } else { sec.style.display='none'; if(btn) btn.disabled=true; } }
function confirmStudent(){ isStudentVerified=true; document.getElementById('submit-help').innerHTML='✓ Student confirmed! You may now proceed.'; toggleSubmit(); setTimeout(()=>document.getElementById('btnsubmit')?.scrollIntoView({behavior:'smooth',block:'center'}),100); }
function rejectStudent(){ isStudentVerified=false; document.getElementById('studentno').value=''; document.getElementById('verification-result').innerHTML=''; document.getElementById('submit-help').innerHTML='Please verify your student number before proceeding'; toggleSubmit(); }
function verifyStudent(){
  const studentno=document.getElementById('studentno').value.trim();
  const campid=document.getElementById('campid').value;
  const resultDiv=document.getElementById('verification-result');
  const verifyBtn=document.getElementById('verifyBtn');
  if(!studentno){ alert('Please enter a student number first.'); return; }
  if(!campid){ alert('Please select a campus first.'); return; }
  verifyBtn.disabled=true; verifyBtn.innerHTML='Verifying...'; resultDiv.innerHTML='<div class="alert info">Verifying student...</div>';
  const fd=new FormData(); fd.append('verify_student','1'); fd.append('studentno',studentno); fd.append('campid',campid);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
    if(data.success){
      const campLabel = document.getElementById('campid').selectedOptions[0]?.text || campid;
      const initials = data.name.trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase().substring(0,2) || '●';
      resultDiv.innerHTML='<div class="verify-card">'
        +'<div class="verify-card-top ok"><div class="verify-icon"><i class="fa-solid fa-check"></i></div><div><div class="verify-title">Verified</div><div class="verify-subtitle">'+data.message+'</div></div></div>'
        +'<div class="verify-card-body">'
          +'<div class="verify-profile"><div class="verify-avatar">'+initials+'</div><div><div class="verify-name">'+data.name+'</div><div class="verify-sub">Is this you? Tap Confirm if correct</div></div></div>'
          +'<div class="verify-meta"><span>'+studentno+'</span><span>'+campLabel+'</span></div>'
          +'<div class="verify-hint">Found in campus records. Confirm to proceed with payment.</div>'
        +'</div>'
        +'<div class="verify-actions"><button type="button" onclick="confirmStudent()" class="btn btn-primary"><i class="fa-solid fa-check"></i> Yes, it’s me</button><button type="button" onclick="rejectStudent()" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Not mine</button></div>'
      +'</div>';
      document.getElementById('submit-help').innerHTML='✓ Found — confirm your name';
    } else {
      isStudentVerified=false;
      resultDiv.innerHTML='<div class="verify-card"><div class="verify-card-top err"><div class="verify-icon"><i class="fa-solid fa-circle-xmark"></i></div><div><div class="verify-title">Failed</div><div class="verify-subtitle">Verification failed</div></div></div><div class="verify-card-body"><div class="verify-hint">'+data.message+'</div></div></div>'; document.getElementById('submit-help').innerHTML='Please verify your student number before proceeding';
    }
    toggleSubmit();
  }).catch(()=>{ resultDiv.innerHTML='<div class="verify-card"><div class="verify-card-header err"><i class="fa-solid fa-circle-xmark"></i> Error</div><div class="verify-card-body"><div class="verify-hint">Error verifying. Please try again.</div></div></div>'; toggleSubmit(); }).finally(()=>{ verifyBtn.disabled=false; verifyBtn.innerHTML='<i class="fa-solid fa-magnifying-glass"></i> Verify'; });
}
document.addEventListener('DOMContentLoaded',()=>{
  const c=document.getElementById('campid').value; if(c!=='') document.getElementById('verification-section').style.display='block';
  const s=document.getElementById('studentno');
  if(s){ s.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); verifyStudent(); } }); }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
