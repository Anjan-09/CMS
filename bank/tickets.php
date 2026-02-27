<?php
require '../includes/config.php';
require_role(['bank_staff','bank_admin']);
$uid=(int)$_SESSION['user_id'];
$bid=(int)$_SESSION['bank_id'];
$role=$_SESSION['role'];

$status=clean($_GET['status']??'');
$priority=clean($_GET['priority']??'');

$sql="SELECT c.*,
  u.full_name AS cust_name, u.phone AS cust_phone,
  s.full_name AS staff_name,
  TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
  FROM complaints c
  LEFT JOIN users u ON c.customer_id=u.id
  LEFT JOIN users s ON c.assigned_to=s.id
  WHERE c.bank_id=:bid";
$params=[':bid'=>$bid];

// Staff only see assigned tickets
if($role==='bank_staff'){ $sql.=" AND c.assigned_to=:uid"; $params[':uid']=$uid; }
if($status){ $sql.=" AND c.status=:s"; $params[':s']=$status; }
if($priority){ $sql.=" AND c.priority=:p"; $params[':p']=$priority; }
$sql.=" ORDER BY FIELD(c.priority,'high','medium','low'), c.sla_deadline ASC";
$q=$pdo->prepare($sql);$q->execute($params);$tickets=$q->fetchAll();

$page_title='Tickets';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header">
    <h1><?=$role==='bank_staff'?'My Assigned Tickets':'All Bank Tickets'?></h1>
    <p><?=count($tickets)?> ticket<?=count($tickets)!=1?'s':''?></p>
  </div>

  <!-- Filters -->
  <form method="get" style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <select name="status" class="form-control" style="width:170px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach(['pending','in_progress','resolved','overdue'] as $s): ?>
        <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=strtoupper(str_replace('_',' ',$s))?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="form-control" style="width:170px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Priorities</option>
      <?php foreach(['high','medium','low'] as $p): ?>
        <option value="<?=$p?>" <?=$priority===$p?'selected':''?>><?=strtoupper($p)?></option>
      <?php endforeach; ?>
    </select>
    <?php if($status||$priority): ?>
      <a href="tickets.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <?php if(empty($tickets)): ?>
    <div class="card" style="text-align:center;padding:64px">
      <div style="font-size:56px;margin-bottom:16px">🎉</div>
      <h3>No tickets matching filters</h3>
    </div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Ticket #</th><th>Customer</th><th>Subject</th>
      <th>Priority</th><th>Status</th>
      <?php if($role==='bank_admin'): ?><th>Staff</th><?php endif; ?>
      <th>SLA</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach($tickets as $t): ?>
      <tr class="row-<?=$t['priority']?>">
        <td><span class="fw-bold text-accent"><?=clean($t['ticket_no'])?></span></td>
        <td>
          <div class="fw-bold text-sm"><?=clean($t['cust_name'])?></div>
          <div class="text-xs text-muted"><?=clean($t['cust_phone'])?></div>
        </td>
        <td class="td-wrap"><?=clean($t['subject'])?></td>
        <td><?=priority_badge($t['priority'])?></td>
        <td><?=status_badge($t['status'])?></td>
        <?php if($role==='bank_admin'): ?>
          <td class="text-sm"><?=clean($t['staff_name']??'Unassigned')?></td>
        <?php endif; ?>
        <td><?=time_left_html($t['sla_deadline'],$t['status'])?></td>
        <td><a href="../view_ticket.php?id=<?=$t['id']?>" class="btn btn-xs btn-secondary">Manage</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';
