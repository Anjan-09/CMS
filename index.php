<?php
require 'includes/config.php';
$page_title = 'Financial Ujuri – Complaint Management System';
ob_start();
?>
<div class="home-hero">
  <div class="home-header">
    <div class="home-logo">
      <span class="logo-title">Financial Ujuri</span>
      <span class="logo-sub">COMPLAINT MANAGEMENT SYSTEM</span>
    </div>
    <a href="login.php" class="btn btn-primary home-login-btn">Login / Register</a>
  </div>
  <div class="home-main">
    <div class="home-portal-badge">NEPAL'S BANKING COMPLAINT PORTAL</div>
    <h1 class="home-title">Report Your <span class="accent">Online Banking</span><br>Issues Instantly</h1>
    <div class="home-desc">Having trouble with your online payment? Submit your complaint directly to your bank and track its resolution — all in one place.</div>
    <a href="submit_complaint.php" class="btn btn-primary home-cta">File a Complaint →</a>
    </div>
  </div>
</div>
<style>
body {
  background: linear-gradient(135deg,#1a3260 0%,#23406c 100%);
  color: #fff;
  min-height: 100vh;
}
.home-hero {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 0;
  position: relative;
}
.home-header {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 32px 48px 0 48px;
  position: absolute;
  top: 0;
  left: 0;
  z-index: 10;
}
.home-logo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.logo-icon {
  font-size: 32px;
  margin-bottom: 6px;
}
.logo-title {
  font-family: 'Syne',sans-serif;
  font-size: 26px;
  font-weight: 800;
  color: #fff;
}
.logo-sub {
  font-size: 14px;
  color: #cbd5e1;
  letter-spacing: 1px;
  margin-top: 2px;
}
.home-login-btn {
  background: #ffd166;
  color: #1a3260;
  font-weight: 600;
  border-radius: 16px;
  font-size: 16px;
  padding: 12px 32px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.home-main {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 180px;
}
.home-portal-badge {
  background: rgba(255,255,255,0.08);
  color: #cbd5e1;
  font-size: 13px;
  padding: 6px 18px;
  border-radius: 18px;
  margin-bottom: 24px;
  letter-spacing: 1px;
}
.home-title {
  font-family: 'Syne',sans-serif;
  font-size: 48px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 18px;
  text-align: center;
}
.home-title .accent {
  color: #ffd166;
}
.home-desc {
  font-size: 18px;
  color: #cbd5e1;
  margin-bottom: 32px;
  text-align: center;
  max-width: 600px;
}
.home-cta {
  background: #2196f3;
  color: #fff;
  font-size: 20px;
  font-weight: 700;
  border-radius: 16px;
  padding: 18px 48px;
  margin-bottom: 40px;
  box-shadow: 0 2px 8px rgba(33,150,243,0.12);
}
.home-bank-numbers {
  background: rgba(0,0,0,0.12);
  border-radius: 18px;
  padding: 24px 32px;
  margin-top: 24px;
  color: #fff;
}
.bank-numbers-title {
  font-size: 15px;
  color: #ffd166;
  margin-bottom: 16px;
  text-align: center;
  font-weight: 600;
}
.bank-numbers-list {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
  justify-content: center;
}
.bank-num {
  background: rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 12px 24px;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #fff;
  font-weight: 500;
}
.bank-icon {
  font-size: 20px;
}
.num {
  color: #ffd166;
  font-weight: 700;
  margin-left: 8px;
}
@media (max-width: 900px) {
  .home-header { padding: 24px 16px 0 16px; position: absolute; top: 0; }
  .home-main { margin-top: 120px; }
  .home-title { font-size: 32px; }
  .home-cta { padding: 12px 24px; font-size: 16px; }
  .home-bank-numbers { padding: 16px 8px; }
  .bank-num { padding: 8px 12px; font-size: 14px; }
}
</style>
<?php
$body_html = ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';

require 'includes/config.php';
header('Location:'.(logged_in()?'dashboard.php':'/cms/login.php'));
exit;
