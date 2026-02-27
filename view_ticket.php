<?php
require 'includes/config.php';
require_login();
$id=(int)($_GET['id']??0);
if(!$id){ header('Location:dashboard.php');exit; }

$role=$_SESSION['role'];$uid=$_SESSION['user_id'];

$row=$pdo->prepare("SELECT c.*,
  b.name AS bank_name,
  cu.full_name AS cust_name, cu.email AS cust_email, cu.phone AS cust_phone,
  st.full_name AS staff_name, st.email AS staff_email,
  TIMESTAMPDIFF(SECOND,NOW(),c.sla_deadline) AS secs_left
  FROM complaints c
  LEFT JOIN banks b ON c.bank_id=b.id
  LEFT JOIN users cu ON c.customer_id=cu.id
  LEFT JOIN users st ON c.assigned_to=st.id
  WHERE c.id=?");
$row->execute([$id]); $t=$row->fetch();
if(!$t){ flash('danger','Ticket not found.'); header('Location:dashboard.php');exit; }

// Access control
if($role==='customer'    && $t['customer_id']!=$uid){ header('Location:dashboard.php');exit; }
if($role==='bank_staff'  && $t['assigned_to']!=$uid  && $t['bank_id']!=$_SESSION['bank_id']){ header('Location:dashboard.php');exit; }
if($role==='bank_admin'  && $t['bank_id']!=$_SESSION['bank_id']){ header('Location:dashboard.php');exit; }

// Update status 
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_status'])){
    csrf_verify();
    $ns=clean($_POST['new_status']);
    $note=clean($_POST['note']??'');
    $valid=['in_progress','resolved','pending'];
    if(in_array($ns,$valid)&&in_array($role,['bank_staff','bank_admin','super_admin'])){
        $extra=$ns==='resolved'?', resolved_at=NOW()':'';
        $pdo->prepare("UPDATE complaints SET status=?, updated_at=NOW()$extra WHERE id=?")->execute([$ns,$id]);
        $pdo->prepare("INSERT INTO complaint_logs (complaint_id,user_id,note,old_status,new_status)
                       VALUES (?,?,?,?,?)")
            ->execute([$id,$uid,$note?:('Status changed to '.$ns),$t['status'],$ns]);
        log_activity($pdo,"Ticket #".$t['ticket_no']." → $ns");
        flash('success','Status updated to '.strtoupper(str_replace('_',' ',$ns)).'.');
        header("Location:view_ticket.php?id=$id"); exit;
    }
}

// Complaint history
$hist=$pdo->prepare("SELECT cl.*,u.full_name FROM complaint_logs cl LEFT JOIN users u ON cl.user_id=u.id
                     WHERE cl.complaint_id=? ORDER BY cl.created_at DESC");
$hist->execute([$id]); $logs=$hist->fetchAll();

$page_title='Ticket #'.$t['ticket_no'];
ob_start();
?>
<div class="page-wrap" style="max-width:900px">
  <div class="breadcrumb">
    <a href="dashboard.php">Dashboard</a> ›
    <?php if(isset($_GET['from']) && $_GET['from']==='admin'): ?>
      <a href="/cms/admin/tickets.php">All Tickets</a> ›
    <?php elseif($role==='customer'): ?>
      <a href="my_complaints.php">My Tickets</a> ›
    <?php endif; ?>
    <span><?=clean($t['ticket_no'])?></span>
  </div>

  <!-- Ticket header card -->
  <div class="card mb-24" style="background:linear-gradient(135deg,var(--card),var(--card2));border-color:<?=
    $t['priority']==='high'?'rgba(239,68,68,.4)':($t['priority']==='medium'?'rgba(245,158,11,.4)':'rgba(34,197,94,.4)')
  ?>">
    <div class="flex-between" style="flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Ticket Number</div>
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--accent)"><?=clean($t['ticket_no'])?></div>
        <div class="text-muted text-sm mt-8">Submitted <?=date('D, M j Y · g:i A',strtotime($t['created_at']))?></div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?=priority_badge($t['priority'])?>
        <?=status_badge($t['status'])?>
        <?=time_left_html($t['sla_deadline'],$t['status'])?>
      </div>
    </div>
  </div>

  <div class="grid-2" style="align-items:start">

    <!-- LEFT: Main details -->
    <div>
      <div class="card mb-24">
        <div class="card-header">Complaint Details</div>

        <div style="margin-bottom:18px">
          <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Bank</div>
          <div class="fw-bold"><?=clean($t['bank_name'])?></div>
        </div>
        <div style="margin-bottom:18px">
          <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Subject</div>
          <div class="fw-bold"><?=clean($t['subject'])?></div>
        </div>
        <div style="margin-bottom:18px">
          <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Description</div>
          <div style="line-height:1.7;font-size:14px"><?=nl2br(clean($t['description']))?></div>
        </div>

        <div class="grid-2" style="margin-bottom:18px">
          <div>
            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">SLA Deadline</div>
            <div class="text-sm fw-bold"><?=date('M j, Y g:i A',strtotime($t['sla_deadline']))?></div>
          </div>
          <?php if($role!=='customer'): ?>
          <div>
            <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Assigned To</div>
            <div class="text-sm fw-bold"><?=clean($t['staff_name']??'Unassigned')?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if($t['screenshot']): ?>
        <div>
          <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Screenshot</div>
          <img src="uploads/<?=clean($t['screenshot'])?>" alt="Screenshot"
               style="max-width:100%;border-radius:8px;border:1px solid var(--border);cursor:zoom-in"
               onclick="window.open(this.src)">
        </div>
        <?php endif; ?>
      </div>

      <?php if($role!=='customer'): ?>
      <!-- Customer info -->
      <div class="card mb-24">
        <div class="card-header">Customer Information</div>
        <div class="grid-2">
          <div>
            <div class="text-xs text-muted mb-16" style="text-transform:uppercase;letter-spacing:.4px">Name</div>
            <div class="fw-bold"><?=clean($t['cust_name'])?></div>
          </div>
          <div>
            <div class="text-xs text-muted mb-16" style="text-transform:uppercase;letter-spacing:.4px">Phone</div>
            <div class="fw-bold"><?=clean($t['cust_phone'])?></div>
          </div>
        </div>
        <div style="margin-top:12px">
          <div class="text-xs text-muted mb-16" style="text-transform:uppercase;letter-spacing:.4px">Email</div>
          <div class="fw-bold"><?=clean($t['cust_email'])?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Status update panel -->
      <?php if(in_array($role,['bank_staff','bank_admin','super_admin']) && $t['status']!=='resolved'): ?>
      <div class="card">
        <div class="card-header">Update Status</div>
        <form method="post" onsubmit="return confirmDo('Update ticket status to ' + this.new_status.options[this.new_status.selectedIndex].text.substring(2) + '?')">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <div class="form-group">
            <label class="form-label">New Status</label>
            <select name="new_status" class="form-control" required>
              <?php if($t['status']!=='in_progress'): ?>
                <option value="in_progress">🔄 Mark as In Progress</option>
              <?php endif; ?>
              <option value="resolved">✅ Mark as Resolved</option>
              <?php if($t['status']!=='pending'): ?>
                <option value="pending">⏳ Reopen (Pending)</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Internal Note (optional)</label>
            <textarea class="form-control" name="note" rows="3" placeholder="Add a note about this status change..."></textarea>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Update Status</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Timeline -->
    <div>
      <div class="card" style="position:sticky;top:80px">
        <div class="card-header">Activity Timeline</div>
        <?php if($logs): ?>
        <div class="timeline">
          <?php foreach($logs as $l): ?>
          <div class="tl-item">
            <div class="tl-time"><?=date('M j, Y g:i A',strtotime($l['created_at']))?></div>
            <div class="tl-action"><?=clean($l['note'])?></div>
            <div class="text-xs text-muted">by <?=clean($l['full_name']??'System')?></div>
            <?php if($l['old_status']&&$l['new_status']): ?>
              <div style="margin-top:5px;display:flex;align-items:center;gap:6px">
                <?=status_badge($l['old_status'])?>
                <span class="text-muted">→</span>
                <?=status_badge($l['new_status'])?>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="text-muted text-sm">No activity yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$body_html=ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
