<?php
require '../includes/config.php';
require_role(['super_admin']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();
    $keys=['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_name',
           'theme_primary','theme_accent','site_name'];
    foreach($keys as $k){
        $val=trim($_POST[$k]??'');
        $pdo->prepare("INSERT INTO settings (key_name,key_value) VALUES (?,?) ON DUPLICATE KEY UPDATE key_value=?")
            ->execute([$k,$val,$val]);
    }
    log_activity($pdo,'System settings updated');
    flash('success','Settings saved successfully.');
    header('Location:settings.php');exit;
}

$all=$pdo->query("SELECT key_name,key_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title='System Settings';
ob_start();
?>
<div class="page-wrap" style="max-width:760px">
  <div class="page-header">
    <h1>System Settings</h1>
    <p>Configure SMTP email and system appearance.</p>
  </div>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">

    <!-- SMTP -->
    <div class="card mb-24">
      <div class="card-header">📧 Gmail SMTP Configuration</div>
      <div class="alert alert-info mb-16">
        <span>To send emails: generate a Gmail <strong>App Password</strong> at
        <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--info)">myaccount.google.com/apppasswords</a>.
        Enable 2-Step Verification first.</span>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">SMTP Host</label>
          <input class="form-control" name="smtp_host" value="<?=clean($all['smtp_host']??'smtp.gmail.com')?>">
        </div>
        <div class="form-group">
          <label class="form-label">SMTP Port</label>
          <input class="form-control" name="smtp_port" value="<?=clean($all['smtp_port']??'587')?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Gmail Address</label>
          <input class="form-control" type="email" name="smtp_user" value="<?=clean($all['smtp_user']??'')?>">
        </div>
        <div class="form-group">
          <label class="form-label">App Password</label>
          <input class="form-control" type="password" name="smtp_pass" value="<?=clean($all['smtp_pass']??'')?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">From Email</label>
          <input class="form-control" name="smtp_from" value="<?=clean($all['smtp_from']??'')?>">
        </div>
        <div class="form-group">
          <label class="form-label">From Name</label>
          <input class="form-control" name="smtp_name" value="<?=clean($all['smtp_name']??'Complaint Management System')?>">
        </div>
      </div>
    </div>

    <!-- Theme -->
    <div class="card mb-24">
      <div class="card-header">🎨 Theme Colors</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Primary Color</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input type="color" name="theme_primary" value="<?=$all['theme_primary']??'#1a1a2e'?>"
                   style="width:46px;height:40px;border:none;background:none;cursor:pointer;padding:0;border-radius:6px">
            <input class="form-control" id="primary_hex" value="<?=$all['theme_primary']??'#1a1a2e'?>"
                   onchange="document.querySelector('[name=theme_primary]').value=this.value">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Accent Color</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input type="color" name="theme_accent" value="<?=$all['theme_accent']??'#e94560'?>"
                   style="width:46px;height:40px;border:none;background:none;cursor:pointer;padding:0;border-radius:6px">
            <input class="form-control" id="accent_hex" value="<?=$all['theme_accent']??'#e94560'?>"
                   onchange="document.querySelector('[name=theme_accent]').value=this.value">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Site Name</label>
        <input class="form-control" name="site_name" value="<?=clean($all['site_name']??'Complaint Management System')?>">
      </div>
    </div>

    <button class="btn btn-primary" type="submit">Save Settings</button>
    <div class="form-group mt-16">
      <label class="form-label">Send Test Email</label>
      <div style="display:flex;gap:10px">
        <input class="form-control" type="email" name="test_email" placeholder="Enter your email">
        <button class="btn btn-info" name="test_email_btn" value="1" type="submit">Send Test</button>
      </div>
      <small class="text-muted">Use your email to check if SMTP is working.</small>
    </div>
  </form>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';

if(isset($_POST['test_email'])){
    $test_email = trim($_POST['test_email'] ?? '');
    if(filter_var($test_email, FILTER_VALIDATE_EMAIL)){
        require '../includes/mailer.php';
        $mailer = new Mailer($pdo);
        $result = $mailer->send($test_email, 'Admin Test', 'Test Email from CMS', '<p>This is a test email from Complaint Management System.</p>');
        if($result){
            flash('success','Test email sent successfully to '.$test_email);
        }else{
            flash('danger','Test email failed: '.$mailer->getLastError());
        }
    }else{
        flash('danger','Invalid test email address.');
    }
    header('Location:settings.php');exit;
}
