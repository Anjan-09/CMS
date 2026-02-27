<?php
require '../includes/config.php';
require_role(['super_admin']);

$logs=$pdo->query("SELECT al.*,u.full_name,u.email FROM activity_logs al
  LEFT JOIN users u ON al.user_id=u.id
  ORDER BY al.created_at DESC LIMIT 300")->fetchAll();

$page_title='Activity Logs';
ob_start();
?>
<div class="page-wrap">
  <div class="page-header flex-between">
    <div><h1>Activity Logs</h1><p>Last <?=count($logs)?> events</p></div>
    <a href="logs.php?clear=1" class="btn btn-danger btn-sm"
       onclick="return confirmDo('Clear all logs?')">Clear Logs</a>
  </div>

  <?php
  if(isset($_GET['clear'])){
      $pdo->exec("DELETE FROM activity_logs");
      flash('success','Logs cleared.');
      header('Location:logs.php');exit;
  }
  ?>

  <div class="table-wrap">
  <table>
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>IP</th></tr></thead>
    <tbody>
      <?php if(empty($logs)): ?>
        <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted)">No logs</td></tr>
      <?php endif; ?>
      <?php foreach($logs as $l): ?>
      <tr>
        <td class="text-xs text-muted" style="white-space:nowrap"><?=date('M j Y g:i A',strtotime($l['created_at']))?></td>
        <td>
          <?php if($l['full_name']): ?>
            <div class="text-sm fw-bold"><?=clean($l['full_name'])?></div>
            <div class="text-xs text-muted"><?=clean($l['email']??'')?></div>
          <?php else: ?>
            <span class="text-muted">Guest</span>
          <?php endif; ?>
        </td>
        <td class="text-sm"><?=clean($l['action'])?></td>
        <td class="text-xs text-muted"><?=clean($l['ip_address']??'—')?></td>
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
