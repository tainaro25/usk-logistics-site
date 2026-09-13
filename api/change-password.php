<?php
require __DIR__ . '/../session-init.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

require __DIR__ . '/csrf.php';
require_csrf();

$configFile = __DIR__ . '/../db-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}
require $configFile;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');

if ($currentPassword === '' || strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$conn = db_connect();
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connection_failed']);
    exit;
}

require __DIR__ . '/rate-limit.php';
$userId = $_SESSION['user_id'];
$rateLimitKeys = [
    ['key' => 'change_password:user:' . $userId, 'max_attempts' => 5, 'window_seconds' => 900, 'lock_seconds' => 900],
];
rate_limit_enforce($conn, $rateLimitKeys);

$stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    rate_limit_register_failure($conn, $rateLimitKeys);
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'wrong_current_password']);
    exit;
}

rate_limit_reset($conn, $rateLimitKeys);

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $newHash, $userId);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['ok' => $ok]);
