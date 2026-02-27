<?php
require 'includes/config.php';
require_login();

$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$bid  = (int)($_SESSION['bank_id']??0);

// ── CUSTOMER ─────────────────────────────────────────────────────────────────
if($role==='customer'){
    $rows=$pdo->prepare("SELECT c.*,b.name AS bank_name,
           TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
           FROM complaints c LEFT JOIN banks b ON c.bank_id=b.id
           WHERE c.customer_id=? ORDER BY c.created_at DESC LIMIT 10");
    $rows->execute([$uid]);$tickets=$rows->fetchAll();

    $st=$pdo->prepare("SELECT
      COUNT(*) total,
      SUM(status='pending') pend,
      SUM(status='in_progress') inp,
      SUM(status='resolved') res,
      SUM(status='overdue') ov
     FROM complaints WHERE customer_id=?");
    $st->execute([$uid]);$stats=$st->fetch();
}

// ── BANK STAFF ────────────────────────────────────────────────────────────────
if($role==='bank_staff'){
    // Update their own availability via toggle
    if(isset($_POST['toggle_status'])){
        csrf_verify();
        $ns=$_POST['toggle_status']==='active'?'active':'offline';
        $pdo->prepare("UPDATE users SET staff_status=? WHERE id=?")->execute([$ns,$uid]);
        $_SESSION['staff_status']=$ns;
        log_activity($pdo,"Staff status → $ns");
        header('Location:dashboard.php');exit;
    }
    $me=$pdo->prepare("SELECT * FROM users WHERE id=?")->execute([$uid])?
        $pdo->prepare("SELECT * FROM users WHERE id=?")->execute([$uid])&&false:null;
    $urow=$pdo->prepare("SELECT * FROM users WHERE id=?");
    $urow->execute([$uid]);$me=$urow->fetch();
    $_SESSION['staff_status']=$me['staff_status'];

    $rows=$pdo->prepare("SELECT c.*,b.name AS bank_name,u.full_name AS cust_name,u.phone AS cust_phone,
           TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
           FROM complaints c LEFT JOIN banks b ON c.bank_id=b.id
           LEFT JOIN users u ON c.customer_id=u.id
           WHERE c.assigned_to=?
           ORDER BY FIELD(c.priority,'high','medium','low'), c.sla_deadline ASC LIMIT 15");
    $rows->execute([$uid]);$tickets=$rows->fetchAll();

    $st=$pdo->prepare("SELECT
      COUNT(*) total,
      SUM(status='pending') pend,
      SUM(status='in_progress') inp,
      SUM(status='resolved') res,
      SUM(status='overdue') ov
     FROM complaints WHERE assigned_to=?");
    $st->execute([$uid]);$stats=$st->fetch();
}

// ── BANK ADMIN ────────────────────────────────────────────────────────────────
if($role==='bank_admin'){
    $rows=$pdo->prepare("SELECT c.*,b.name AS bank_name,u.full_name AS cust_name,u.phone AS cust_phone,
           s.full_name AS staff_name,
           TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
           FROM complaints c LEFT JOIN banks b ON c.bank_id=b.id
           LEFT JOIN users u ON c.customer_id=u.id
           LEFT JOIN users s ON c.assigned_to=s.id
           WHERE c.bank_id=?
           ORDER BY FIELD(c.priority,'high','medium','low'), c.sla_deadline ASC LIMIT 15");
    $rows->execute([$bid]);$tickets=$rows->fetchAll();

    $st=$pdo->prepare("SELECT
      COUNT(*) total,
      SUM(status='resolved') res,
      SUM(status='overdue') ov,
      SUM(status='in_progress') inp,
      SUM(status='pending') pend,
      SUM(status='resolved' AND resolved_at<=sla_deadline) within_sla
     FROM complaints WHERE bank_id=?");
    $st->execute([$bid]);$stats=$st->fetch();
    $sla_pct = $stats['res']>0 ? round($stats['within_sla']/$stats['res']*100,1):0;
}

// ── SUPER ADMIN ───────────────────────────────────────────────────────────────
if($role==='super_admin'){
    $rows=$pdo->query("SELECT c.*,b.name AS bank_name,u.full_name AS cust_name,
           s.full_name AS staff_name,
           TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
           FROM complaints c LEFT JOIN banks b ON c.bank_id=b.id
           LEFT JOIN users u ON c.customer_id=u.id
           LEFT JOIN users s ON c.assigned_to=s.id
           ORDER BY c.created_at DESC LIMIT 20");
    $tickets=$rows->fetchAll();

    $st=$pdo->query("SELECT
      COUNT(*) total,
      SUM(status='resolved') res,
      SUM(status='overdue') ov,
      SUM(status='pending') pend,
      SUM(status='in_progress') inp,
      (SELECT COUNT(*) FROM banks) bank_count,
      (SELECT COUNT(*) FROM users WHERE role='customer') cust_count
     FROM complaints")->fetch();
    $stats=$st;

    $pending_banks=$pdo->query("SELECT id,name,code FROM banks WHERE is_verified=0")->fetchAll();
}

$page_title='Dashboard';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div>
      <h1>
        <?php
        $greet=date('H')<12?'Good morning':( date('H')<17?'Good afternoon':'Good evening');
        echo $greet.', '.clean(explode(' ',$_SESSION['full_name'])[0]).' 👋';
        ?>
      </h1>
      <p>Here's what's happening today</p>
    </div>
    <?php if($role==='customer'): ?>
      <a href="submit_complaint.php" class="btn btn-primary">＋ New Complaint</a>
    <?php endif; ?>
    <?php if($role==='bank_staff'): ?>
      <form method="post" style="display:flex;align-items:center;gap:12px" onsubmit="return confirmDo('Change availability status?')">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <span class="text-sm text-muted">Availability</span>
        <label class="toggle">
          <input type="checkbox" name="toggle_status"
            value="<?=$me['staff_status']==='active'?'offline':'active'?>"
            onchange="if(confirmDo('Change availability status?')) this.form.submit(); else this.checked=!this.checked"
            <?=$me['staff_status']==='active'?'checked':''?>>
          <span class="toggle-slider"></span>
        </label>
        <span class="badge <?=$me['staff_status']==='active'?'badge-success':'badge-secondary'?>">
          <?=strtoupper($me['staff_status']??'offline')?>
        </span>
      </form>
    <?php endif; ?>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <?php if(in_array($role,['customer','bank_staff'])): ?>
      <div class="stat-box ab-primary"><div class="num"><?=$stats['total']?></div><div class="lbl">Total Tickets</div><div class="ico">🎫</div></div>
      <div class="stat-box ab-warning"><div class="num"><?=$stats['pend']?></div><div class="lbl">Pending</div><div class="ico">⏳</div></div>
      <div class="stat-box ab-info"   ><div class="num"><?=$stats['inp']?></div><div class="lbl">In Progress</div><div class="ico">🔄</div></div>
      <div class="stat-box ab-success"><div class="num"><?=$stats['res']?></div><div class="lbl">Resolved</div><div class="ico">✅</div></div>
      <div class="stat-box ab-danger" ><div class="num"><?=$stats['ov']?></div><div class="lbl">Overdue</div><div class="ico">🚨</div></div>
    <?php elseif($role==='bank_admin'): ?>
      <div class="stat-box ab-primary"><div class="num"><?=$stats['total']?></div><div class="lbl">Total Tickets</div><div class="ico">🎫</div></div>
      <div class="stat-box ab-success"><div class="num"><?=$stats['res']?></div><div class="lbl">Resolved</div><div class="ico">✅</div></div>
      <div class="stat-box ab-danger" ><div class="num"><?=$stats['ov']?></div><div class="lbl">Overdue</div><div class="ico">🚨</div></div>
      <div class="stat-box ab-warning"><div class="num"><?=$sla_pct?>%</div><div class="lbl">SLA Compliance</div><div class="ico">📊</div></div>
      <div class="stat-box ab-info"   ><div class="num"><?=$stats['inp']?></div><div class="lbl">In Progress</div><div class="ico">🔄</div></div>
    <?php else: /* super admin */ ?>
      <div class="stat-box ab-primary"><div class="num"><?=$stats['total']?></div><div class="lbl">Total Tickets</div><div class="ico">🎫</div></div>
      <div class="stat-box ab-success"><div class="num"><?=$stats['bank_count']?></div><div class="lbl">Banks</div><div class="ico">🏦</div></div>
      <div class="stat-box ab-info"   ><div class="num"><?=$stats['cust_count']?></div><div class="lbl">Customers</div><div class="ico">👥</div></div>
      <div class="stat-box ab-success"><div class="num"><?=$stats['res']?></div><div class="lbl">Resolved</div><div class="ico">✅</div></div>
      <div class="stat-box ab-danger" ><div class="num"><?=$stats['ov']?></div><div class="lbl">Overdue</div><div class="ico">🚨</div></div>
    <?php endif; ?>
  </div>

  <!-- PENDING BANKS (super admin only) -->
  <?php if($role==='super_admin' && $pending_banks): ?>
  <div class="card mb-24">
    <div class="card-header">
      ⚠ Banks Awaiting Verification
      <a href="/cms/admin/banks.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php foreach($pending_banks as $pb): ?>
      <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div>
          <span class="fw-bold"><?=clean($pb['name'])?></span>
          <span class="text-muted text-sm"> (<?=clean($pb['code'])?>)</span>
        </div>
        <a href="/cms/admin/banks.php?verify=<?=$pb['id']?>" class="btn btn-sm btn-success"
           onclick="return confirmDo('Verify this bank?')">✓ Verify</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- RECENT TICKETS TABLE -->
  <div class="card">
    <div class="card-header">
      <?= $role==='customer' ? 'Recent Tickets' : 'Tickets Overview' ?>
      <?php if($role!=='customer'): ?>
        <a href="<?=$role==='super_admin'?'/cms/admin/tickets.php':'/cms/bank/tickets.php'?>" class="btn btn-sm btn-secondary">View All</a>
      <?php endif; ?>
    </div>

    <?php if(empty($tickets)): ?>
      <div style="text-align:center;padding:48px;color:var(--muted)">
        <div style="font-size:48px;margin-bottom:14px">📭</div>
        <div class="fw-bold">No tickets yet</div>
        <?php if($role==='customer'): ?>
          <a href="submit_complaint.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex">Submit First Complaint</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Ticket #</th>
        <?php if($role!=='customer'): ?><th>Customer</th><?php endif; ?>
        <th>Bank</th>
        <th>Subject</th>
        <th>Priority</th>
        <th>Status</th>
        <th>SLA Deadline</th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach($tickets as $t): ?>
        <tr class="row-<?=$t['priority']?>">
          <td><span class="fw-bold text-accent"><?=clean($t['ticket_no'])?></span></td>
          <?php if($role!=='customer'): ?>
            <td><div><?=clean($t['cust_name']??'—')?></div><div class="text-xs text-muted"><?=clean($t['cust_phone']??'')?></div></td>
          <?php endif; ?>
          <td class="text-sm"><?=clean($t['bank_name']??'—')?></td>
          <td class="td-wrap"><?=clean($t['subject'])?></td>
          <td><?=priority_badge($t['priority'])?></td>
          <td><?=status_badge($t['status'])?></td>
          <td><?=time_left_html($t['sla_deadline'],$t['status'])?></td>
          <td><a href="view_ticket.php?id=<?=$t['id']?>" class="btn btn-xs btn-secondary">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$body_html = ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
