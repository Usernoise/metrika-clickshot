<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';

header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight has no site payload. It grants no write access; POST validates it.
    if (request_origin_host() === '') respond_json(403, ['ok' => false]);
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true'); // Legacy sendBeacon clients.
    header('Vary: Origin');
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
    require_site_origin($domains);
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    $path = clean_path(input_string($input['path'] ?? '/', 'path', 4096));
    $referrer = clean_referrer(input_string($input['referrer'] ?? '', 'referrer', 4096), $domains);
    $event = input_string($input['event'] ?? '', 'event', 64);
    if ($event !== '' && !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $event)) {
        throw new InvalidArgumentException('Некорректное событие');
    }

    // Keep aggregation buckets tied to the configured application timezone.
    $timezone = new DateTimeZone(app_config()['timezone']);
    $now = new DateTimeImmutable('now', $timezone);
    $day = $now->format('Y-m-d');
    $hour = $now->format('Y-m-d H:00:00');

    $pdo = db();
    $pdo->beginTransaction();
    // Acquire the writer lock before reading limits/settings inside the transaction.
    $budget = $pdo->prepare('INSERT INTO ingest_budget(site_id,minute,hits) VALUES(?,?,1)
        ON CONFLICT(site_id) DO UPDATE SET minute=excluded.minute,
        hits=CASE WHEN minute=excluded.minute THEN hits+1 ELSE 1 END');
    $budget->execute([$siteId, intdiv(time(), 60)]);
    $budget = $pdo->prepare('SELECT hits FROM ingest_budget WHERE site_id=?');
    $budget->execute([$siteId]);
    if ((int)$budget->fetchColumn() > app_config()['hits_per_minute']) throw new OverflowException();
    $site = site_by_id($siteId);
    if (!$site) throw new InvalidArgumentException('Неизвестный сайт');
    $collection = $site['collection'];
    $visit = $collection['visits'] && ($input['newVisit'] ?? false) === true ? 1 : 0;
    if ($event !== '') {
        check_dimension_budget($pdo, 'event_daily_stats', 'event_name', $event, $siteId, $day, 200);
    } elseif ($collection['pages']) {
        check_dimension_budget($pdo, 'page_daily_stats', 'path', $path, $siteId, $day, 10000);
    }
    if ($collection['referrers']) {
        check_dimension_budget($pdo, $event !== '' ? 'source_event_daily_stats' : 'referrer_daily_stats',
            'referrer', $referrer, $siteId, $day, 1000);
    }

    $queries = [
    ];

    if ($event !== '') {
        $queries[] = [
            'INSERT INTO event_daily_stats(site_id, stat_date, event_name, events, visits)
             VALUES(:site,:date,:event,1,:visits)
             ON CONFLICT(site_id,stat_date,event_name)
             DO UPDATE SET events=events+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':event' => $event, ':visits' => $visit]
        ];
        if ($collection['referrers']) {
            $queries[] = [
                'INSERT INTO source_event_daily_stats(site_id, stat_date, referrer, event_name, events, visits)
                 VALUES(:site,:date,:referrer,:event,1,:visits)
                 ON CONFLICT(site_id,stat_date,referrer,event_name)
                 DO UPDATE SET events=events+1, visits=visits+excluded.visits',
                [':site' => $siteId, ':date' => $day, ':referrer' => $referrer, ':event' => $event, ':visits' => $visit]
            ];
        }
    } elseif ($collection['pageviews'] || $collection['visits']) {
        $pageview = $collection['pageviews'] ? 1 : 0;
        array_push(
            $queries,
            [
                'INSERT INTO daily_stats(site_id, stat_date, pageviews, visits)
                 VALUES(:site,:date,:pageviews,:visits)
                 ON CONFLICT(site_id,stat_date)
                 DO UPDATE SET pageviews=pageviews+excluded.pageviews, visits=visits+excluded.visits',
                [':site' => $siteId, ':date' => $day, ':pageviews' => $pageview, ':visits' => $visit]
            ],
            [
                'INSERT INTO hourly_stats(site_id, stat_hour, pageviews, visits)
                 VALUES(:site,:hour,:pageviews,:visits)
                 ON CONFLICT(site_id,stat_hour)
                 DO UPDATE SET pageviews=pageviews+excluded.pageviews, visits=visits+excluded.visits',
                [':site' => $siteId, ':hour' => $hour, ':pageviews' => $pageview, ':visits' => $visit]
            ]
        );
    }

    if ($event === '' && $collection['pages']) {
        $queries[] = [
            'INSERT INTO page_daily_stats(site_id, stat_date, path, pageviews, visits)
             VALUES(:site,:date,:path,1,:visits)
             ON CONFLICT(site_id,stat_date,path)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':path' => $path, ':visits' => $visit]
        ];
        if ($collection['referrers']) {
            $queries[] = [
                'INSERT INTO source_page_daily_stats(site_id, stat_date, referrer, path, pageviews, visits)
                 VALUES(:site,:date,:referrer,:path,1,:visits)
                 ON CONFLICT(site_id,stat_date,referrer,path)
                 DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
                [':site' => $siteId, ':date' => $day, ':referrer' => $referrer, ':path' => $path, ':visits' => $visit]
            ];
        }
    }

    if ($event === '' && $collection['referrers']) {
        $queries[] = [
            'INSERT INTO referrer_daily_stats(site_id, stat_date, referrer, pageviews, visits)
             VALUES(:site,:date,:referrer,1,:visits)
             ON CONFLICT(site_id,stat_date,referrer)
             DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits',
            [':site' => $siteId, ':date' => $day, ':referrer' => $referrer, ':visits' => $visit]
        ];
    }

    if ($event === '' && $collection['tech']) {
        $tech = classify_user_agent();
        foreach ([
            ['browser_daily_stats', 'source_browser_daily_stats', 'browser', $tech['browser']],
            ['os_daily_stats', 'source_os_daily_stats', 'os', $tech['os']],
            ['device_daily_stats', 'source_device_daily_stats', 'device', $tech['device']],
        ] as [$table, $sourceTable, $column, $value]) {
            $queries[] = [
                "INSERT INTO {$table}(site_id, stat_date, {$column}, pageviews, visits)
                 VALUES(:site,:date,:value,1,:visits)
                 ON CONFLICT(site_id,stat_date,{$column})
                 DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits",
                [':site' => $siteId, ':date' => $day, ':value' => $value, ':visits' => $visit]
            ];
            if ($collection['referrers']) {
                $queries[] = [
                    "INSERT INTO {$sourceTable}(site_id, stat_date, referrer, {$column}, pageviews, visits)
                     VALUES(:site,:date,:referrer,:value,1,:visits)
                     ON CONFLICT(site_id,stat_date,referrer,{$column})
                     DO UPDATE SET pageviews=pageviews+1, visits=visits+excluded.visits",
                    [':site' => $siteId, ':date' => $day, ':referrer' => $referrer, ':value' => $value, ':visits' => $visit]
                ];
            }
        }
    }

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
    api_error($e);
}
