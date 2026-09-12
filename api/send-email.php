<?php
function send_transactional_email($to, $subject, $htmlContent) {
    $configFile = __DIR__ . '/../email-config.php';
    if (!file_exists($configFile)) {
        error_log('send_transactional_email: email-config.php not found');
        return false;
    }
    require_once $configFile;

    if (!defined('RESEND_API_KEY') || RESEND_API_KEY === '') {
        error_log('send_transactional_email: RESEND_API_KEY not configured');
        return false;
    }

    $payload = json_encode([
        'from' => EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlContent,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('send_transactional_email failed: http=' . $httpCode . ' curl_error=' . $curlError . ' response=' . $response);
        return false;
    }

    return true;
}

function generate_verification_code() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function send_verification_code_email($to, $code) {
    $subject = 'Код подтверждения - USK Logistics';
    $html = '<div style="font-family: sans-serif; font-size: 15px; color: #1c1917;">'
        . '<p>Здравствуйте!</p>'
        . '<p>Ваш код подтверждения для регистрации на сайте USK Logistics:</p>'
        . '<p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; margin: 16px 0;">' . htmlspecialchars($code) . '</p>'
        . '<p style="color: #6b6b6b; font-size: 13px;">Код действителен 15 минут. Если вы не регистрировались на сайте USK Logistics, просто проигнорируйте это письмо.</p>'
        . '</div>';
    return send_transactional_email($to, $subject, $html);
}
