<?php
// layout.php  – called with:  $page_title, $body_html  already set
$site_name = get_setting($pdo,'site_name','Complaint Management System');
$accent    = get_setting($pdo,'theme_accent','#e94560');
$primary   = get_setting($pdo,'theme_primary','#1a1a2e');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page_title??$site_name) ?> – <?= $site_name ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<?php
// Determine CSS path based on directory depth
$is_in_subdir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/bank/') !== false);
$css_path = $is_in_subdir ? '../assets/css/style.css' : 'assets/css/style.css';
?>
<link rel="stylesheet" href="<?= $css_path ?>">
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
:root {
  --accent: <?= $accent ?>;
  --primary: <?= $primary ?>;
}
</style>
</head>
<body>

<?php if(logged_in()): ?>
<!-- Quote Banner -->
<div class="quote-banner">
  <p class="quote-text">Speak. Report. Resolve.</p>
</div>

<?php
$role = $_SESSION['role'];
$fname = $_SESSION['full_name'] ?? 'User';
$initial = strtoupper(substr($fname,0,1));
$cur = basename($_SERVER['PHP_SELF']);
// Determine path prefix (add ../ for subdirectory pages)
$is_in_subdir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/bank/') !== false);
$path_prefix = $is_in_subdir ? '../' : '';
?>
<nav class="topbar">
  <a class="topbar-brand" href="<?= $path_prefix ?>dashboard.php">
    <span class="topbar-brand-name"><?= $site_name ?></span>
  </a>
  <div class="topbar-nav">
    <a href="<?= $path_prefix ?>dashboard.php" class="<?=$cur=='dashboard.php'?'active':''?>">Dashboard</a>
    <?php if($role==='customer'): ?>
      <a href="<?= $path_prefix ?>submit_complaint.php" class="<?=$cur=='submit_complaint.php'?'active':''?>">New Complaint</a>
      <a href="<?= $path_prefix ?>my_complaints.php" class="<?=$cur=='my_complaints.php'?'active':''?>">My Tickets</a>
    <?php elseif(in_array($role,['bank_admin','bank_staff'])): ?>
      <a href="<?= $path_prefix ?>bank/tickets.php" class="<?=$cur=='tickets.php'?'active':''?>">Tickets</a>
      <?php if($role==='bank_admin'): ?>
        <a href="<?= $path_prefix ?>bank/staff.php" class="<?=$cur=='staff.php'?'active':''?>">Staff</a>
        <a href="<?= $path_prefix ?>bank/analytics.php" class="<?=$cur=='analytics.php'?'active':''?>">Analytics</a>
      <?php endif; ?>
      <a href="<?= $path_prefix ?>team_chat.php" class="<?=$cur=='team_chat.php'?'active':''?>">Chat</a>
    <?php elseif($role==='super_admin'): ?>
      <a href="<?= $path_prefix ?>admin/banks.php" class="<?=$cur=='banks.php'?'active':''?>">Banks</a>
      <a href="<?= $path_prefix ?>admin/tickets.php" class="<?=$cur=='tickets.php'?'active':''?>">All Tickets</a>
      <a href="<?= $path_prefix ?>admin/users.php" class="<?=$cur=='users.php'?'active':''?>">Users</a>
      <a href="<?= $path_prefix ?>admin/settings.php" class="<?=$cur=='settings.php'?'active':''?>">Settings</a>
      <a href="<?= $path_prefix ?>admin/logs.php" class="<?=$cur=='logs.php'?'active':''?>">Logs</a>
    <?php endif; ?>
  </div>
  <div class="flex-center" style="gap:10px">
    <div class="topbar-user">
      <div class="avatar"><?= $initial ?></div>
      <span><?= clean($fname) ?></span>
      <span class="badge badge-secondary" style="font-size:10px"><?= str_replace('_',' ',strtoupper($role)) ?></span>
    </div>
    <a href="<?= $path_prefix ?>logout.php" class="btn-logout">Logout</a>
  </div>
</nav>
<?php endif; ?>

<div class="page-content">
<!-- Flash messages -->
<?php
foreach(['success','danger','warning','info'] as $ft){
    $f=flash($ft);
    if($f) echo '<div class="alert alert-'.$ft.'" style="margin:12px 24px 0"><span>'.$f['msg'].'</span><button class="alert-close" onclick="this.parentElement.remove()">×</button></div>';
}
?>

<script>
// Countdown timers
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.countdown[data-sec]').forEach(el=>{
    let s=+el.dataset.sec;
    el.dataset.sec='';
    const tick=()=>{
      if(s<=0){el.textContent='OVERDUE';el.className='badge badge-danger blink';return}
      const h=String(Math.floor(s/3600)).padStart(2,'0');
      const m=String(Math.floor((s%3600)/60)).padStart(2,'0');
      const sc=String(s%60).padStart(2,'0');
      el.textContent=`${h}:${m}:${sc}`;
      if(s<3600)el.className='badge badge-danger countdown';
      else if(s<7200)el.className='badge badge-warning countdown';
      else el.className='badge badge-info countdown';
      s--;setTimeout(tick,1000);
    };tick();
  });
  // Auto-dismiss flash alerts
  document.querySelectorAll('.alert').forEach(a=>{
    setTimeout(()=>a.style.transition='opacity .5s', 4500);
    setTimeout(()=>{a.style.opacity='0';setTimeout(()=>a.remove(),500)},5000);
  });
  // Logout confirmation for all logout buttons
  document.querySelectorAll('.btn-logout').forEach(btn=>{
    btn.addEventListener('click', function(e){
      if(!confirm('Are you sure you want to logout?')) e.preventDefault();
    });
  });
});
function confirmDo(msg){return confirm(msg??'Are you sure?')}
</script>

</div>

</body></html>
