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

CREATE TABLE IF NOT EXISTS site_collection_settings (
    site_id TEXT PRIMARY KEY,
    collect_pageviews INTEGER NOT NULL DEFAULT 1,
    collect_pages INTEGER NOT NULL DEFAULT 1,
    collect_referrers INTEGER NOT NULL DEFAULT 1,
    collect_visits INTEGER NOT NULL DEFAULT 1,
    collect_tech INTEGER NOT NULL DEFAULT 0,
    slide_cookie_enabled INTEGER NOT NULL DEFAULT 0,
    slide_cookie_policy_url TEXT NOT NULL DEFAULT '',
    slide_cookie_param TEXT NOT NULL DEFAULT 'always',
    slide_cookie_key TEXT NOT NULL DEFAULT '',
    slide_cookie_block_metrika INTEGER NOT NULL DEFAULT 1,
    slide_cookie_ym_counter TEXT NOT NULL DEFAULT '',
    slide_cookie_accent_color TEXT NOT NULL DEFAULT '#C5FF1A',
    slide_cookie_dark_color TEXT NOT NULL DEFAULT '#0A0A0A',
    slide_cookie_accent_text_color TEXT NOT NULL DEFAULT '#C5FF1A',
    slide_cookie_version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL
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

CREATE TABLE IF NOT EXISTS browser_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    browser TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, browser)
);

CREATE TABLE IF NOT EXISTS os_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    os TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, os)
);

CREATE TABLE IF NOT EXISTS device_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    device TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, device)
);

CREATE TABLE IF NOT EXISTS event_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    event_name TEXT NOT NULL,
    events INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, event_name)
);

CREATE INDEX IF NOT EXISTS idx_daily_site_date ON daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_hourly_site_hour ON hourly_stats(site_id, stat_hour);
CREATE INDEX IF NOT EXISTS idx_page_daily_site_date ON page_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_referrer_daily_site_date ON referrer_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_browser_daily_site_date ON browser_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_os_daily_site_date ON os_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_device_daily_site_date ON device_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_event_daily_site_date ON event_daily_stats(site_id, stat_date);
SQL);

    $settingColumnNames = array_column(
        $pdo->query('PRAGMA table_info(site_collection_settings)')->fetchAll(),
        'name'
    );
    $missingColumns = [
        'collect_pageviews' => 'INTEGER NOT NULL DEFAULT 1',
        'slide_cookie_enabled' => 'INTEGER NOT NULL DEFAULT 0',
        'slide_cookie_policy_url' => "TEXT NOT NULL DEFAULT ''",
        'slide_cookie_param' => "TEXT NOT NULL DEFAULT 'always'",
        'slide_cookie_key' => "TEXT NOT NULL DEFAULT ''",
        'slide_cookie_block_metrika' => 'INTEGER NOT NULL DEFAULT 1',
        'slide_cookie_ym_counter' => "TEXT NOT NULL DEFAULT ''",
        'slide_cookie_accent_color' => "TEXT NOT NULL DEFAULT '#C5FF1A'",
        'slide_cookie_dark_color' => "TEXT NOT NULL DEFAULT '#0A0A0A'",
        'slide_cookie_accent_text_color' => "TEXT NOT NULL DEFAULT '#C5FF1A'",
        'slide_cookie_version' => 'INTEGER NOT NULL DEFAULT 1',
    ];
    foreach ($missingColumns as $column => $definition) {
        if (!in_array($column, $settingColumnNames, true)) {
            $pdo->exec("ALTER TABLE site_collection_settings ADD COLUMN {$column} {$definition}");
        }
    }

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

    $pdo->exec(
        "INSERT OR IGNORE INTO site_collection_settings(
            site_id, collect_pageviews, collect_pages, collect_referrers, collect_visits, collect_tech, updated_at
        )
        SELECT id, 1, 1, 1, 1, 0, '" . date(DATE_ATOM) . "' FROM sites"
    );
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
    $segments = explode('/', $path);
    foreach ($segments as $index => $segment) {
        if ($segment === '') {
            continue;
        }
        $isEmail = filter_var(rawurldecode($segment), FILTER_VALIDATE_EMAIL) !== false;
        $isUuid = preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $segment);
        $isLongNumber = preg_match('/^\d{5,}$/', $segment);
        $isToken = preg_match('/^[A-Za-z0-9_-]{24,}$/', $segment);
        if ($isEmail || $isUuid || $isLongNumber || $isToken) {
            $segments[$index] = ':id';
        }
    }
    $path = implode('/', $segments);
    return mb_substr($path, 0, 500);
}

