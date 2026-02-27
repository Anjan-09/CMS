<?php
require 'includes/config.php';
require_role(['customer']);
$uid=$_SESSION['user_id'];

// Filters
$status=clean($_GET['status']??'');
$priority=clean($_GET['priority']??'');
$sql="SELECT c.*,b.name AS bank_name,
     TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
     FROM complaints c LEFT JOIN banks b ON c.bank_id=b.id
     WHERE c.customer_id=:uid";
$params=[':uid'=>$uid];
if($status){ $sql.=" AND c.status=:s"; $params[':s']=$status; }
if($priority){ $sql.=" AND c.priority=:p"; $params[':p']=$priority; }
$sql.=" ORDER BY FIELD(c.priority,'high','medium','low'), c.sla_deadline ASC";
$q=$pdo->prepare($sql);$q->execute($params);$tickets=$q->fetchAll();

$page_title='My Complaints';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div>
      <h1>My Tickets</h1>
      <p><?=count($tickets)?> ticket<?=count($tickets)!=1?'s':''?> found</p>
    </div>
    <a href="submit_complaint.php" class="btn btn-primary">＋ New Complaint</a>
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
      <a href="my_complaints.php" class="btn btn-secondary">Clear Filters</a>
    <?php endif; ?>
  </form>

  <?php if(empty($tickets)): ?>
    <div class="card" style="text-align:center;padding:64px">
      <div style="font-size:56px;margin-bottom:16px">📭</div>
      <h3>No tickets found</h3>
      <p class="text-muted mt-8">Submit a complaint to get started.</p>
      <a href="submit_complaint.php" class="btn btn-primary" style="margin-top:20px;display:inline-flex">Submit Complaint</a>
    </div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr>
      <th>Ticket #</th><th>Bank</th><th>Subject</th><th>Priority</th>
      <th>Status</th><th>SLA Deadline</th><th>Submitted</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach($tickets as $t): ?>
      <tr class="row-<?=$t['priority']?>">
        <td><span class="fw-bold text-accent"><?=clean($t['ticket_no'])?></span></td>
        <td class="text-sm"><?=clean($t['bank_name'])?></td>
        <td class="td-wrap"><?=clean($t['subject'])?></td>
        <td><?=priority_badge($t['priority'])?></td>
        <td><?=status_badge($t['status'])?></td>
        <td><?=time_left_html($t['sla_deadline'],$t['status'])?></td>
        <td class="text-xs text-muted"><?=date('M j Y',strtotime($t['created_at']))?></td>
        <td><a href="view_ticket.php?id=<?=$t['id']?>" class="btn btn-xs btn-secondary">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php
$body_html=ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
