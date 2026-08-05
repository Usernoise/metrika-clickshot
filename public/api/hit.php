<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';

header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, ['ok' => false]);
}

try {
    $input = json_input();
    $siteId = clean_site_id($input['site'] ?? '');
    $site = site_by_id($siteId);

    if (!$site) {
        throw new InvalidArgumentException('Неизвестный сайт');
    }

    $domains = array_map('strtolower', $site['domains']);
    $originHost = request_origin_host();
    $refererHost = request_referer_host();

    if ($originHost && !in_array($originHost, $domains, true)) {
        if (!$refererHost || !in_array($refererHost, $domains, true)) {
            throw new InvalidArgumentException('Домен не разрешён');
        }
    }

    $path = clean_path($input['path'] ?? '/');
    $referrer = clean_referrer($input['referrer'] ?? '', $domains);
    $visit = ($input['newVisit'] ?? false) === true ? 1 : 0;

    $now = new DateTimeImmutable('now');
    $day = $now->format('Y-m-d');
    $hour = $now->format('Y-m-d H:00:00');

    $pdo = db();
    $pdo->beginTransaction();

    $queries = [
        [
            'INSERT INTO daily_stats(site_id, stat_date, pageviews, visits)
             VALUES(:site,:date,1,:visits)
             ON CONFLICT(site_id,stat_date)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':visits' => $visit]
        ],
        [
            'INSERT INTO hourly_stats(site_id, stat_hour, pageviews, visits)
             VALUES(:site,:hour,1,:visits)
             ON CONFLICT(site_id,stat_hour)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':hour' => $hour, ':visits' => $visit]
        ],
        [
            'INSERT INTO page_daily_stats(site_id, stat_date, path, pageviews, visits)
             VALUES(:site,:date,:path,1,:visits)
             ON CONFLICT(site_id,stat_date,path)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':path' => $path, ':visits' => $visit]
        ],
        [
            'INSERT INTO referrer_daily_stats(site_id, stat_date, referrer, pageviews, visits)
             VALUES(:site,:date,:referrer,1,:visits)
             ON CONFLICT(site_id,stat_date,referrer)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':referrer' => $referrer, ':visits' => $visit]
        ],
    ];

    foreach ($queries as [$sql, $params]) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();
    http_response_code(204);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond_json(400, ['ok' => false, 'error' => $e->getMessage()]);
}
