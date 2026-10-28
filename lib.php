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

require_once __DIR__ . '/consent.php';

const SCHEMA_VERSION = 3;

function db(bool $forMigration = false): PDO
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

    $connection = new PDO('sqlite:' . $config['db_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $connection->exec('PRAGMA busy_timeout=5000');
    $connection->exec('PRAGMA synchronous=NORMAL');
    if ($forMigration) {
        // Serialize WAL conversion too: it runs outside the schema transaction.
        $lock = fopen($config['db_path'] . '.migration.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock migration');
        try {
            $connection->exec('PRAGMA journal_mode=WAL');
            migrate($connection);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } elseif ((int)$connection->query('PRAGMA user_version')->fetchColumn() !== SCHEMA_VERSION) {
        throw new RuntimeException('Database migration required');
    }
    $pdo = $connection;
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version > SCHEMA_VERSION) throw new RuntimeException('Unsupported database version');
    if ($version === SCHEMA_VERSION) {
        $pdo->exec('COMMIT');
        return;
    }
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
    bounces INTEGER NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS source_page_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    path TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer, path)
);

CREATE TABLE IF NOT EXISTS source_browser_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    browser TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer, browser)
);

CREATE TABLE IF NOT EXISTS source_os_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    os TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer, os)
);

CREATE TABLE IF NOT EXISTS source_device_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    device TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer, device)
);

CREATE TABLE IF NOT EXISTS source_event_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    referrer TEXT NOT NULL,
    event_name TEXT NOT NULL,
    events INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, referrer, event_name)
);

CREATE INDEX IF NOT EXISTS idx_daily_site_date ON daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_hourly_site_hour ON hourly_stats(site_id, stat_hour);
CREATE INDEX IF NOT EXISTS idx_page_daily_site_date ON page_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_referrer_daily_site_date ON referrer_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_browser_daily_site_date ON browser_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_os_daily_site_date ON os_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_device_daily_site_date ON device_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_event_daily_site_date ON event_daily_stats(site_id, stat_date);
CREATE INDEX IF NOT EXISTS idx_source_page_daily_site_date ON source_page_daily_stats(site_id, stat_date, referrer);
CREATE INDEX IF NOT EXISTS idx_source_browser_daily_site_date ON source_browser_daily_stats(site_id, stat_date, referrer);
CREATE INDEX IF NOT EXISTS idx_source_os_daily_site_date ON source_os_daily_stats(site_id, stat_date, referrer);
CREATE INDEX IF NOT EXISTS idx_source_device_daily_site_date ON source_device_daily_stats(site_id, stat_date, referrer);
CREATE INDEX IF NOT EXISTS idx_source_event_daily_site_date ON source_event_daily_stats(site_id, stat_date, referrer);

CREATE TABLE IF NOT EXISTS campaign_daily_stats (
    site_id TEXT NOT NULL,
    stat_date TEXT NOT NULL,
    utm_source TEXT NOT NULL,
    utm_medium TEXT NOT NULL,
    utm_campaign TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, stat_date, utm_source, utm_medium, utm_campaign)
);
CREATE INDEX IF NOT EXISTS idx_campaign_daily_site_date ON campaign_daily_stats(site_id, stat_date);
SQL);

    $dailyColumnNames = array_column($pdo->query('PRAGMA table_info(daily_stats)')->fetchAll(), 'name');
    if (!in_array('bounces', $dailyColumnNames, true)) {
        $pdo->exec('ALTER TABLE daily_stats ADD COLUMN bounces INTEGER NOT NULL DEFAULT 0');
    }

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

    $pdo->exec(
        "INSERT OR IGNORE INTO site_collection_settings(
            site_id, collect_pageviews, collect_pages, collect_referrers, collect_visits, collect_tech, updated_at
        )
        SELECT id, 1, 1, 1, 1, 0, '" . date(DATE_ATOM) . "' FROM sites"
    );
    $pdo->exec('CREATE TABLE IF NOT EXISTS ingest_budget (site_id TEXT PRIMARY KEY, minute INTEGER NOT NULL, hits INTEGER NOT NULL)');
    migrate_consent($pdo);
    // Triggers protect both old and newly created tables without rewriting history.
    foreach (site_data_tables() as $table) {
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS guard_{$table} BEFORE INSERT ON {$table}
            WHEN NOT EXISTS (SELECT 1 FROM sites WHERE id=NEW.site_id)
            BEGIN SELECT RAISE(ABORT, 'Unknown site'); END");
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS delete_{$table} AFTER DELETE ON sites
            BEGIN DELETE FROM {$table} WHERE site_id=OLD.id; END");
    }
    $pdo->exec('PRAGMA user_version=' . SCHEMA_VERSION);
    $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function site_data_tables(): array
{
    return ['site_collection_settings', 'daily_stats', 'hourly_stats', 'page_daily_stats',
        'referrer_daily_stats', 'browser_daily_stats', 'os_daily_stats', 'device_daily_stats',
        'event_daily_stats', 'source_page_daily_stats', 'source_browser_daily_stats',
        'source_os_daily_stats', 'source_device_daily_stats', 'source_event_daily_stats',
        'campaign_daily_stats', 'ingest_budget', 'site_consent_settings', 'consent_documents', 'consent_receipts', 'consent_budget'];
}

