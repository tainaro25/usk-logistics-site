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

$users = [];
$result = $conn->query('SELECT id, name, email, phone FROM users ORDER BY name');
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$conn->close();

echo json_encode(['ok' => true, 'users' => $users]);
