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

        $stmt = db()->prepare(
            'INSERT INTO sites(id,name,domains_json,created_at,is_active)
             VALUES(:id,:name,:domains,:created_at,1)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':domains' => json_encode($domains, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => date(DATE_ATOM),
        ]);

        respond_json(201, ['ok' => true, 'id' => $id]);
    } catch (Throwable $e) {
        respond_json(400, ['ok' => false, 'error' => $e->getMessage() ?: 'Не удалось создать сайт']);
    }
}

if ($method === 'DELETE') {
    try {
        $input = json_input();
        $id = clean_site_id($input['id'] ?? '');

        if ($id === '') {
            throw new InvalidArgumentException('Не указан ID сайта');
        }

        db()->beginTransaction();
        
        $stmt = db()->prepare('DELETE FROM sites WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = db()->prepare('DELETE FROM daily_stats WHERE site_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = db()->prepare('DELETE FROM hourly_stats WHERE site_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = db()->prepare('DELETE FROM page_daily_stats WHERE site_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = db()->prepare('DELETE FROM referrer_daily_stats WHERE site_id = :id');
        $stmt->execute([':id' => $id]);

        db()->commit();

        respond_json(200, ['ok' => true]);
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        respond_json(400, ['ok' => false, 'error' => $e->getMessage() ?: 'Не удалось удалить сайт']);
    }
}

respond_json(405, ['ok' => false]);
