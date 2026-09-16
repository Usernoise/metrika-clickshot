<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib.php';
if (PHP_SAPI !== 'cli') exit(1);

try {
    $command = $argv[1] ?? '';
    if ($command === 'migrate') {
        db(true);
        echo "Schema ready: " . SCHEMA_VERSION . PHP_EOL;
    } elseif ($command === 'backup') {
        $destination = $argv[2] ?? '';
        if ($destination === '' || file_exists($destination) || !is_dir(dirname($destination))) {
            throw new RuntimeException('Specify a new backup file in an existing directory');
        }
        $source = app_config()['db_path'];
        if (!is_file($source)) throw new RuntimeException('Source database not found');
        $pdo = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout=5000');
        $stmt = $pdo->prepare('VACUUM INTO ?');
        $stmt->execute([$destination]);
        chmod($destination, 0600);
        $copy = new PDO('sqlite:' . $destination);
        if ($copy->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Backup verification failed');
        echo "Backup verified\n";
    } elseif ($command === 'audit') {
        // Counts only: never print stored paths, domains or secrets.
        $pdo = db();
        $report = ['invalid_site_ids' => 0, 'orphans' => []];
        foreach ($pdo->query('SELECT id FROM sites') as $row) {
            if (clean_site_id($row['id']) === '') $report['invalid_site_ids']++;
        }
        foreach (site_data_tables() as $table) {
            $report['orphans'][$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE site_id NOT IN (SELECT id FROM sites)")->fetchColumn();
        }
        echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        throw new RuntimeException('Usage: php bin/database.php migrate | backup NEW_PATH | audit');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