function site_by_id(string $siteId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, cs.collect_pageviews, cs.collect_pages, cs.collect_referrers, cs.collect_visits, cs.collect_tech,
                cs.slide_cookie_enabled, cs.slide_cookie_policy_url, cs.slide_cookie_param, cs.slide_cookie_key,
                cs.slide_cookie_block_metrika, cs.slide_cookie_ym_counter, cs.slide_cookie_accent_color,
                cs.slide_cookie_dark_color, cs.slide_cookie_accent_text_color, cs.slide_cookie_version
         FROM sites s
         LEFT JOIN site_collection_settings cs ON cs.site_id = s.id
         WHERE s.id = :id AND s.is_active = 1'
    );
    $stmt->execute([':id' => $siteId]);
    $site = $stmt->fetch();
    if (!$site) {
        return null;
    }
    $site['domains'] = json_decode($site['domains_json'], true) ?: [];
    $site['collection'] = [
        'pageviews' => (bool)($site['collect_pageviews'] ?? true),
        'pages' => (bool)($site['collect_pages'] ?? true),
        'referrers' => (bool)($site['collect_referrers'] ?? true),
        'visits' => (bool)($site['collect_visits'] ?? true),
        'tech' => (bool)($site['collect_tech'] ?? false),
    ];
    $site['slide_cookie'] = slide_cookie_settings_from_row($site);
    return $site;
}

function slide_cookie_settings_from_row(array $row): array
{
    return [
        'enabled' => (bool)($row['slide_cookie_enabled'] ?? false),
        'policy_url' => (string)($row['slide_cookie_policy_url'] ?? ''),
        'param' => (string)($row['slide_cookie_param'] ?? 'always'),
        'key' => (string)($row['slide_cookie_key'] ?? ''),
        'block_metrika' => (bool)($row['slide_cookie_block_metrika'] ?? true),
        'ym_counter' => (string)($row['slide_cookie_ym_counter'] ?? ''),
        'accent_color' => (string)($row['slide_cookie_accent_color'] ?? '#C5FF1A'),
        'dark_color' => (string)($row['slide_cookie_dark_color'] ?? '#0A0A0A'),
        'accent_text_color' => (string)($row['slide_cookie_accent_text_color'] ?? '#C5FF1A'),
        'version' => max(1, (int)($row['slide_cookie_version'] ?? 1)),
    ];
}

