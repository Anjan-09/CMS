<?php
require '../includes/config.php';
require_role(['bank_admin']);
$bid=(int)$_SESSION['bank_id'];
$uid=(int)$_SESSION['user_id'];

$errors=[];

// Add new staff
if(isset($_POST['action'])&&$_POST['action']==='add_staff'){
    csrf_verify();
    $name =clean($_POST['full_name']??'');
    $email=strtolower(trim($_POST['email']??''));
    $phone=trim($_POST['phone']??'');
    $pass =$_POST['password']??'';

    if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<6)
        $errors[]='All fields required. Password min 6 chars.';
    elseif(!preg_match('/^98\d{8}$/',$phone))
        $errors[]='Phone must be 10 digits starting with 98.';
    else{
        $chk=$pdo->prepare("SELECT id FROM users WHERE email=? OR phone=?");
        $chk->execute([$email,$phone]);
        if($chk->fetch()) $errors[]='Email or phone already exists.';
        else{
            $pdo->prepare("INSERT INTO users (full_name,email,phone,password,role,bank_id,email_verified,is_active)
              VALUES (?,?,?,?,'bank_staff',?,1,1)")
              ->execute([$name,$email,$phone,password_hash($pass,PASSWORD_DEFAULT),$bid]);
            log_activity($pdo,"Added staff: $email");
            flash('success',"Staff member $name added.");
            header('Location:staff.php');exit;
        }
    }
}

// Toggle active
if(isset($_GET['toggle'])&&isset($_GET['id'])){
    $tid=(int)$_GET['id'];
    $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND bank_id=? AND role='bank_staff'")
        ->execute([$tid,$bid]);
    header('Location:staff.php');exit;
}

$staff=$pdo->prepare("SELECT u.*,
  (SELECT COUNT(*) FROM complaints WHERE assigned_to=u.id AND status IN ('pending','in_progress')) AS open_tickets
  FROM users u WHERE u.bank_id=? AND u.role='bank_staff' ORDER BY u.full_name");
$staff->execute([$bid]);$staffList=$staff->fetchAll();

$page_title='Staff Management';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div>
      <h1>Staff Management</h1>
      <p><?=count($staffList)?> staff member<?=count($staffList)!=1?'s':''?></p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('add-modal').classList.add('open')">＋ Add Staff</button>
  </div>

  <?php if($errors): ?>
    <div class="alert alert-danger">
      <?php foreach($errors as $e) echo '• '.clean($e).'<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if(empty($staffList)): ?>
    <div class="card" style="text-align:center;padding:64px">
      <div style="font-size:56px;margin-bottom:16px">👥</div>
      <h3>No staff yet</h3>
      <p class="text-muted mt-8">Add staff members to start assigning tickets.</p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Name</th><th>Email</th><th>Phone</th>
      <th>Availability</th><th>Open Tickets</th><th>Account</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach($staffList as $s): ?>
      <tr>
        <td class="fw-bold"><?=clean($s['full_name'])?></td>
        <td class="text-sm"><?=clean($s['email'])?></td>
        <td class="text-sm"><?=clean($s['phone'])?></td>
        <td>
          <span class="badge <?=$s['staff_status']==='active'?'badge-success':'badge-secondary'?>">
            <?=strtoupper($s['staff_status']??'offline')?>
          </span>
        </td>
        <td>
          <span class="badge <?=$s['open_tickets']>5?'badge-danger':($s['open_tickets']>2?'badge-warning':'badge-info')?>">
            <?=$s['open_tickets']?>
          </span>
        </td>
        <td>
          <span class="badge <?=$s['is_active']?'badge-success':'badge-danger'?>">
            <?=$s['is_active']?'Active':'Disabled'?>
          </span>
        </td>
        <td>
          <a href="staff.php?toggle=1&id=<?=$s['id']?>" class="btn btn-xs btn-secondary"
             onclick="return confirmDo('Toggle account status?')">
            <?=$s['is_active']?'Disable':'Enable'?>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Add Staff Modal -->
<div class="modal-bg" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add Staff Member</div>
      <button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_staff">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" type="text" name="full_name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="form-group">
        <label class="form-label">Phone (98XXXXXXXX)</label>
        <input class="form-control" type="tel" name="phone" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Create Staff Account</button>
    </form>
  </div>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';
