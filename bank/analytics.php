<?php
require '../includes/config.php';
require_role(['bank_admin']);
$bid=(int)$_SESSION['bank_id'];

$stats=$pdo->prepare("SELECT
  COUNT(*) total,
  SUM(status='pending') pend,
  SUM(status='in_progress') inp,
  SUM(status='resolved') res,
  SUM(status='overdue') ov,
  SUM(status='resolved' AND resolved_at<=sla_deadline) within_sla,
  SUM(priority='high') hi,
  SUM(priority='medium') med,
  SUM(priority='low') lo
 FROM complaints WHERE bank_id=?");
$stats->execute([$bid]);$s=$stats->fetch();

$sla_pct = $s['res']>0 ? round($s['within_sla']/$s['res']*100,1):0;

// Daily tickets for the last 14 days
$daily=$pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS n
  FROM complaints WHERE bank_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 14 DAY)
  GROUP BY DATE(created_at) ORDER BY d");
$daily->execute([$bid]);$daily=$daily->fetchAll(PDO::FETCH_KEY_PAIR);

// Staff load
$staff_load=$pdo->prepare("SELECT u.full_name,u.staff_status,
  SUM(c.status IN ('pending','in_progress')) AS open,
  SUM(c.status='resolved') AS done,
  COUNT(c.id) AS total
  FROM users u
  LEFT JOIN complaints c ON c.assigned_to=u.id AND c.bank_id=?
  WHERE u.bank_id=? AND u.role='bank_staff'
  GROUP BY u.id ORDER BY open DESC");
$staff_load->execute([$bid,$bid]);$staffRows=$staff_load->fetchAll();

$page_title='Analytics';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header">
    <h1>Analytics Dashboard</h1>
    <p>Performance overview for your bank</p>
  </div>

  <!-- Main stats -->
  <div class="stats-grid">
    <div class="stat-box ab-primary"><div class="num"><?=$s['total']?></div><div class="lbl">Total Tickets</div></div>
    <div class="stat-box ab-success"><div class="num"><?=$s['res']?></div><div class="lbl">Resolved</div></div>
    <div class="stat-box ab-danger" ><div class="num"><?=$s['ov']?></div><div class="lbl">Overdue</div></div>
    <div class="stat-box ab-warning"><div class="num"><?=$s['pend']?></div><div class="lbl">Pending</div></div>
    <div class="stat-box ab-info"   ><div class="num"><?=$s['inp']?></div><div class="lbl">In Progress</div></div>
  </div>

  <div class="grid-2">
    <!-- SLA Compliance -->
    <div class="card">
      <div class="card-header">SLA Compliance</div>
      <div style="text-align:center;padding:16px 0">
        <div style="font-family:'Syne',sans-serif;font-size:56px;font-weight:800;
          color:<?=$sla_pct>=80?'var(--success)':($sla_pct>=50?'var(--warning)':'var(--danger)')?>">
          <?=$sla_pct?>%
        </div>
        <div class="text-muted text-sm mt-8"><?=$s['within_sla']?> of <?=$s['res']?> resolved within deadline</div>
      </div>
      <div class="progress">
        <div class="progress-bar" style="width:<?=$sla_pct?>%;background:<?=$sla_pct>=80?'var(--success)':($sla_pct>=50?'var(--warning)':'var(--danger)')?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:20px;text-align:center">
        <div style="background:var(--card2);padding:12px;border-radius:8px">
          <div style="font-size:22px;font-weight:700;color:var(--danger)"><?=$s['hi']?></div>
          <div class="text-xs text-muted">High Priority</div>
        </div>
        <div style="background:var(--card2);padding:12px;border-radius:8px">
          <div style="font-size:22px;font-weight:700;color:var(--warning)"><?=$s['med']?></div>
          <div class="text-xs text-muted">Medium</div>
        </div>
        <div style="background:var(--card2);padding:12px;border-radius:8px">
          <div style="font-size:22px;font-weight:700;color:var(--success)"><?=$s['lo']?></div>
          <div class="text-xs text-muted">Low</div>
        </div>
      </div>
    </div>

    <!-- Last 14 days chart -->
    <div class="card">
      <div class="card-header">Tickets — Last 14 Days</div>
      <?php
      $max=max(array_merge([1],array_values($daily)));
      $labels=[];
      for($i=13;$i>=0;$i--){
        $d=date('Y-m-d',strtotime("-{$i} days"));
        $labels[$d]=$daily[$d]??0;
      }
      ?>
      <div style="display:flex;align-items:flex-end;gap:6px;height:140px;padding-top:10px">
        <?php foreach($labels as $d=>$n): ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <div style="font-size:10px;color:var(--muted)"><?=$n>0?$n:''?></div>
            <div style="width:100%;border-radius:4px 4px 0 0;
              background:<?=$n>0?'var(--accent)':'var(--card2)'?>;
              height:<?=max(4,round($n/$max*100))?>px;
              transition:.3s;cursor:default" title="<?=date('M j',strtotime($d))?>: $n tickets"></div>
            <div style="font-size:9px;color:var(--muted)"><?=date('j',strtotime($d))?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Staff load -->
  <?php if($staffRows): ?>
  <div class="card mt-24">
    <div class="card-header">Staff Workload</div>
    <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Staff Member</th><th>Status</th><th>Open Tickets</th><th>Resolved</th><th>Total</th><th>Load</th>
      </tr></thead>
      <tbody>
        <?php foreach($staffRows as $sr): ?>
        <tr>
          <td class="fw-bold"><?=clean($sr['full_name'])?></td>
          <td>
            <span class="badge <?=$sr['staff_status']==='active'?'badge-success':'badge-secondary'?>">
              <?=strtoupper($sr['staff_status']??'offline')?>
            </span>
          </td>
          <td>
            <span class="badge <?=$sr['open']>5?'badge-danger':($sr['open']>2?'badge-warning':'badge-info')?>"><?=$sr['open']?></span>
          </td>
          <td><?=$sr['done']?></td>
          <td><?=$sr['total']?></td>
          <td style="min-width:100px">
            <?php $pct=$sr['total']>0?round($sr['done']/$sr['total']*100):0; ?>
            <div class="progress"><div class="progress-bar" style="width:<?=$pct?>%;background:var(--success)"></div></div>
            <div class="text-xs text-muted mt-8"><?=$pct?>% resolved</div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$body_html=ob_get_clean();
require '../includes/layout.php';
echo $body_html;
echo '</body></html>';
