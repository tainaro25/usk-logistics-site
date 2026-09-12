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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($name === '' || $phone === '' || strlen($password) < 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
$ip = rate_limit_client_ip();
$rateLimitKeys = [
    ['key' => 'register:ip:' . $ip, 'max_attempts' => 10, 'window_seconds' => 3600, 'lock_seconds' => 1800],
];
rate_limit_enforce($conn, $rateLimitKeys);

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    rate_limit_register_failure($conn, $rateLimitKeys);
    $conn->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'email_taken']);
    exit;
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, phone, email, password_hash) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $name, $phone, $email, $hash);

if (!$stmt->execute()) {
    $stmt->close();
    rate_limit_register_failure($conn, $rateLimitKeys);
    $conn->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'insert_failed']);
    exit;
}

rate_limit_reset($conn, $rateLimitKeys);

$userId = $stmt->insert_id;
$stmt->close();

require __DIR__ . '/send-email.php';
$code = generate_verification_code();
$expires = date('Y-m-d H:i:s', time() + 900);

$stmt = $conn->prepare('UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE id = ?');
$stmt->bind_param('ssi', $code, $expires, $userId);
$stmt->execute();
$stmt->close();

$emailSent = send_verification_code_email($email, $code);

$conn->close();

$_SESSION['pending_verify_user_id'] = $userId;

echo json_encode(['ok' => true, 'needs_verification' => true, 'email' => $email, 'email_sent' => $emailSent]);
