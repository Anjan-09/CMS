<?php
require '../includes/config.php';
require_role(['super_admin']);

$status  =clean($_GET['status']??'');
$priority=clean($_GET['priority']??'');
$bank_id =(int)($_GET['bank']??0);

$sql="SELECT c.*,
  b.name AS bank_name,
  u.full_name AS cust_name, u.phone AS cust_phone,
  s.full_name AS staff_name,
  TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
  FROM complaints c
  LEFT JOIN banks b ON c.bank_id=b.id
  LEFT JOIN users u ON c.customer_id=u.id
  LEFT JOIN users s ON c.assigned_to=s.id
  WHERE 1=1";
$params=[];
if($status){  $sql.=" AND c.status=?";  $params[]=$status; }
if($priority){$sql.=" AND c.priority=?";$params[]=$priority;}
if($bank_id){ $sql.=" AND c.bank_id=?"; $params[]=$bank_id;}
$sql.=" ORDER BY FIELD(c.priority,'high','medium','low'), c.sla_deadline ASC LIMIT 200";
$q=$pdo->prepare($sql);$q->execute($params);$tickets=$q->fetchAll();

$allBanks=$pdo->query("SELECT id,name FROM banks ORDER BY name")->fetchAll();

$page_title='All Tickets';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div><h1>All Tickets</h1><p><?=count($tickets)?> tickets</p></div>
  </div>

  <form method="get" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
    <select name="status"   class="form-control" style="width:160px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach(['pending','in_progress','resolved','overdue'] as $s): ?>
        <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=strtoupper(str_replace('_',' ',$s))?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority" class="form-control" style="width:160px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Priority</option>
      <?php foreach(['high','medium','low'] as $p): ?>
        <option value="<?=$p?>" <?=$priority===$p?'selected':''?>><?=strtoupper($p)?></option>
      <?php endforeach; ?>
    </select>
    <select name="bank"     class="form-control" style="width:200px;padding:9px 13px" onchange="this.form.submit()">
      <option value="">All Banks</option>
      <?php foreach($allBanks as $ab): ?>
        <option value="<?=$ab['id']?>" <?=$bank_id==$ab['id']?'selected':''?>><?=clean($ab['name'])?></option>
      <?php endforeach; ?>
    </select>
    <?php if($status||$priority||$bank_id): ?>
      <a href="tickets.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Ticket #</th><th>Bank</th><th>Customer</th><th>Subject</th>
      <th>Priority</th><th>Status</th><th>Staff</th><th>SLA</th><th></th>
    </tr></thead>
    <tbody>
      <?php if(empty($tickets)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted)">No tickets found</td></tr>
      <?php endif; ?>
      <?php foreach($tickets as $t): ?>
      <tr class="row-<?=$t['priority']?>">
        <td><span class="fw-bold text-accent"><?=clean($t['ticket_no'])?></span></td>
        <td class="text-sm"><?=clean($t['bank_name'])?></td>
        <td>
          <div class="text-sm fw-bold"><?=clean($t['cust_name'])?></div>
          <div class="text-xs text-muted"><?=clean($t['cust_phone'])?></div>
        </td>
        <td class="td-wrap"><?=clean($t['subject'])?></td>
        <td><?=priority_badge($t['priority'])?></td>
        <td><?=status_badge($t['status'])?></td>
        <td class="text-sm"><?=clean($t['staff_name']??'—')?></td>
        <td><?=time_left_html($t['sla_deadline'],$t['status'])?></td>
        <td><a href="/cms/view_ticket.php?id=<?=$t['id']?>&from=admin" class="btn btn-xs btn-secondary">View</a></td>
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
