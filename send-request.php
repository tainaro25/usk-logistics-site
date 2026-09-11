<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$configFile = __DIR__ . '/telegram-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}
require $configFile;

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$link  = trim($_POST['link'] ?? '');
$email = trim($_POST['email'] ?? '');
$track = trim($_POST['track'] ?? '');

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

$esc = function ($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$lines = ["<b>Новая заявка</b>"];
$lines[] = "👤 Имя: " . $esc($name);
$lines[] = "📞 Телефон: " . $esc($phone);
if ($email !== '') $lines[] = "✉️ Email: " . $esc($email);
if ($link !== '')  $lines[] = "🔗 Ссылка на товар: " . $esc($link);
if ($track !== '') $lines[] = "📦 Трек-номер: " . $esc($track);

$text = implode("\n", $lines);

$ch = curl_init("https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'chat_id' => $TELEGRAM_CHAT_ID,
    'text' => $text,
    'parse_mode' => 'HTML',
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'telegram_failed']);
}
