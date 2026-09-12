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

$trackNumber = trim($_POST['track_number'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($trackNumber === '') {
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
$status = 'на складе';
$source = 'customer';

$stmt = $conn->prepare('INSERT INTO orders (user_id, track_number, description, status, source) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('issss', $userId, $trackNumber, $description, $status, $source);

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
