<?php
$page_title = "QR Codes — Payments Hub";
require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();
require_once __DIR__ . '/../includes/header.php';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$payBase = $baseUrl . $payments_base;
$links = [
  'new-enrollee' => ['label'=>'New Enrollee','sub'=>'Locator verification','url'=>$payBase.'new-enrollee','color'=>'linear-gradient(135deg,var(--blue),#6ea0ff)','icon'=>'fa-user-plus'],
  'enrolled' => ['label'=>'Enrolled','sub'=>'Student number','url'=>$payBase.'enrolled','color'=>'linear-gradient(135deg,#0e8a6b,#34d399)','icon'=>'fa-id-card'],
  'other' => ['label'=>'Other Payment','sub'=>'No ID required','url'=>$payBase.'other','color'=>'linear-gradient(135deg,#d97706,#f59e0b)','icon'=>'fa-wallet'],
];
?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/qr" class="active">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content">
  <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px">
    <h2 style="margin:0; font-size:22px"><i class="fa-solid fa-qrcode" style="color:var(--blue)"></i> Payment QR Codes</h2>
    <span style="margin-left:auto; background:var(--bg); border:1px solid var(--line); padding:6px 10px; border-radius:999px; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--muted)">Print-ready • With UPHS logo</span>
  </div>
  <p style="color:var(--muted); margin:0 0 16px; font-size:13.5px">Scan to open the payment page directly. Each QR has the UPHS logo in the center and the payment type label below.</p>

  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px" class="qr-grid">
    <?php foreach($links as $key=>$info): ?>
    <div class="qr-card" style="background:#fff; border:1px solid var(--line); border-radius:18px; padding:18px; text-align:center; box-shadow:0 8px 24px rgba(15,32,64,.06); display:flex; flex-direction:column; align-items:center; gap:12px">
      <div style="width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; background:<?= $info['color'] ?>"><i class="fa-solid <?= $info['icon'] ?>"></i></div>
      <div>
        <div style="font-weight:800; font-size:15px; color:var(--text)"><?= htmlspecialchars($info['label']) ?></div>
        <div style="font-size:12px; color:var(--muted); font-weight:600"><?= htmlspecialchars($info['sub']) ?></div>
      </div>
      <div class="qr-wrap" style="position:relative; width:220px; height:220px; display:flex; align-items:center; justify-content:center; background:#fff; border:1px solid var(--line); border-radius:14px; padding:10px">
        <div id="qr-<?= $key ?>" style="width:200px; height:200px"></div>
        <img src="<?= $payments_base ?>assets/UPHSJ_LOGO_2026Edition.png" alt="UPHS Logo" class="qr-logo" style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:44px; height:44px; object-fit:contain; background:#fff; border-radius:8px; padding:4px; box-shadow:0 2px 8px rgba(0,0,0,.15); border:1px solid #e2e8f0">
      </div>
      <div style="font-size:11px; color:var(--muted); word-break:break-all; background:#f8fafc; border:1px solid var(--line); border-radius:999px; padding:6px 10px; max-width:100%"><?= htmlspecialchars($info['url']) ?></div>
      <div style="display:flex; gap:8px; width:100%">
        <a href="<?= htmlspecialchars($info['url']) ?>" target="_blank" class="btn" style="flex:1; background:#fff; border:1px solid var(--line); padding:10px; font-size:12px"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
        <button onclick="downloadQR('<?= $key ?>','QR-<?= $key ?>.png')" class="btn btn-primary" style="flex:1; background:var(--blue); color:#fff; padding:10px; font-size:12px; border:none"><i class="fa-solid fa-download"></i> Save</button>
      </div>
      <button onclick="printQR('<?= $key ?>')" class="btn" style="width:100%; background:#f8fafc; border:1px solid var(--line); padding:10px; font-size:12px"><i class="fa-solid fa-print"></i> Print</button>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="alert info" style="margin-top:16px"><i class="fa-solid fa-circle-info"></i><div><strong>Tip:</strong> Print on A4 or sticker, place at cashier/advising. QR uses high error correction so the UPHS logo in the center still scans.</div></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const qrData = {
  'new-enrollee': "<?= addslashes($links['new-enrollee']['url']) ?>",
  'enrolled': "<?= addslashes($links['enrolled']['url']) ?>",
  'other': "<?= addslashes($links['other']['url']) ?>"
};
Object.keys(qrData).forEach(key=>{
  const el = document.getElementById('qr-'+key);
  if(!el) return;
  new QRCode(el, {
    text: qrData[key],
    width: 200,
    height: 200,
    colorDark: "#0f2040",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
  });
});
function downloadQR(key, filename){
  // Generate high-res QR (600px) to avoid blur from upscaling 200→400
  const tmp = document.createElement('div');
  tmp.style.position='fixed'; tmp.style.left='-9999px'; tmp.style.top='-9999px';
  document.body.appendChild(tmp);
  new QRCode(tmp, {
    text: qrData[key],
    width: 600,
    height: 600,
    colorDark: "#0f2040",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
  });
  // wait a tick for QR to render
  setTimeout(()=>{
    const hiCanvas = tmp.querySelector('canvas');
    if(!hiCanvas){ document.body.removeChild(tmp); return; }
    const out = document.createElement('canvas');
    out.width = 660; out.height = 760;
    const ctx = out.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0,0,out.width,out.height);
    // crisp QR — disable smoothing for QR, keep on for logo/text
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(hiCanvas, 30, 30, 600, 600);
    ctx.imageSmoothingEnabled = true;
    document.body.removeChild(tmp);
    const logoSize = 110;
    const lx = (600 - logoSize)/2 + 30, ly = (600 - logoSize)/2 + 30;
    ctx.fillStyle = '#fff';
    const pad = 10, r = 14;
    const bx = lx - pad, by = ly - pad, bw = logoSize + pad*2, bh = logoSize + pad*2;
    if(ctx.roundRect){
      ctx.beginPath(); ctx.roundRect(bx, by, bw, bh, r); ctx.fill();
    } else {
      ctx.fillRect(bx, by, bw, bh);
    }
    ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 1.5;
    if(ctx.roundRect){ ctx.beginPath(); ctx.roundRect(bx, by, bw, bh, r); ctx.stroke(); }
    else ctx.strokeRect(bx, by, bw, bh);
    const doSave = (logoImg)=>{
      if(logoImg && logoImg.naturalWidth){
        try {
          // contain — preserve aspect, no stretch
          const iw = logoImg.naturalWidth, ih = logoImg.naturalHeight;
          const scale = Math.min(logoSize / iw, logoSize / ih);
          const dw = iw * scale, dh = ih * scale;
          const dx = lx + (logoSize - dw)/2, dy = ly + (logoSize - dh)/2;
          ctx.drawImage(logoImg, dx, dy, dw, dh);
        } catch(e){}
      }
      ctx.fillStyle = '#0f2040';
      ctx.font = '800 26px Inter, sans-serif';
      ctx.textAlign = 'center';
      const labels = {'new-enrollee':'New Enrollee','enrolled':'Enrolled','other':'Other Payment'};
      ctx.fillText(labels[key]||key, 330, 690);
      ctx.fillStyle = '#667085';
      ctx.font = '600 14px Inter, sans-serif';
      let urlText = qrData[key];
      if(urlText.length>58) urlText = urlText.slice(0,55)+'...';
      ctx.fillText(urlText, 330, 715);
      const a = document.createElement('a');
      a.download = filename;
      a.href = out.toDataURL('image/png');
      a.click();
    };
    const logoSrc = "<?= $payments_base ?>assets/UPHSJ_LOGO_2026Edition.png";
    let saved = false;
    const onceSave = (img)=>{ if(saved) return; saved=true; doSave(img); };
    const logoImg = new Image();
    logoImg.onload = ()=> onceSave(logoImg);
    logoImg.onerror = ()=> onceSave(null);
    logoImg.src = logoSrc;
    if(logoImg.complete && logoImg.naturalWidth) setTimeout(()=> onceSave(logoImg), 20);
    setTimeout(()=> onceSave(null), 900);
  }, 80);
}
function printQR(key){
  const wrap = document.getElementById('qr-'+key);
  const canvas = wrap.querySelector('canvas');
  if(!canvas) return;
  const w = window.open('', '_blank');
  w.document.write('<html><head><title>QR '+key+'</title><style>body{margin:0; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; font-family:Inter, sans-serif} img{width:320px; height:320px; border:1px solid #e2e8f0; border-radius:12px} h2{margin:12px 0 4px} p{color:#667085; font-size:12px; word-break:break-all}</style></head><body><img src="'+canvas.toDataURL()+'"><h2>'+key+'</h2><p>'+qrData[key]+'</p><script>window.print();<\/script></body></html>');
  w.document.close();
}
</script>
<style>
@media(max-width:900px){ .qr-grid{grid-template-columns:1fr !important} }
@media print{ .admin-tabs, .pay-header, .pay-footer{display:none} }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
