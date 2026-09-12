<?php
require __DIR__ . '/../session-init.php';
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/../db-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}
require $configFile;

$conn = db_connect();
require __DIR__ . '/admin-guard.php';
require_admin($conn);
require __DIR__ . '/csrf.php';
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$buyoutId = (int)($_POST['buyout_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($buyoutId <= 0 || $status === '') {
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$stmt = $conn->prepare('UPDATE buyout_requests SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $buyoutId);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['ok' => $ok]);
