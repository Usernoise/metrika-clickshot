<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib.php';
require_auth();

$checks = [
    'php_version' => PHP_VERSION,
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'sqlite3' => extension_loaded('sqlite3'),
    'storage_exists' => is_dir(dirname(app_config()['db_path'])),
    'storage_writable' => is_writable(dirname(app_config()['db_path'])),
    'db_path' => app_config()['db_path'],
];

try {
    db()->query('SELECT 1')->fetchColumn();
    $checks['database'] = true;
} catch (Throwable $e) {
    $checks['database'] = false;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($checks, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