function clean_slide_cookie_settings(mixed $value, ?array $fallback = null): array
{
    $settings = $fallback ?? [
        'enabled' => false, 'policy_url' => '', 'param' => 'always', 'key' => '',
        'block_metrika' => true, 'ym_counter' => '', 'accent_color' => '#C5FF1A',
        'dark_color' => '#0A0A0A', 'accent_text_color' => '#C5FF1A', 'version' => 1,
    ];
    if (!is_array($value)) return $settings;
    foreach (['enabled', 'block_metrika'] as $key) {
        if (array_key_exists($key, $value)) $settings[$key] = $value[$key] === true || $value[$key] === 1 || $value[$key] === '1';
    }
    foreach (['policy_url', 'param', 'key', 'ym_counter'] as $key) {
        if (array_key_exists($key, $value)) $settings[$key] = trim((string)$value[$key]);
    }
    foreach (['accent_color', 'dark_color', 'accent_text_color'] as $key) {
        if (array_key_exists($key, $value) && preg_match('/^#[0-9a-f]{6}$/i', (string)$value[$key])) $settings[$key] = strtoupper((string)$value[$key]);
    }
    if ($settings['policy_url'] !== '' && !preg_match('~^(?:https?://|/|\./|\.\./|\?|#)~i', $settings['policy_url'])) throw new InvalidArgumentException('Некорректная ссылка на политику');
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $settings['param'])) throw new InvalidArgumentException('Некорректный параметр показа');
    if (mb_strlen($settings['key']) > 128 || !preg_match('/^\d{0,20}$/', $settings['ym_counter'])) throw new InvalidArgumentException('Некорректная настройка Slide Cookie');
    if ($settings['enabled'] && $settings['policy_url'] === '') throw new InvalidArgumentException('Укажите ссылку на политику для Slide Cookie');
    if (($value['reset_consent'] ?? false) === true) $settings['version']++;
    return $settings;
}

function site_collection_settings(string $siteId): array
{
    $site = site_by_id($siteId);
    return $site['collection'] ?? [
        'pageviews' => true,
        'pages' => true,
        'referrers' => true,
        'visits' => true,
        'tech' => false,
    ];
}

function clean_collection_settings(mixed $value, ?array $fallback = null): array
{
    $settings = $fallback ?? [
        'pageviews' => true,
        'pages' => true,
        'referrers' => true,
        'visits' => true,
        'tech' => false,
    ];
    if (!is_array($value)) {
        return $settings;
    }
    foreach (array_keys($settings) as $key) {
        if (array_key_exists($key, $value)) {
            $settings[$key] = $value[$key] === true || $value[$key] === 1 || $value[$key] === '1';
        }
    }
    return $settings;
}

function classify_user_agent(): array
{
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    unset($_SERVER['HTTP_USER_AGENT']);

    $browser = 'Другое';
    if (preg_match('/YaBrowser/i', $userAgent)) {
        $browser = 'Яндекс Браузер';
    } elseif (preg_match('/Edg(?:e|A|iOS)?\//i', $userAgent)) {
        $browser = 'Edge';
    } elseif (preg_match('/(?:OPR|Opera|OPiOS)\//i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/(?:Firefox|FxiOS)\//i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/(?:Chrome|CriOS)\//i', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Safari\//i', $userAgent)) {
        $browser = 'Safari';
    }

    $os = 'Другая';
    if (preg_match('/Android/i', $userAgent)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $os = 'iOS';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X|Macintosh/i', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $os = 'Linux';
    }

    $device = 'Компьютер';
    if (preg_match('/iPad|Tablet|PlayBook|Silk\//i', $userAgent)
        || preg_match('/Android/i', $userAgent) && !preg_match('/Mobile/i', $userAgent)) {
        $device = 'Планшет';
    } elseif (preg_match('/Mobi|iPhone|iPod|Android/i', $userAgent)) {
        $device = 'Смартфон';
    }

    return compact('browser', 'os', 'device');
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
        'SELECT s.id, s.name, s.domains_json, s.created_at, s.is_active,
                cs.collect_pageviews,
                cs.collect_pages, cs.collect_referrers, cs.collect_visits, cs.collect_tech
                , cs.slide_cookie_enabled, cs.slide_cookie_policy_url, cs.slide_cookie_param, cs.slide_cookie_key,
                cs.slide_cookie_block_metrika, cs.slide_cookie_ym_counter, cs.slide_cookie_accent_color,
                cs.slide_cookie_dark_color, cs.slide_cookie_accent_text_color, cs.slide_cookie_version
         FROM sites s
         LEFT JOIN site_collection_settings cs ON cs.site_id = s.id
         ORDER BY s.created_at DESC'
    )->fetchAll();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