function api_error(Throwable $error): never
{
    if ($error instanceof InvalidArgumentException || $error instanceof JsonException) {
        respond_json(400, ['ok' => false, 'error' => $error->getMessage()]);
    }
    if ($error instanceof OverflowException) {
        header('Retry-After: 60');
        respond_json(429, ['ok' => false, 'error' => 'Достигнут лимит приёма событий']);
    }
    respond_json(503, ['ok' => false, 'error' => 'Сервис временно недоступен']);
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

function json_input(int $limit = 8192): array
{
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        respond_json(415, ['ok' => false, 'error' => 'Ожидался application/json']);
    }
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if ($raw === false || strlen($raw) > $limit) {
        throw new InvalidArgumentException('Некорректное тело запроса');
    }
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) {
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
        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($segment);
            if ($decoded === $segment) break;
            $segment = $decoded;
        }
        // Residual encoding and encoded separators cannot be classified safely.
        if (!mb_check_encoding($segment, 'UTF-8') || preg_match('~[%/\\\\\x00-\x1f]~', $segment)) {
            $segments[$index] = ':id';
            continue;
        }
        $isEmail = filter_var($segment, FILTER_VALIDATE_EMAIL) !== false;
        $isUuid = preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $segment);
        $isLongNumber = preg_match('/^\d{5,}$/', $segment);
        $isPhone = preg_match('/^\+?[\d ().-]{10,}$/', $segment)
            && strlen(preg_replace('/\D/', '', $segment)) >= 10;
        // Длинные SEO-slug'и (например, nazvanie-kursa-s-podrobnym-opisaniem)
        // не являются идентификаторами и должны оставаться читаемыми в отчёте.
        // Маскируем только непрозрачные смешанные токены с цифрами или подчёркиваниями.
        $isOpaqueToken = preg_match('/^[A-Za-z0-9_-]{24,}$/', $segment)
            && (preg_match('/\d/', $segment) || str_contains($segment, '_') || !str_contains($segment, '-'));
        $sensitiveRoute = $index > 0 && preg_match('/^(?:token|reset-password|access-token|email|phone)$/i', $segments[$index - 1]);
        if ($isEmail || $isUuid || $isLongNumber || $isOpaqueToken || $isPhone || $sensitiveRoute) {
            $segments[$index] = ':id';
        } else {
            $segments[$index] = $segment;
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
    return array_replace(consent_details((string)($row['id'] ?? $row['site_id'] ?? '')), [
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
    ]);
}

