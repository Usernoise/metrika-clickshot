<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) require_admin_origin();

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
        $input = json_input(32768);
        $id = clean_site_id($input['id'] ?? '');
        if (array_key_exists('id', $input) && $id === '') throw new InvalidArgumentException('Некорректный ID сайта');
        $name = input_string($input['name'] ?? '', 'name', 200);
        $domains = clean_domains($input['domains'] ?? []);
        if ($name === '') {
            throw new InvalidArgumentException('Некорректные данные');
        }

        if ($id === '') {
            $baseDomain = $domains[0] ?? 'site';
            $slug = strtolower((string)preg_replace('/[^a-z0-9]/i', '_', $baseDomain));
            $slug = trim($slug, '_');
            do {
                $id = substr($slug, 0, 47) . '_' . bin2hex(random_bytes(8));
                $exists = db()->prepare('SELECT 1 FROM sites WHERE id=?');
                $exists->execute([$id]);
            } while ($exists->fetchColumn());
        }

        // Validate everything before changing the database. In particular,
        // Slide Cookie validation may fail after the site fields are valid.
        $settings = clean_collection_settings($input['collection'] ?? null);
        $slideCookie = clean_slide_cookie_settings($input['slide_cookie'] ?? null);
        $pdo = db();
        $pdo->beginTransaction();
        $exists = $pdo->prepare('SELECT 1 FROM sites WHERE id=?');
        $exists->execute([$id]);
        if ($exists->fetchColumn()) throw new InvalidArgumentException('ID сайта уже существует');

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

        save_consent_details($id, $slideCookie);
        $pdo->commit();
        respond_json(201, ['ok' => true, 'id' => $id]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error($e);
    }
}

if ($method === 'PUT') {
    try {
        $input = json_input(32768);
        $id = clean_site_id($input['id'] ?? '');
        $pdo = db();
        $pdo->beginTransaction();
        // Serialize concurrent partial settings updates before reading fallbacks.
        $lock = $pdo->prepare('UPDATE sites SET is_active=is_active WHERE id=?');
        $lock->execute([$id]);
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
        save_consent_details($id, $slideCookie);
        $pdo->commit();
        respond_json(200, ['ok' => true, 'collection' => $settings, 'slide_cookie' => $slideCookie]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        api_error($e);
    }
}

if ($method === 'DELETE') {
    try {
        $input = json_input(32768);
        $id = clean_site_id($input['id'] ?? '');

        if ($id === '') {
            throw new InvalidArgumentException('Не указан ID сайта');
        }

        $pdo = db();
        $pdo->beginTransaction();
        
        foreach (array_merge(site_data_tables(), ['sites']) as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE " . ($table === 'sites' ? 'id' : 'site_id') . ' = :id');
            $stmt->execute([':id' => $id]);
        }

        $pdo->commit();

        respond_json(200, ['ok' => true]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error($e);
    }
}

respond_json(405, ['ok' => false]);
