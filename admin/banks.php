<?php
require '../includes/config.php';
require_role(['super_admin']);

// Verify bank
if(isset($_GET['verify'])){
    $bid=(int)$_GET['verify'];
    $pdo->prepare("UPDATE banks SET is_verified=1 WHERE id=?")->execute([$bid]);
    log_activity($pdo,"Bank verified id=$bid");
    flash('success','Bank verified successfully.');
    header('Location:banks.php');exit;
}

// Delete/disable bank (only available for unverified or manual removal)
if(isset($_GET['delete'])){
    $bid=(int)$_GET['delete'];
    // disable any users linked to this bank (admins/staff)
    $pdo->prepare("UPDATE users SET is_active=0 WHERE bank_id=?")->execute([$bid]);
    // remove the bank record; complaints will cascade if FK configured
    $pdo->prepare("DELETE FROM banks WHERE id=?")->execute([$bid]);
    log_activity($pdo,"Bank deleted id=$bid (employees disabled)");
    flash('success','Bank deleted and related employees disabled.');
    header('Location:banks.php');exit;
}

// Add bank
$errors=[];
if(isset($_POST['action'])&&$_POST['action']==='add_bank'){
    csrf_verify();
    $name =clean($_POST['name']??'');
    $code =strtoupper(trim($_POST['code']??''));
    $type =$_POST['type']??'';
    $email=clean($_POST['email']??'');
    $phone=clean($_POST['phone']??'');

    if(!$name||!$code||!$type||!$email) $errors[]='All fields required.';
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Invalid email.';
    else{
        $chk=$pdo->prepare("SELECT id FROM banks WHERE code=?");$chk->execute([$code]);
        if($chk->fetch()) $errors[]='Bank code already exists.';
        else{
            $pdo->prepare("INSERT INTO banks (name,code,type,email,phone,is_verified) VALUES (?,?,?,?,?,1)")
                ->execute([$name,$code,$type,$email,$phone]);
            log_activity($pdo,"Bank added: $name");
            flash('success','Bank added.');
            header('Location:banks.php');exit;
        }
    }
}

// Add bank admin account
if(isset($_POST['action'])&&$_POST['action']==='add_admin'){
    csrf_verify();
    $bank_id=(int)$_POST['bank_id'];
    $fname  =clean($_POST['full_name']??'');
    $email  =strtolower(trim($_POST['email']??''));
    $phone  =trim($_POST['phone']??'');
    $pass   =$_POST['password']??'';

    if(!$bank_id||!$fname||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<6)
        $errors[]='All fields required.';
    else{
        $chk=$pdo->prepare("SELECT id FROM users WHERE email=? OR phone=?");$chk->execute([$email,$phone]);
        if($chk->fetch()) $errors[]='Email or phone already exists.';
        else{
            $pdo->prepare("INSERT INTO users (full_name,email,phone,password,role,bank_id,email_verified,is_active)
              VALUES (?,?,?,?,'bank_admin',?,1,1)")
              ->execute([$fname,$email,$phone,password_hash($pass,PASSWORD_DEFAULT),$bank_id]);
            log_activity($pdo,"Bank admin created for bank_id=$bank_id: $email");
            flash('success',"Bank admin account created for ".clean($fname).'.');
            header('Location:banks.php');exit;
        }
    }
}

$banks=$pdo->query("SELECT b.*,
  (SELECT COUNT(*) FROM users WHERE bank_id=b.id AND role='bank_admin') AS admin_count,
  (SELECT COUNT(*) FROM users WHERE bank_id=b.id AND role='bank_staff') AS staff_count,
  (SELECT COUNT(*) FROM complaints WHERE bank_id=b.id) AS ticket_count
  FROM banks b ORDER BY b.is_verified ASC, b.name")->fetchAll();

$page_title='Manage Banks';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div>
      <h1>Manage Banks</h1>
      <p><?=count($banks)?> banks registered</p>
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('admin-modal').classList.add('open')">Add Bank Admin</button>
      <button class="btn btn-primary" onclick="document.getElementById('bank-modal').classList.add('open')">＋ Add Bank</button>
    </div>
  </div>

  <?php if($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e) echo '• '.clean($e).'<br>'; ?></div>
  <?php endif; ?>

  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Bank Name</th><th>Code</th><th>Type</th><th>Contact</th>
      <th>Status</th><th>Admins</th><th>Staff</th><th>Tickets</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach($banks as $b): ?>
      <tr>
        <td class="fw-bold"><?=clean($b['name'])?></td>
        <td><code style="background:var(--card2);padding:2px 8px;border-radius:4px"><?=clean($b['code'])?></code></td>
        <td class="text-sm"><?=ucfirst(str_replace('_',' ',$b['type']))?></td>
        <td><div class="text-sm"><?=clean($b['email'])?></div><div class="text-xs text-muted"><?=clean($b['phone'])?></div></td>
        <td>
          <?php if($b['is_verified']): ?>
            <span class="badge badge-success">✓ Verified</span>
          <?php else: ?>
            <span class="badge badge-warning">Unverified</span>
          <?php endif; ?>
        </td>
        <td><?=$b['admin_count']?></td>
        <td><?=$b['staff_count']?></td>
        <td><?=$b['ticket_count']?></td>
        <td>
          <?php if(!$b['is_verified']): ?>
            <a href="banks.php?verify=<?=$b['id']?>" class="btn btn-xs btn-success"
               onclick="return confirmDo('Verify this bank?')">Verify</a>
            <a href="banks.php?delete=<?=$b['id']?>" class="btn btn-xs btn-danger"
               onclick="return confirmDo('Delete this bank?\nThis will disable any associated staff/admin accounts.')">Delete</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Add Bank Modal -->
<div class="modal-bg" id="bank-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add New Bank</div>
      <button class="modal-close" onclick="document.getElementById('bank-modal').classList.remove('open')">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_bank">
      <div class="form-group">
        <label class="form-label">Bank Name</label>
        <input class="form-control" name="name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Code (unique)</label>
          <input class="form-control" name="code" placeholder="e.g. KHALTI" required style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select class="form-control" name="type" required>
            <option value="">— Select —</option>
            <option value="digital_wallet">Digital Wallet</option>
            <option value="bank">Bank</option>
            <option value="payment_gateway">Payment Gateway</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Contact Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="form-group">
        <label class="form-label">Contact Phone</label>
        <input class="form-control" name="phone">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Add Bank</button>
    </form>
  </div>
</div>

<!-- Add Bank Admin Modal -->
<div class="modal-bg" id="admin-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Create Bank Admin Account</div>
      <button class="modal-close" onclick="document.getElementById('admin-modal').classList.remove('open')">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_admin">
      <div class="form-group">
        <label class="form-label">Bank</label>
        <select class="form-control" name="bank_id" required>
          <option value="">— Select Bank —</option>
          <?php foreach($banks as $b): ?>
            <option value="<?=$b['id']?>"><?=clean($b['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="full_name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="form-group">
        <label class="form-label">Phone (98XXXXXXXX)</label>
        <input class="form-control" name="phone">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Create Admin Account</button>
    </form>
  </div>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';
