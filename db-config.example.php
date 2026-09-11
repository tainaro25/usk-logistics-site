<?php
// Скопируй этот файл в db-config.php и впиши реальные значения.
// db-config.php НЕ должен попадать в GitHub — загружай его отдельно, вручную, через Plesk.
$DB_HOST = 'localhost';
$DB_NAME = 'PASTE_YOUR_DB_NAME_HERE';
$DB_USER = 'PASTE_YOUR_DB_USER_HERE';
$DB_PASS = 'PASTE_YOUR_DB_PASSWORD_HERE';

function db_connect() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    return $conn;
}
