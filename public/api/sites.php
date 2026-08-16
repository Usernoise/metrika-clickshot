<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sites = array_map(static function(array $site): array {
        return [
            'id' => $site['id'],
            'name' => $site['name'],
            'domains' => json_decode($site['domains_json'], true) ?: [],
            'created_at' => $site['created_at'],
            'is_active' => (bool)$site['is_active'],
            'collection' => [
                'pageviews' => (bool)($site['collect_pageviews'] ?? true),
                'pages' => (bool)($site['collect_pages'] ?? true),
                'referrers' => (bool)($site['collect_referrers'] ?? true),
                'visits' => (bool)($site['collect_visits'] ?? true),
                'tech' => (bool)($site['collect_tech'] ?? false),
            ],
            'slide_cookie' => slide_cookie_settings_from_row($site),
        ];
    }, all_sites());

    respond_json(200, ['ok' => true, 'sites' => $sites]);
}

if ($method === 'POST') {
    try {
        $input = json_input();
        $id = clean_site_id($input['id'] ?? '');
        $name = trim((string)($input['name'] ?? ''));
        $domains = $input['domains'] ?? [];

        if (!is_array($domains) || !$domains || $name === '') {
            throw new InvalidArgumentException('Некорректные данные');
        }

        $domains = array_values(array_unique(array_filter(array_map(
            static fn($d) => strtolower(trim((string)$d)),
            $domains
        ))));

        foreach ($domains as $domain) {
            if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
                throw new InvalidArgumentException('Некорректный домен');
            }
        }

        if ($id === '') {
            $baseDomain = $domains[0] ?? 'site';
            $slug = strtolower((string)preg_replace('/[^a-z0-9]/i', '_', $baseDomain));
            $slug = trim($slug, '_');
            $id = $slug . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        // Validate everything before changing the database. In particular,
        // Slide Cookie validation may fail after the site fields are valid.
        $settings = clean_collection_settings($input['collection'] ?? null);
        $slideCookie = clean_slide_cookie_settings($input['slide_cookie'] ?? null);
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO sites(id,name,domains_json,created_at,is_active)
             VALUES(:id,:name,:domains,:created_at,1)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':domains' => json_encode($domains, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => date(DATE_ATOM),
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO site_collection_settings(
                site_id, collect_pageviews, collect_pages, collect_referrers, collect_visits, collect_tech,
                slide_cookie_enabled, slide_cookie_policy_url, slide_cookie_param, slide_cookie_key,
                slide_cookie_block_metrika, slide_cookie_ym_counter, slide_cookie_accent_color,
                slide_cookie_dark_color, slide_cookie_accent_text_color, slide_cookie_version, updated_at
             ) VALUES(:site, :pageviews, :pages, :referrers, :visits, :tech,
                :cookie_enabled, :policy_url, :cookie_param, :cookie_key, :block_metrika, :ym_counter,
                :accent_color, :dark_color, :accent_text_color, :cookie_version, :updated_at)'
        );
        $stmt->execute([
            ':site' => $id,
            ':pageviews' => (int)$settings['pageviews'],
            ':pages' => (int)$settings['pages'],
            ':referrers' => (int)$settings['referrers'],
            ':visits' => (int)$settings['visits'],
            ':tech' => (int)$settings['tech'],
            ':cookie_enabled' => (int)$slideCookie['enabled'], ':policy_url' => $slideCookie['policy_url'],
            ':cookie_param' => $slideCookie['param'], ':cookie_key' => $slideCookie['key'],
            ':block_metrika' => (int)$slideCookie['block_metrika'], ':ym_counter' => $slideCookie['ym_counter'],
            ':accent_color' => $slideCookie['accent_color'], ':dark_color' => $slideCookie['dark_color'],
            ':accent_text_color' => $slideCookie['accent_text_color'], ':cookie_version' => $slideCookie['version'],
            ':updated_at' => date(DATE_ATOM),
        ]);

        $pdo->commit();
        respond_json(201, ['ok' => true, 'id' => $id]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond_json(400, ['ok' => false, 'error' => $e->getMessage() ?: 'Не удалось создать сайт']);
    }
}

if ($method === 'PUT') {
    try {
        $input = json_input();
        $id = clean_site_id($input['id'] ?? '');
        $site = site_by_id($id);
        if (!$site) {
            throw new InvalidArgumentException('Сайт не найден');
        }

        $settings = clean_collection_settings($input['collection'] ?? null, $site['collection']);
        $slideCookie = clean_slide_cookie_settings($input['slide_cookie'] ?? null, $site['slide_cookie']);
        $stmt = db()->prepare(
            'INSERT INTO site_collection_settings(
                site_id, collect_pageviews, collect_pages, collect_referrers, collect_visits, collect_tech,
                slide_cookie_enabled, slide_cookie_policy_url, slide_cookie_param, slide_cookie_key,
                slide_cookie_block_metrika, slide_cookie_ym_counter, slide_cookie_accent_color,
                slide_cookie_dark_color, slide_cookie_accent_text_color, slide_cookie_version, updated_at
             ) VALUES(:site, :pageviews, :pages, :referrers, :visits, :tech,
                :cookie_enabled, :policy_url, :cookie_param, :cookie_key, :block_metrika, :ym_counter,
                :accent_color, :dark_color, :accent_text_color, :cookie_version, :updated_at)
             ON CONFLICT(site_id) DO UPDATE SET
                collect_pageviews = excluded.collect_pageviews,
                collect_pages = excluded.collect_pages,
                collect_referrers = excluded.collect_referrers,
                collect_visits = excluded.collect_visits,
                collect_tech = excluded.collect_tech,
                slide_cookie_enabled = excluded.slide_cookie_enabled,
                slide_cookie_policy_url = excluded.slide_cookie_policy_url,
                slide_cookie_param = excluded.slide_cookie_param,
                slide_cookie_key = excluded.slide_cookie_key,
                slide_cookie_block_metrika = excluded.slide_cookie_block_metrika,
                slide_cookie_ym_counter = excluded.slide_cookie_ym_counter,
                slide_cookie_accent_color = excluded.slide_cookie_accent_color,
                slide_cookie_dark_color = excluded.slide_cookie_dark_color,
                slide_cookie_accent_text_color = excluded.slide_cookie_accent_text_color,
                slide_cookie_version = excluded.slide_cookie_version,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([
            ':site' => $id,
            ':pageviews' => (int)$settings['pageviews'],
            ':pages' => (int)$settings['pages'],
            ':referrers' => (int)$settings['referrers'],
            ':visits' => (int)$settings['visits'],
            ':tech' => (int)$settings['tech'],
            ':cookie_enabled' => (int)$slideCookie['enabled'], ':policy_url' => $slideCookie['policy_url'],
            ':cookie_param' => $slideCookie['param'], ':cookie_key' => $slideCookie['key'],
            ':block_metrika' => (int)$slideCookie['block_metrika'], ':ym_counter' => $slideCookie['ym_counter'],
            ':accent_color' => $slideCookie['accent_color'], ':dark_color' => $slideCookie['dark_color'],
            ':accent_text_color' => $slideCookie['accent_text_color'], ':cookie_version' => $slideCookie['version'],
            ':updated_at' => date(DATE_ATOM),
        ]);
        respond_json(200, ['ok' => true, 'collection' => $settings, 'slide_cookie' => $slideCookie]);
    } catch (Throwable $e) {
        respond_json(400, ['ok' => false, 'error' => $e->getMessage() ?: 'Не удалось сохранить настройки']);
    }
}

if ($method === 'DELETE') {
    try {
        $input = json_input();
        $id = clean_site_id($input['id'] ?? '');

        if ($id === '') {
            throw new InvalidArgumentException('Не указан ID сайта');
        }

        $pdo = db();
        $pdo->beginTransaction();
        
        foreach ([
            'site_collection_settings',
            'daily_stats',
            'hourly_stats',
            'page_daily_stats',
            'referrer_daily_stats',
            'browser_daily_stats',
            'os_daily_stats',
            'device_daily_stats',
            'event_daily_stats',
            'sites',
        ] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE " . ($table === 'sites' ? 'id' : 'site_id') . ' = :id');
            $stmt->execute([':id' => $id]);
        }

        $pdo->commit();

        respond_json(200, ['ok' => true]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond_json(400, ['ok' => false, 'error' => $e->getMessage() ?: 'Не удалось удалить сайт']);
    }
}

respond_json(405, ['ok' => false]);
