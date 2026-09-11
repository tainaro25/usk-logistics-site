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

$buyouts = [];
$result = $conn->query(
    'SELECT b.id, b.user_id, b.name, b.phone, b.product_link, b.status, b.created_at, u.email AS user_email ' .
    'FROM buyout_requests b LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC'
);
while ($row = $result->fetch_assoc()) {
    $buyouts[] = $row;
}
$conn->close();

echo json_encode(['ok' => true, 'buyouts' => $buyouts]);
