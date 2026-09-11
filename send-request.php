<?php
require __DIR__ . '/session-init.php';
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

$dbConfigFile = __DIR__ . '/db-config.php';
if (file_exists($dbConfigFile)) {
    require $dbConfigFile;
    $conn = db_connect();
    if (!$conn->connect_error) {
        $userId = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $stmt = $conn->prepare('INSERT INTO buyout_requests (user_id, name, phone, product_link) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isss', $userId, $name, $phone, $link);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
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

$chatIds = isset($TELEGRAM_CHAT_IDS) ? $TELEGRAM_CHAT_IDS : [$TELEGRAM_CHAT_ID];

$allOk = true;
foreach ($chatIds as $chatId) {
    $ch = curl_init("https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) $allOk = false;
}

if ($allOk) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'telegram_failed']);
}
