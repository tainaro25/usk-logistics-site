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

$orders = [];
$result = $conn->query(
    'SELECT o.id, o.track_number, o.description, o.origin, o.status, o.source, o.created_at, u.id AS user_id, u.name AS user_name, u.email AS user_email, u.phone AS user_phone ' .
    'FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC'
);
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$conn->close();

echo json_encode(['ok' => true, 'orders' => $orders]);
