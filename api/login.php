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

$email = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
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
    ['key' => 'login:ip:' . $ip, 'max_attempts' => 20, 'window_seconds' => 900, 'lock_seconds' => 900],
    ['key' => 'login:ip-email:' . $ip . ':' . $email, 'max_attempts' => 5, 'window_seconds' => 900, 'lock_seconds' => 900],
];
rate_limit_enforce($conn, $rateLimitKeys);

$stmt = $conn->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    rate_limit_register_failure($conn, $rateLimitKeys);
    $conn->close();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid_credentials']);
    exit;
}

rate_limit_reset($conn, $rateLimitKeys);
$conn->close();

$_SESSION['user_id'] = $user['id'];

echo json_encode(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $email]]);
