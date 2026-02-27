<?php
require 'includes/config.php';
if(!isset($_SESSION['verify_uid'])){ header('Location:login.php');exit; }

$error='';$success='';
$uid   = (int)$_SESSION['verify_uid'];
$email = $_SESSION['verify_email']??'';

// Resend OTP
if(isset($_GET['resend'])){
    $otp=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $exp=date('Y-m-d H:i:s',strtotime('+10 minutes'));
    $pdo->prepare("UPDATE users SET otp_code=?,otp_expiry=? WHERE id=?")->execute([$otp,$exp,$uid]);
    $success='OTP regenerated. Find it in phpMyAdmin &rarr; users &rarr; otp_code column.';
}

// Verify
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();
    $entered=trim($_POST['otp']??'');
    $row=$pdo->prepare("SELECT otp_code,otp_expiry FROM users WHERE id=?");
    $row->execute([$uid]);$data=$row->fetch();
    if(!$data||$data['otp_code']!==$entered){
        $error='Incorrect OTP. Try again.';
    }elseif(strtotime($data['otp_expiry'])<time()){
        $error='OTP expired. <a href="verify.php?resend=1" style="color:#e94560">Resend OTP</a>';
    }else{
        $pdo->prepare("UPDATE users SET email_verified=1,otp_code=NULL,otp_expiry=NULL WHERE id=?")->execute([$uid]);
        unset($_SESSION['verify_uid'],$_SESSION['verify_email']);
        flash('success','Email verified! You can now log in.');
        log_activity($pdo,"Email verified uid=$uid");
        header('Location:login.php'); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Email – CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head></head><body>
<!-- Quote Banner -->
<div class="quote-banner">
  <p class="quote-text">Speak. Report. Resolve.</p>
</div>

<div class="box">
  <div class="icon">📩</div>
  <h1>Verify Email</h1>
  <p class="sub">We sent a 6-digit OTP to <strong><?=clean($email)?></strong></p>

  <?php if($error): ?>
    <div class="error">⚠ <?=$error?></div>
  <?php elseif($success): ?>
    <div class="success-msg">✓ <?=$success?></div>
  <?php endif; ?>

  <div class="info-box">
    💡 If you haven't configured SMTP yet, find your OTP in:<br>
    <strong>phpMyAdmin → complaint_system → users → otp_code</strong>
  </div>

  <div class="card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <div class="form-group">
        <label class="form-label">Enter OTP</label>
        <input class="otp-input" type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="------" required autofocus>
      </div>
      <button class="btn" type="submit">Verify →</button>
    </form>
    <div class="links">
      <p>Didn't receive? <a href="verify.php?resend=1">Resend OTP</a></p>
      <p style="margin-top:8px"><a href="login.php">← Back to Login</a></p>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="footer-content">
    <div class="footer-copy">© 2026 Financial Ujuri. All rights reserved.</div>
    <div class="footer-email">
      Email: <a href="mailto:complaintmanagementsystem010@gmail.com">complaintmanagementsystem010@gmail.com</a>
    </div>
    <div class="footer-divider"></div>
    <div class="footer-contact">For more contact information, check back very soon!</div>
  </div>
</footer>

</body></html>
