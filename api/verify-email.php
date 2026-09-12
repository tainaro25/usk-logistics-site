<?php
require __DIR__ . '/../session-init.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['pending_verify_user_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'not_pending']);
    exit;
}

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

$code = trim($_POST['code'] ?? '');
if ($code === '') {
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
$userId = (int)$_SESSION['pending_verify_user_id'];
$ip = rate_limit_client_ip();
$rateLimitKeys = [
    ['key' => 'verify:user:' . $userId, 'max_attempts' => 6, 'window_seconds' => 900, 'lock_seconds' => 900],
    ['key' => 'verify:ip:' . $ip, 'max_attempts' => 20, 'window_seconds' => 900, 'lock_seconds' => 900],
];
rate_limit_enforce($conn, $rateLimitKeys);

$stmt = $conn->prepare('SELECT id, name, email, phone, email_verified, verification_code, verification_code_expires FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    unset($_SESSION['pending_verify_user_id']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'not_pending']);
    exit;
}

if ((int)$user['email_verified'] === 1) {
    $conn->close();
    unset($_SESSION['pending_verify_user_id']);
    $_SESSION['user_id'] = $user['id'];
    echo json_encode(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
    exit;
}

$expired = !$user['verification_code_expires'] || strtotime($user['verification_code_expires']) < time();
$matches = $user['verification_code'] !== null && hash_equals((string)$user['verification_code'], $code);

if ($expired || !$matches) {
    rate_limit_register_failure($conn, $rateLimitKeys);
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $expired ? 'code_expired' : 'invalid_code']);
    exit;
}

rate_limit_reset($conn, $rateLimitKeys);

$stmt = $conn->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

require __DIR__ . '/link-guest-buyouts.php';
link_guest_buyouts($conn, $userId, $user['phone']);

$conn->close();

unset($_SESSION['pending_verify_user_id']);
$_SESSION['user_id'] = $userId;

echo json_encode(['ok' => true, 'user' => ['id' => $userId, 'name' => $user['name'], 'email' => $user['email']]]);
