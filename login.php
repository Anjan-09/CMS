<?php
require 'includes/config.php';
if(logged_in()){ header('Location:dashboard.php');exit; }

$errors=[];$success='';
$tab = $_GET['tab']??'login';

// REGISTER 
if(isset($_POST['action']) && $_POST['action']==='register'){
    csrf_verify();
    $name  = clean($_POST['full_name']??'');
    $email = strtolower(trim($_POST['email']??''));
    $phone = trim($_POST['phone']??'');
    $pass  = $_POST['password']??'';
    $pass2 = $_POST['password2']??'';

    if(!$name) $errors[]='Full name is required.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Enter a valid email.';
    if(!preg_match('/^98\d{8}$/',$phone)) $errors[]='Phone must be 10 digits starting with 98.';
    if(strlen($pass)<8) $errors[]='Password must be at least 8 characters.';
    if($pass!==$pass2) $errors[]='Passwords do not match.';

    if(!$errors){
        $chk=$pdo->prepare("SELECT id FROM users WHERE email=? OR phone=?");
        $chk->execute([$email,$phone]);
        if($chk->fetch()) $errors[]='Email or phone already registered.';
    }
    if(!$errors){
        $otp=str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $exp=date('Y-m-d H:i:s',strtotime('+10 minutes'));
        $hash=password_hash($pass,PASSWORD_DEFAULT);
        try {
            $ins=$pdo->prepare("INSERT INTO users (full_name,email,phone,password,role,otp_code,otp_expiry)
                                 VALUES (?,?,?,?,'customer',?,?)");
            $ins->execute([$name,$email,$phone,$hash,$otp,$exp]);
            $uid=$pdo->lastInsertId();
            $_SESSION['verify_uid']=$uid;
            $_SESSION['verify_email']=$email;
            log_activity($pdo,"Registered: $email");
            header('Location:verify.php'); exit;
        } catch (Exception $e) {
            $errors[]='Registration failed: '.$e->getMessage();
        }
    }
    $tab='register';
}

//  LOGIN 
if(isset($_POST['action']) && $_POST['action']==='login'){
    csrf_verify();
    $email = strtolower(trim($_POST['email']??''));
    $pass  = $_POST['password']??'';
    $u=$pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1");
    $u->execute([$email]);
    $user=$u->fetch();
    if(!$user || !password_verify($pass,$user['password'])){
        $errors[]='Invalid email or password.';
    } elseif($user['role']==='customer' && !$user['email_verified']){
        $_SESSION['verify_uid']=$user['id'];
        $_SESSION['verify_email']=$user['email'];
        header('Location:verify.php'); exit;
    } else {
        $_SESSION['user_id']=$user['id'];
        $_SESSION['role']=$user['role'];
        $_SESSION['full_name']=$user['full_name'];
        $_SESSION['bank_id']=$user['bank_id'];
        $_SESSION['last_active']=time();
        log_activity($pdo,"Login: $email");
        header('Location:dashboard.php'); exit;
    }
    $tab='login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – Complaint Management System</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
html, body { height: 100%; margin: 0; padding: 0; }
body { display: flex; flex-direction: column; min-height: 100vh; }
.auth-page { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
.quote-banner { flex: 0 0 auto; }
.auth-footer { flex: 0 0 auto; }
</style>
</head>
<body>
<!-- Quote Banner -->
<div class="quote-banner">
  <p class="quote-text">Speak. Report. Resolve.</p>
</div>

<div class="auth-page">
  <div class="auth-box">

    <!-- Error alerts -->
    <?php if($errors): ?>
      <div class="alert alert-danger">
        <?php foreach($errors as $e) echo '<div>⚠ '.clean($e).'</div>'; ?>
      </div>
    <?php endif; ?>

    <?php if(isset($_GET['timeout'])): ?>
      <div class="alert alert-warning">⏱ Session expired. Please login again.</div>
    <?php endif; ?>

    <!-- Tab buttons -->
    <div class="auth-tabs">
      <button class="auth-tab <?=$tab==='login'?'active':''?>" onclick="showTab('login', event)">Sign In</button>
      <button class="auth-tab <?=$tab==='register'?'active':''?>" onclick="showTab('register', event)">Register</button>
    </div>

    <!-- LOGIN CARD -->
    <div id="form-login" class="card <?=$tab!=='login'?'hidden':''?>" style="display:<?=$tab==='login'?'block':'none'?>">
      <div class="card-header">Sign In to Your Account</div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input class="form-control" type="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div style="position:relative">
            <input class="form-control pwd-field" type="password" name="password" placeholder="••••••••" required>
            <button type="button" class="pwd-toggle" onclick="togglePassword(this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px">👁️</button>
          </div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Sign In →</button>
      </form>
      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">
        Don't have an account? <a href="#" onclick="showTab('register', event)" style="color:var(--accent);text-decoration:none;font-weight:600">Register here</a>
      </div>
    </div>

    <!-- REGISTER CARD -->
    <div id="form-register" class="card <?=$tab!=='register'?'hidden':''?>" style="display:<?=$tab==='register'?'block':'none'?>">
      <div class="card-header">Create New Account</div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-control" type="text" name="full_name" placeholder="Sita Sharma" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input class="form-control" type="email" name="email" placeholder="sita@gmail.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input class="form-control" type="tel" name="phone" placeholder="98XXXXXXXX" required>
          <div class="form-hint">10 digits starting with 98</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <div style="position:relative">
              <input class="form-control pwd-field" type="password" name="password" placeholder="Min 8 characters" required>
              <button type="button" class="pwd-toggle" onclick="togglePassword(this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px">👁️</button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div style="position:relative">
              <input class="form-control pwd-field" type="password" name="password2" placeholder="••••••••" required>
              <button type="button" class="pwd-toggle" onclick="togglePassword(this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px">👁️</button>
            </div>
          </div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create Account →</button>
      </form>
      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">
        Already have an account? <a href="#" onclick="showTab('login', event)" style="color:var(--accent);text-decoration:none;font-weight:600">Sign in</a>
      </div>
    </div>

    <!-- Demo Box (HIDDEN)
    <div class="card" style="background:rgba(14,165,233,.08);margin-top:20px">
      <div style="text-align:center">
        <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Demo Credentials</div>
        <div style="font-size:13px"><strong>admin@123.com</strong> / <strong>OneTwo3!</strong></div>
      </div>
    </div>
    -->
  </div>
</div>
<script>
function showTab(tab, e) {
  e?.preventDefault?.();
  const loginForm = document.getElementById('form-login');
  const registerForm = document.getElementById('form-register');
  const tabs = document.querySelectorAll('.auth-tab');
  
  if (tab === 'login') {
    loginForm.style.display = 'block';
    registerForm.style.display = 'none';
    tabs[0].classList.add('active');
    tabs[1].classList.remove('active');
  } else {
    loginForm.style.display = 'none';
    registerForm.style.display = 'block';
    tabs[0].classList.remove('active');
    tabs[1].classList.add('active');
  }
}
function togglePassword(btn) {
  const pwd = btn.previousElementSibling;
  if (pwd.type === 'password') {
    pwd.type = 'text';
    btn.textContent = '🙈';
  } else {
    pwd.type = 'password';
    btn.textContent = '👁️';
  }
}
</script>

<!-- Footer -->
<footer class="footer">
  <div class="footer-content">
    <div class="footer-copy">© 2026 Financial Ujuri. All rights reserved.</div>
  </div>
</footer>

</body></html>
