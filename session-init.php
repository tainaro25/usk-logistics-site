<?php
$sessionDir = __DIR__ . '/.sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
session_save_path($sessionDir);
session_start();
