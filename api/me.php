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

$conn = db_connect();
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connection_failed']);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT id, name, phone, email, delivery_address FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

$orders = [];
$stmt = $conn->prepare('SELECT id, track_number, description, origin, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

$buyouts = [];
$stmt = $conn->prepare('SELECT product_link, status, created_at FROM buyout_requests WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $buyouts[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'user' => $user, 'orders' => $orders, 'buyouts' => $buyouts]);
