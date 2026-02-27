<?php
session_start();

// ── Database connection ────────────────────────────────────
$host = 'localhost';
$dbname = 'complaint_system';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#fff0f0;color:#c00;border:2px solid #c00;margin:40px auto;max-width:600px;border-radius:10px;">
        <h2>⚠ Database Connection Failed</h2>
        <p>'.$e->getMessage().'</p>
        <p>Make sure MySQL is running in XAMPP and you have imported <strong>database.sql</strong>.</p>
    </div>');
}

// ── Timezone ───────────────────────────────────────────────
date_default_timezone_set('Asia/Kathmandu');

// ── Session timeout (60 min) ───────────────────────────────
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > 3600)) {
    session_unset(); session_destroy(); session_start();
    header('Location: login.php?timeout=1'); exit;
}
if (isset($_SESSION['user_id'])) $_SESSION['last_active'] = time();

// ── CSRF helpers ───────────────────────────────────────────
if (!function_exists('csrf_token')) {
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
}
if (!function_exists('csrf_verify')) {
function csrf_verify() {
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        die('Invalid CSRF token.');
    }
}
}
// ── Auth helpers ───────────────────────────────────────────
if (!function_exists('logged_in')) {
function logged_in()  { return isset($_SESSION['user_id']); }
}
if (!function_exists('require_login')) {
function require_login($redirect='login.php') {
    if (!logged_in()) { header("Location:$redirect"); exit; }
}
}
if (!function_exists('require_role')) {
function require_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        header('Location: dashboard.php'); exit;
    }
}
}
// ── Sanitize input ─────────────────────────────────────────
if (!function_exists('clean')) {
function clean(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
}
if (!function_exists('decode_clean')) {
function decode_clean(string $v): string {
    return htmlspecialchars_decode($v, ENT_QUOTES | ENT_SUBSTITUTE);
}
}

// ── Flash messages ─────────────────────────────────────────
if (!function_exists('flash')) {
function flash(string $msg, string $type='info') {
    $_SESSION['flash'][] = ['msg'=>$msg, 'type'=>$type];
}
}
if (!function_exists('get_flash')) {
function get_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $f;
}
}

// ── Settings loader ────────────────────────────────────────
function get_setting(PDO $pdo, string $key, string $default=''): string {
    $s = $pdo->prepare("SELECT key_value FROM settings WHERE key_name=?");
    $s->execute([$key]);
    return $s->fetchColumn() ?: $default;
}

// ── Activity logger ────────────────────────────────────────
function log_activity(PDO $pdo, string $action) {
    $uid = $_SESSION['user_id'] ?? null;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare("INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)")
        ->execute([$uid, $action, $ip]);
}

// SLA deadline calculator
function sla_deadline(string $priority): string {
    $hours = match($priority) { 'high'=>2, 'medium'=>12, default=>24 };
    return date('Y-m-d H:i:s', strtotime("+$hours hours"));
}

// ── Ticket number generator ────────────────────────────────
function gen_ticket(): string {
    return 'TKT-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid('',true)),0,6));
}

// Smart auto-assign staff
function auto_assign(PDO $pdo, int $bank_id): ?int {
    // Step 1 – active staff, least open tickets
    $sql = "SELECT u.id,
             (SELECT COUNT(*) FROM complaints WHERE assigned_to=u.id AND status IN ('pending','in_progress')) AS open
            FROM users u
            WHERE u.bank_id=:bid AND u.role='bank_staff' AND u.is_active=1 AND u.staff_status='active'
            ORDER BY open ASC, RAND() LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute([':bid'=>$bank_id]);
    $row = $st->fetch();
    if ($row) return $row['id'];

    // Step 2 – any staff, least open tickets
    $sql2 = "SELECT u.id,
              (SELECT COUNT(*) FROM complaints WHERE assigned_to=u.id AND status IN ('pending','in_progress')) AS open
             FROM users u
             WHERE u.bank_id=:bid AND u.role='bank_staff' AND u.is_active=1
             ORDER BY open ASC, RAND() LIMIT 1";
    $st2 = $pdo->prepare($sql2); $st2->execute([':bid'=>$bank_id]);
    $row2 = $st2->fetch();
    return $row2 ? $row2['id'] : null;
}

// ── Update overdue tickets ─────────────────────────────────
$pdo->exec("UPDATE complaints SET status='overdue'
            WHERE status IN ('pending','in_progress') AND sla_deadline < NOW()");

// ── HTML helpers ───────────────────────────────────────────
function priority_badge(string $p): string {
    $map = ['high'=>'badge-danger','medium'=>'badge-warning','low'=>'badge-success'];
    return '<span class="badge '.($map[$p]??'badge-secondary').'">'.strtoupper($p).'</span>';
}
function status_badge(string $s): string {
    $map = ['pending'=>'badge-secondary','in_progress'=>'badge-info',
            'resolved'=>'badge-success','overdue'=>'badge-danger blink'];
    return '<span class="badge '.($map[$s]??'badge-secondary').'">'.strtoupper(str_replace('_',' ',$s)).'</span>';
}
function time_left_html(string $deadline, string $status): string {
    if ($status==='resolved') return '<span class="badge badge-success">✓ Done</span>';
    $sec = strtotime($deadline)-time();
    if ($sec<=0) return '<span class="badge badge-danger blink">OVERDUE</span>';
    $h=floor($sec/3600); $m=floor(($sec%3600)/60); $s=$sec%60;
    $cls = $sec<3600?'badge-danger':($sec<7200?'badge-warning':'badge-info');
    return '<span class="badge '.$cls.' countdown" data-sec="'.$sec.'">'
           .sprintf('%02d:%02d:%02d',$h,$m,$s).'</span>';
}
