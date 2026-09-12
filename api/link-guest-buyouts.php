<?php
function normalize_phone_kz($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $digits = substr($digits, 1);
    }
    return $digits;
}

function link_guest_buyouts($conn, $userId, $phone) {
    $target = normalize_phone_kz($phone);
    if ($target === '') {
        return;
    }

    $result = $conn->query('SELECT id, phone FROM buyout_requests WHERE user_id IS NULL');
    if (!$result) {
        return;
    }

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        if (normalize_phone_kz($row['phone']) === $target) {
            $ids[] = (int)$row['id'];
        }
    }
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$userId], $ids);

    $stmt = $conn->prepare("UPDATE buyout_requests SET user_id = ? WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}
