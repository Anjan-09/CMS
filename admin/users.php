<?php
require '../includes/config.php';
require_role(['super_admin']);

$role_f=clean($_GET['role']??'');
$sql="SELECT u.*,b.name AS bank_name FROM users u LEFT JOIN banks b ON u.bank_id=b.id WHERE 1=1";
$params=[];
if($role_f){ $sql.=" AND u.role=?"; $params[]=$role_f; }
$sql.=" ORDER BY u.created_at DESC LIMIT 300";
$q=$pdo->prepare($sql);$q->execute($params);$users=$q->fetchAll();

// Toggle active
if(isset($_GET['toggle'])&&isset($_GET['id'])){
    $tid=(int)$_GET['id'];
    $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND role!='super_admin'")->execute([$tid]);
    flash('success','User status updated.');
    header('Location:users.php'); exit;
}

$page_title='User Management';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div><h1>User Management</h1><p><?=count($users)?> users</p></div>
  </div>

  <form method="get" style="display:flex;gap:10px;margin-bottom:24px">
    <select name="role" class="form-control" style="width:200px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Roles</option>
      <?php foreach(['customer','bank_staff','bank_admin','super_admin'] as $r): ?>
        <option value="<?=$r?>" <?=$role_f===$r?'selected':''?>><?=strtoupper(str_replace('_',' ',$r))?></option>
      <?php endforeach; ?>
    </select>
    <?php if($role_f): ?><a href="users.php" class="btn btn-secondary">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Name</th><th>Email</th><th>Phone</th><th>Role</th>
      <th>Bank</th><th>Verified</th><th>Status</th><th>Joined</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach($users as $u): ?>
      <tr>
        <td class="fw-bold"><?=clean($u['full_name'])?></td>
        <td class="text-sm"><?=clean($u['email'])?></td>
        <td class="text-sm"><?=clean($u['phone'])?></td>
        <td><span class="badge badge-secondary text-xs"><?=strtoupper(str_replace('_',' ',$u['role']))?></span></td>
        <td class="text-sm"><?=clean($u['bank_name']??'—')?></td>
        <td><?=$u['email_verified']?'<span class="badge badge-success">✓</span>':'<span class="badge badge-warning">No</span>'?></td>
        <td><?=$u['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-danger">Disabled</span>'?></td>
        <td class="text-xs text-muted"><?=date('M j Y',strtotime($u['created_at']))?></td>
        <td>
          <?php if($u['role']!=='super_admin'): ?>
          <a href="users.php?toggle=1&id=<?=$u['id']?>" class="btn btn-xs btn-secondary"
             onclick="return confirmDo('Toggle this user?')"><?=$u['is_active']?'Disable':'Enable'?></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';
