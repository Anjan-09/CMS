<?php
require 'includes/config.php';
require_role(['customer']);

$uid  = $_SESSION['user_id'];
$me   = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me->execute([$uid]); $me = $me->fetch();
$banks = $pdo->query("SELECT id,name,type FROM banks WHERE is_verified=1 ORDER BY name")->fetchAll();

$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();

    $bank_id    = (int)($_POST['bank_id']??0);
    $subject    = clean($_POST['subject']??'');
    $desc       = clean($_POST['description']??'');
    $priority   = $_POST['priority']??'';

    if(!$bank_id)              $errors[]='Please select a bank.';
    if(strlen($subject)<5)     $errors[]='Subject too short (min 5 chars).';
    if(strlen($desc)<10)       $errors[]='Description too short (min 10 chars).';
    if(!in_array($priority,['high','medium','low'])) $errors[]='Select a priority.';

    // File upload
    $screenshot=null;
    if(empty($_FILES['screenshot']['name'])){
        $errors[]='Screenshot is required.';
    }else{
        $file=$_FILES['screenshot'];
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        $allowed=['jpg','jpeg'];
        if(!in_array($ext,$allowed)) $errors[]='Image must be JPG format only.';
        elseif($file['size']>5242880) $errors[]='Image must be smaller than 5 MB.';
        elseif(!getimagesize($file['tmp_name'])) $errors[]='Invalid image file.';
        elseif(!in_array(mime_content_type($file['tmp_name']),['image/jpeg'])) $errors[]='File must be a valid JPG image.';
        else {
            $fname=uniqid('sc_',true).'.'.$ext;
            if(!is_dir('uploads')) mkdir('uploads',0755,true);
            if(!move_uploaded_file($file['tmp_name'],'uploads/'.$fname)){
                $errors[]='Upload failed. Check uploads/ folder permissions.';
            } else {
                $screenshot=$fname;
            }
        }
    }

    if(!$errors){
        $ticket = gen_ticket();
        $sla    = sla_deadline($priority);
        $staff  = auto_assign($pdo,$bank_id);

        $ins=$pdo->prepare("INSERT INTO complaints
          (ticket_no,customer_id,bank_id,assigned_to,subject,description,priority,screenshot,sla_deadline)
          VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$ticket,$uid,$bank_id,$staff,$subject,$desc,$priority,$screenshot,$sla]);
        $cid=(int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO complaint_logs (complaint_id,user_id,note,new_status) VALUES (?,?,?,?)")
            ->execute([$cid,$uid,'Complaint submitted by customer.','pending']);

        log_activity($pdo,"Complaint submitted: $ticket");
        flash('success',"Ticket <strong>$ticket</strong> submitted! ".
              ($staff?'Auto-assigned to staff.':'Will be assigned shortly.'));
        header('Location:view_ticket.php?id='.$cid); exit;
    }
}

$page_title='Submit Complaint';
ob_start();
?>
<div class="page-wrap" style="max-width:760px">
  <div class="breadcrumb">
    <a href="dashboard.php">Dashboard</a> › <span>New Complaint</span>
  </div>
  <div class="page-header">
    <h1>Submit a Complaint</h1>
    <p>Report an issue with your digital banking service. All fields are required.</p>
  </div>

  <?php if($errors): ?>
    <div class="alert alert-danger">
      <span>
        <?php foreach($errors as $e) echo '• '.clean($e).'<br>'; ?>
      </span>
      <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">

      <!-- Auto-fill read-only info -->
      <div class="alert alert-info" style="margin-bottom:20px">
        <span>👤 Submitting as <strong><?=clean($me['full_name'])?></strong>
        · 📧 <?=clean($me['email'])?>
        · 📞 <?=clean($me['phone'])?></span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Select Bank / Wallet *</label>
          <select name="bank_id" class="form-control" required>
            <option value="">— Choose bank —</option>
            <?php foreach($banks as $b): ?>
              <option value="<?=$b['id']?>" <?=($_POST['bank_id']??'')==$b['id']?'selected':''?>>
                <?=clean($b['name'])?> (<?=strtoupper(str_replace('_',' ',$b['type']))?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priority *</label>
          <select name="priority" class="form-control" required>
            <option value="">— Choose priority —</option>
            <option value="high"   <?=($_POST['priority']??'')==='high'?'selected':''?>>🔴 High — 2 hour SLA</option>
            <option value="medium" <?=($_POST['priority']??'')==='medium'?'selected':''?>>🟡 Medium — 12 hour SLA</option>
            <option value="low"    <?=($_POST['priority']??'')==='low'?'selected':''?>>🟢 Low — 24 hour SLA</option>
          </select>
          <div class="form-hint">SLA = maximum time before ticket goes overdue</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Subject *</label>
        <input class="form-control" type="text" name="subject"
               value="<?=clean($_POST['subject']??'')?>"
               placeholder="e.g. Transaction failed but amount debited" required>
      </div>

      <div class="form-group">
        <label class="form-label">Detailed Description *</label>
        <textarea class="form-control" name="description" rows="6"
                  placeholder="Describe your issue in detail. Include transaction ID, date/time, amounts, error messages, etc." required><?=clean($_POST['description']??'')?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Screenshot *</label>
        <div class="upload-box" onclick="document.getElementById('sc').click()" id="drop-area">
          <input type="file" id="sc" name="screenshot" accept=".jpg,.jpeg,image/jpeg" onchange="previewImg(this)">
          <div id="upload-placeholder">
            <div style="font-size:36px;margin-bottom:10px">📷</div>
            <div style="font-weight:600;margin-bottom:4px">Click to upload screenshot</div>
            <div class="text-muted text-sm">JPG only — max 5 MB</div>
          </div>
          <img id="img-preview" src="" alt="" style="max-width:100%;max-height:300px;border-radius:8px;display:none">
        </div>
      </div>

      <div class="flex-end" style="margin-top:8px">
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary" type="submit">Submit Complaint →</button>
      </div>
    </form>
  </div>
</div>

<script>
function previewImg(input){
  const f=input.files[0];
  if(!f) return;
  
  // Check file size
  if(f.size>5242880){
    alert('File too large (max 5 MB)');
    input.value='';
    return;
  }
  
  // Check file type
  if(!f.type.match('image/jpeg')){
    alert('Please upload a JPG image only');
    input.value='';
    return;
  }
  
  // Check file extension
  const ext=f.name.split('.').pop().toLowerCase();
  if(ext!=='jpg' && ext!=='jpeg'){
    alert('File must have .jpg or .jpeg extension');
    input.value='';
    return;
  }
  
  const r=new FileReader();
  r.onload=e=>{
    document.getElementById('img-preview').src=e.target.result;
    document.getElementById('img-preview').style.display='block';
    document.getElementById('upload-placeholder').style.display='none';
  };
  r.readAsDataURL(f);
}
</script>
<?php
$body_html = ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
