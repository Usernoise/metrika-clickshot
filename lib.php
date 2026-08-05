<?php
declare(strict_types=1);

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
        date_default_timezone_set($config['timezone']);
    }
    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $dir = dirname($config['db_path']);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог базы данных');
    }

    $pdo = new PDO('sqlite:' . $config['db_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    $pdo->exec('PRAGMA busy_timeout=5000');

    migrate($pdo);

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sites (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    domains_json TEXT NOT NULL,
    created_at TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date)
);

CREATE TABLE IF NOT EXISTS hourly_stats (
    site_id TEXT NOT NULL,
    stat_hour TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_hour)
);

CREATE TABLE IF NOT EXISTS page_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    path TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, path)
);

CREATE TABLE IF NOT EXISTS referrer_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer)
);

CREATE INDEX IF NOT EXISTS idx_daily_site_date ON daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_hourly_site_hour ON hourly_stats(site_id, stat_hour);
CREATE INDEX IF NOT EXISTS idx_page_daily_site_date ON page_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_referrer_daily_site_date ON referrer_daily_stats(site_id, stat_date);
SQL);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO sites(id, name, domains_json, created_at, is_active)
             VALUES(:id, :name, :domains, :created_at, 1)'
        );
        $stmt->execute([
            ':id' => 'neural_courses',
            ':name' => 'Neural Courses',
            ':domains' => json_encode(
                ['neural-courses.ru', 'www.neural-courses.ru'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            ':created_at' => date(DATE_ATOM),
        ]);
    }
}

function require_auth(): void
{
    $config = app_config();
    $expected = (string)$config['dashboard_password'];

    if ($expected === 'change-me-now') {
        respond_json(503, ['ok' => false, 'error' => 'Задайте METRIKA_DASHBOARD_PASSWORD']);
    }

    $user = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $password = (string)($_SERVER['PHP_AUTH_PW'] ?? '');

    if ($user !== 'admin' || !hash_equals($expected, $password)) {
        header('WWW-Authenticate: Basic realm=ClickShot Metrika');
        respond_json(401, ['ok' => false, 'error' => 'Требуется авторизация']);
    }
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 8192) {
        throw new InvalidArgumentException('Некорректное тело запроса');
    }
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Ожидался JSON');
    }
    return $data;
}

function respond_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_site_id(mixed $value): string
{
    if (!is_string($value) || !preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $value)) {
        return '';
    }
    return $value;
}

function clean_path(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '/';
    }
    $path = parse_url($value, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('~/+~', '/', $path) ?: '/';
    return mb_substr($path, 0, 500);
}

function site_by_id(string $siteId): ?array
{
    $stmt = db()->prepare('SELECT * FROM sites WHERE id = :id AND is_active = 1');
    $stmt->execute([':id' => $siteId]);
    $site = $stmt->fetch();
    if (!$site) {
        return null;
    }
    $site['domains'] = json_decode($site['domains_json'], true) ?: [];
    return $site;
}

function request_origin_host(): string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    return strtolower((string)parse_url($origin, PHP_URL_HOST));
}

function request_referer_host(): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    return strtolower((string)parse_url($referer, PHP_URL_HOST));
}

function clean_referrer(mixed $value, array $siteDomains): string
{
    if (!is_string($value) || $value === '') {
        return '(прямой заход)';
    }
    $host = strtolower((string)parse_url($value, PHP_URL_HOST));
    if ($host === '') {
        return '(прямой заход)';
    }
    if (in_array($host, $siteDomains, true)) {
        return '(внутренний переход)';
    }
    return mb_substr($host, 0, 253);
}

function all_sites(): array
{
    return db()->query(
        'SELECT id, name, domains_json, created_at, is_active
         FROM sites ORDER BY created_at DESC'
    )->fetchAll();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
