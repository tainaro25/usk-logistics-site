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

$orderId = (int)($_POST['order_id'] ?? 0);
$trackNumber = trim($_POST['track_number'] ?? '');
$description = trim($_POST['description'] ?? '');
$origin = trim($_POST['origin'] ?? '');
$status = trim($_POST['status'] ?? '');
$weightRaw = trim($_POST['weight'] ?? '');
$paymentRaw = trim($_POST['payment_amount'] ?? '');
$createdAtRaw = trim($_POST['created_at'] ?? '');

if ($orderId <= 0 || $trackNumber === '' || $status === '') {
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$weight = $weightRaw === '' ? null : (float)$weightRaw;
$paymentAmount = $paymentRaw === '' ? null : (float)$paymentRaw;

$createdAtValue = null;
if ($createdAtRaw !== '') {
    $ts = strtotime($createdAtRaw);
    if ($ts !== false) {
        $createdAtValue = date('Y-m-d', $ts);
    }
}

$stmt = $conn->prepare('SELECT status FROM orders WHERE id = ?');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    $conn->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

$oldStatus = $existing['status'];

if ($createdAtValue !== null) {
    $stmt = $conn->prepare('UPDATE orders SET track_number = ?, description = ?, origin = ?, status = ?, weight = ?, payment_amount = ?, created_at = ? WHERE id = ?');
    $stmt->bind_param('ssssddsi', $trackNumber, $description, $origin, $status, $weight, $paymentAmount, $createdAtValue, $orderId);
} else {
    $stmt = $conn->prepare('UPDATE orders SET track_number = ?, description = ?, origin = ?, status = ?, weight = ?, payment_amount = ? WHERE id = ?');
    $stmt->bind_param('ssssddi', $trackNumber, $description, $origin, $status, $weight, $paymentAmount, $orderId);
}

if (!$stmt->execute()) {
    $error = $stmt->errno === 1062 ? 'track_number_taken' : 'update_failed';
    $stmt->close();
    $conn->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}
$stmt->close();

if ($status !== $oldStatus) {
    $stmt = $conn->prepare('INSERT INTO order_status_history (order_id, status) VALUES (?, ?)');
    $stmt->bind_param('is', $orderId, $status);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

echo json_encode(['ok' => true]);
