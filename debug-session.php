<?php
require __DIR__ . '/session-init.php';
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['debug_counter'])) {
    $_SESSION['debug_counter'] = 0;
}
$_SESSION['debug_counter']++;

$sessionDir = __DIR__ . '/.sessions';

echo "=== Диагностика сессии ===\n\n";
echo "Счётчик (должен расти при каждом обновлении страницы): " . $_SESSION['debug_counter'] . "\n\n";
echo "session_id(): " . session_id() . "\n";
echo "session_save_path (настроенный): " . session_save_path() . "\n";
echo "Папка .sessions существует: " . (is_dir($sessionDir) ? 'да' : 'НЕТ') . "\n";
echo "Папка .sessions доступна для записи: " . (is_writable($sessionDir) ? 'да' : 'НЕТ') . "\n\n";
echo "Cookie, полученные от браузера:\n";
print_r($_COOKIE);
echo "\nuser_id в сессии сейчас: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '(не установлен)') . "\n";
