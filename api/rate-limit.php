<?php
function rate_limit_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// $keys: list of ['key' => string, 'max_attempts' => int, 'window_seconds' => int, 'lock_seconds' => int]
function rate_limit_enforce($conn, $keys) {
    $now = time();
    foreach ($keys as $k) {
        $stmt = $conn->prepare('SELECT locked_until FROM rate_limits WHERE limit_key = ?');
        $stmt->bind_param('s', $k['key']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['locked_until'] !== null && strtotime($row['locked_until']) > $now) {
            http_response_code(429);
            echo json_encode([
                'ok' => false,
                'error' => 'too_many_attempts',
                'retry_after' => strtotime($row['locked_until']) - $now,
            ]);
            exit;
        }
    }
}

function rate_limit_register_failure($conn, $keys) {
    $now = time();
    foreach ($keys as $k) {
        $stmt = $conn->prepare('SELECT id, attempts, first_attempt_at FROM rate_limits WHERE limit_key = ?');
        $stmt->bind_param('s', $k['key']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $stmt = $conn->prepare('INSERT INTO rate_limits (limit_key, attempts, first_attempt_at) VALUES (?, 1, NOW())');
            $stmt->bind_param('s', $k['key']);
            $stmt->execute();
            $stmt->close();
            continue;
        }

        if ($now - strtotime($row['first_attempt_at']) > $k['window_seconds']) {
            $stmt = $conn->prepare('UPDATE rate_limits SET attempts = 1, first_attempt_at = NOW(), locked_until = NULL WHERE id = ?');
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            $stmt->close();
            continue;
        }

        $attempts = (int)$row['attempts'] + 1;
        if ($attempts >= $k['max_attempts']) {
            $lockedUntil = date('Y-m-d H:i:s', $now + $k['lock_seconds']);
            $stmt = $conn->prepare('UPDATE rate_limits SET attempts = ?, locked_until = ? WHERE id = ?');
            $stmt->bind_param('isi', $attempts, $lockedUntil, $row['id']);
        } else {
            $stmt = $conn->prepare('UPDATE rate_limits SET attempts = ? WHERE id = ?');
            $stmt->bind_param('ii', $attempts, $row['id']);
        }
        $stmt->execute();
        $stmt->close();
    }
}

function rate_limit_reset($conn, $keys) {
    foreach ($keys as $k) {
        $stmt = $conn->prepare('DELETE FROM rate_limits WHERE limit_key = ?');
        $stmt->bind_param('s', $k['key']);
        $stmt->execute();
        $stmt->close();
    }
}
