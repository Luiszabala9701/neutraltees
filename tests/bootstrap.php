<?php

declare(strict_types=1);

$raizProyecto = dirname(__DIR__);
$sessionTestDir = __DIR__ . '/.tmp_sessions';

if (!is_dir($sessionTestDir)) {
    mkdir($sessionTestDir, 0777, true);
}

session_save_path($sessionTestDir);
session_name('NEUTRALTEES_PHPUNIT');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

require_once $raizProyecto . '/config/funciones.php';
require_once __DIR__ . '/Support/TestDatabase.php';
