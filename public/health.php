<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib.php';
require_auth();

$checks = [
    'php_version' => PHP_VERSION,
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'sqlite3' => extension_loaded('sqlite3'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
    'dom' => extension_loaded('dom'),
    'supported_php' => PHP_VERSION_ID >= 80300,
    'storage_exists' => is_dir(dirname(app_config()['db_path'])),
    'storage_writable' => is_writable(dirname(app_config()['db_path'])),
];

try {
    db()->query('SELECT 1')->fetchColumn();
    $checks['database'] = true;
} catch (Throwable $e) {
    $checks['database'] = false;
}

$ok = !in_array(false, $checks, true);
respond_json($ok ? 200 : 503, ['ok' => $ok, 'checks' => $checks]);
