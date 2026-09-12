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
require __DIR__ . '/csrf.php';
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$trackNumber = trim($_POST['track_number'] ?? '');
$description = trim($_POST['description'] ?? '');
$origin = trim($_POST['origin'] ?? '');
$status = trim($_POST['status'] ?? 'на рассмотрении');

if ($userId <= 0 || $trackNumber === '') {
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO orders (user_id, track_number, description, origin, status) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('issss', $userId, $trackNumber, $description, $origin, $status);

if (!$stmt->execute()) {
    $error = $stmt->errno === 1062 ? 'track_number_taken' : 'insert_failed';
    $stmt->close();
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

$stmt->close();
$conn->close();

echo json_encode(['ok' => true]);