function clean_slide_cookie_settings(mixed $value, ?array $fallback = null): array
{
    $settings = $fallback ?? [
        'enabled' => false, 'policy_url' => '', 'param' => 'always', 'key' => '',
        'block_metrika' => true, 'ym_counter' => '', 'accent_color' => '#C5FF1A',
        'dark_color' => '#0A0A0A', 'accent_text_color' => '#C5FF1A', 'version' => 1,
    ];
    $settings = array_replace(consent_defaults(), $settings);
    if ($value === null) return $settings;
    if (!is_array($value)) throw new InvalidArgumentException('Некорректные настройки Slide Cookie');
    foreach (['enabled', 'block_metrika'] as $key) {
        if (array_key_exists($key, $value)) $settings[$key] = input_bool($value[$key]);
    }
    // Collect every problem so the form can show them all at once.
    $errors = [];
    $limits = ['policy_url' => [2048, 'ссылка на политику'], 'param' => [64, 'параметр показа'],
        'key' => [128, 'значение параметра'], 'ym_counter' => [20, 'ID Метрики']];
    foreach ($limits as $key => [$limit, $label]) {
        if (!array_key_exists($key, $value)) continue;
        $text = input_string($value[$key], $key, PHP_INT_MAX);
        if (mb_strlen($text) > $limit) $errors[] = "{$label}: не длиннее {$limit} символов";
        else $settings[$key] = $text;
    }
    $settings['policy_url'] = normalize_policy_url($settings['policy_url']);
    foreach (['accent_color', 'dark_color', 'accent_text_color'] as $key) {
        if (array_key_exists($key, $value)) {
            $color = input_string($value[$key], $key, 7);
            if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) throw new InvalidArgumentException('Некорректный цвет');
            $settings[$key] = strtoupper($color);
        }
    }
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $settings['param'])) $errors[] = 'некорректный параметр показа';
    if (!preg_match('/^\d{0,20}$/', $settings['ym_counter'])) $errors[] = 'ID Метрики — только цифры';
    if ($settings['enabled'] && $settings['param'] !== 'always' && $settings['key'] === '') {
        $errors[] = 'укажите значение параметра показа или выберите «Всегда показывать»';
    }
    if (($value['reset_consent'] ?? false) === true) $settings['version']++;
    return clean_consent_details($value, $settings, $fallback, $errors);
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
    if ($value === null) return $settings;
    if (!is_array($value)) throw new InvalidArgumentException('Некорректные настройки сбора');
    foreach (array_keys($settings) as $key) {
        if (array_key_exists($key, $value)) {
            $settings[$key] = input_bool($value[$key]);
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
    return http_url_host($_SERVER['HTTP_ORIGIN'] ?? '');
}

function request_referer_host(): string
{
    return http_url_host($_SERVER['HTTP_REFERER'] ?? '');
}

function http_url_host(string $url): string
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])) return '';
    return strtolower(rtrim($parts['host'] ?? '', '.'));
}

function require_site_origin(array $domains): void
{
    if (isset($_SERVER['HTTP_ORIGIN']) && !preg_match('~^https?://[^/?#]+$~i', $_SERVER['HTTP_ORIGIN'])) {
        respond_json(403, ['ok' => false, 'error' => 'Некорректный Origin']);
    }
    $host = isset($_SERVER['HTTP_ORIGIN']) ? request_origin_host() : request_referer_host();
    $domains = array_map(static fn(string $d): string => strtolower(rtrim($d, '.')), $domains);
    if ($host === '' || !in_array($host, $domains, true)) {
        respond_json(403, ['ok' => false, 'error' => 'Домен не разрешён']);
    }
}

function require_admin_origin(): void
{
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        respond_json(403, ['ok' => false]);
    }
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $expected = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        if (strtolower($_SERVER['HTTP_ORIGIN']) !== strtolower($expected)) respond_json(403, ['ok' => false]);
    }
}

function input_string(mixed $value, string $field, int $max = 500): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max) {
        throw new InvalidArgumentException('Некорректное поле: ' . $field);
    }
    return trim($value);
}

function clean_domains(mixed $domains): array
{
    if (!is_array($domains) || count($domains) > 10) throw new InvalidArgumentException('Укажите от 1 до 10 доменов');
    $result = [];
    foreach ($domains as $domain) {
        $domain = strtolower(rtrim(input_string($domain, 'domains', 253), '.'));
        if ($domain === '') continue;
        if (!str_contains($domain, '.') || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || filter_var($domain, FILTER_VALIDATE_IP) || !preg_match('/[a-z]$/', $domain)) {
            throw new InvalidArgumentException('Некорректный домен; для IDN используйте punycode');
        }
        $result[] = $domain;
    }
    if (!$result) throw new InvalidArgumentException('Укажите хотя бы один домен');
    return array_values(array_unique($result));
}

function input_bool(mixed $value): bool
{
    if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
        throw new InvalidArgumentException('Некорректное логическое значение');
    }
    return $value === true || $value === 1 || $value === '1';
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

/** Allowlisted UTM value; anything else (spaces, IDs with symbols, long tokens) is dropped. */
function clean_utm(mixed $value): string
{
    if (!is_string($value)) return '';
    $value = trim($value);
    return preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $value) ? $value : '';
}

/** $column may be an SQL expression over the table's columns, e.g. a composite key. */
function check_dimension_budget(PDO $pdo, string $table, string $column, string $value, string $site, string $day, int $limit): void
{
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE site_id=? AND stat_date=? AND {$column}=? LIMIT 1");
    $stmt->execute([$site, $day, $value]);
    if ($stmt->fetchColumn()) return;
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT {$column}) FROM {$table} WHERE site_id=? AND stat_date=?");
    $stmt->execute([$site, $day]);
    if ((int)$stmt->fetchColumn() >= $limit) throw new OverflowException();
}

if (PHP_SAPI !== 'cli') set_exception_handler('api_error');
