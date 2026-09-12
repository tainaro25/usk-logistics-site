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
    ['key' => 'resend:user:' . $userId, 'max_attempts' => 3, 'window_seconds' => 900, 'lock_seconds' => 900],
    ['key' => 'resend:ip:' . $ip, 'max_attempts' => 10, 'window_seconds' => 900, 'lock_seconds' => 900],
];
rate_limit_enforce($conn, $rateLimitKeys);

$stmt = $conn->prepare('SELECT email, email_verified FROM users WHERE id = ?');
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
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'already_verified']);
    exit;
}

rate_limit_register_failure($conn, $rateLimitKeys);

require __DIR__ . '/send-email.php';
$code = generate_verification_code();
$expires = date('Y-m-d H:i:s', time() + 900);

$stmt = $conn->prepare('UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE id = ?');
$stmt->bind_param('ssi', $code, $expires, $userId);
$stmt->execute();
$stmt->close();

$emailSent = send_verification_code_email($user['email'], $code);

$conn->close();

echo json_encode(['ok' => true, 'email_sent' => $emailSent]);
