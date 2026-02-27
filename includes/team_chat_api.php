<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['bank_staff', 'bank_admin'], true) ||
    !isset($_SESSION['bank_id'])
) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo->prepare("DELETE FROM team_chat WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)")->execute();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Message is required']);
        exit;
    }

    if (!isset($_SESSION['user_id'], $_SESSION['full_name'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO team_chat (bank_id, user_id, full_name, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['bank_id'],
        $_SESSION['user_id'],
        $_SESSION['full_name'],
        $msg
    ]);
}

$stmt = $pdo->prepare("SELECT * FROM team_chat WHERE bank_id=? ORDER BY id DESC LIMIT 80");
$stmt->execute([$_SESSION['bank_id']]);
$messages = array_reverse($stmt->fetchAll());
echo json_encode($messages);
