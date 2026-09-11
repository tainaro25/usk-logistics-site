<?php
require __DIR__ . '/../session-init.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
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

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['delivery_address'] ?? '');

if ($name === '' || $phone === '') {
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

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('UPDATE users SET name = ?, phone = ?, delivery_address = ? WHERE id = ?');
$stmt->bind_param('sssi', $name, $phone, $address, $userId);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['ok' => $ok]);
