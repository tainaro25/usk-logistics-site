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
    'SELECT o.id, o.track_number, o.description, o.origin, o.status, o.source, o.created_at, o.weight, o.payment_amount, u.id AS user_id, u.name AS user_name, u.email AS user_email, u.phone AS user_phone ' .
    'FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at ASC'
);
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$seqCounters = [];
foreach ($orders as &$order) {
    $uid = $order['user_id'];
    $seqCounters[$uid] = ($seqCounters[$uid] ?? 0) + 1;
    $order['seq'] = $seqCounters[$uid];
}
unset($order);
$orders = array_reverse($orders);

$orderIds = array_column($orders, 'id');
$statusHistoryByOrder = [];
if ($orderIds) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));
    $stmt = $conn->prepare("SELECT order_id, status, changed_at FROM order_status_history WHERE order_id IN ($placeholders) ORDER BY id DESC");
    $stmt->bind_param($types, ...$orderIds);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    while ($row = $historyResult->fetch_assoc()) {
        if (!isset($statusHistoryByOrder[$row['order_id']][$row['status']])) {
            $statusHistoryByOrder[$row['order_id']][$row['status']] = $row['changed_at'];
        }
    }
    $stmt->close();
}

foreach ($orders as &$order) {
    $order['status_history'] = (object) ($statusHistoryByOrder[$order['id']] ?? []);
}
unset($order);

$conn->close();

echo json_encode(['ok' => true, 'orders' => $orders]);
