<?php
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf() {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || $sent === '' || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid_csrf_token']);
        exit;
    }
}
