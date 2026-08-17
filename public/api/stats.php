<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
require_auth();

$siteId = clean_site_id($_GET['site'] ?? '');
$days = max(1, min(1095, (int)($_GET['days'] ?? 30)));
$groupBy = $_GET['group'] ?? 'day'; // day | week | month
if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
    $groupBy = 'day';
}

$site = site_by_id($siteId);

if (!$site) {
    respond_json(404, ['ok' => false, 'error' => 'Сайт не найден']);
}

$parseDate = function(?string $d): ?DateTimeImmutable {
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);
    if ($dt && $dt->format('Y-m-d') === $d) {
        return $dt->setTime(0, 0, 0);
    }
    return null;
};

$fromDate = $parseDate($_GET['from'] ?? null);
$toDate = $parseDate($_GET['to'] ?? null);

if ($fromDate !== null) {
    if ($toDate === null) {
        $toDate = new DateTimeImmutable('today');
    }
    if ($fromDate > $toDate) {
        $tmp = $fromDate;
        $fromDate = $toDate;
        $toDate = $tmp;
    }
    $diffDays = (int)$fromDate->diff($toDate)->format('%a');
    if ($diffDays > 1095) {
        $fromDate = $toDate->modify('-1095 days');
        $diffDays = 1095;
    }
    $days = $diffDays + 1;
} else {
    $days = max(1, min(1095, (int)($_GET['days'] ?? 30)));
    $toDate = new DateTimeImmutable('today');
    $fromDate = $toDate->modify('-' . ($days - 1) . ' days');
}

$start = $fromDate->format('Y-m-d');
$end = $toDate->format('Y-m-d');

$sourceFilter = array_values(array_unique(array_filter(array_map(
    static fn($source) => trim((string)$source),
    explode(',', (string)($_GET['sources'] ?? ''))
))));
$sourceFilter = array_slice($sourceFilter, 0, 50);

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT stat_date, pageviews, visits
     FROM daily_stats
     WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
     ORDER BY stat_date'
);
$stmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
$rows = $stmt->fetchAll();

$map = [];
foreach ($rows as $row) {
    $map[$row['stat_date']] = [
        'pageviews' => (int)$row['pageviews'],
        'visits' => (int)$row['visits']
    ];
}

$dailyMap = [];
$date = $fromDate;

while ($date <= $toDate) {
    $key = $date->format('Y-m-d');
    $dailyMap[$key] = [
        'pageviews' => $map[$key]['pageviews'] ?? 0,
        'visits' => $map[$key]['visits'] ?? 0,
    ];
    $date = $date->modify('+1 day');
}

if ($sourceFilter) {
    $placeholders = implode(',', array_fill(0, count($sourceFilter), '?'));
    $sourceStmt = $pdo->prepare(
        "SELECT stat_date, SUM(pageviews) AS pageviews, SUM(visits) AS visits
         FROM referrer_daily_stats
         WHERE site_id = ? AND stat_date >= ? AND stat_date <= ? AND referrer IN ({$placeholders})
         GROUP BY stat_date"
    );
    $sourceStmt->execute(array_merge([$siteId, $start, $end], $sourceFilter));
    $sourceMap = [];
    foreach ($sourceStmt->fetchAll() as $row) {
        $sourceMap[$row['stat_date']] = ['pageviews' => (int)$row['pageviews'], 'visits' => (int)$row['visits']];
    }
    foreach ($dailyMap as $dateKey => $_) {
        $dailyMap[$dateKey] = $sourceMap[$dateKey] ?? ['pageviews' => 0, 'visits' => 0];
    }
}

// Агрегация в зависимости от $groupBy
$chartData = [];

if ($groupBy === 'week') {
    $weeks = [];
    foreach ($dailyMap as $d => $val) {
        $dt = new DateTimeImmutable($d);
        // Начало недели (Понедельник)
        $wStart = $dt->modify('this week monday')->format('d.m');
        $wEnd = $dt->modify('this week sunday')->format('d.m');
        $label = $wStart . ' - ' . $wEnd;
        if (!isset($weeks[$label])) {
            $weeks[$label] = ['label' => $label, 'pageviews' => 0, 'visits' => 0];
        }
        $weeks[$label]['pageviews'] += $val['pageviews'];
        $weeks[$label]['visits'] += $val['visits'];
    }
    $chartData = array_values($weeks);
} elseif ($groupBy === 'month') {
    $months = [];
    $ruMonths = [
        '01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр',
        '05' => 'Май', '06' => 'Июн', '07' => 'Июл', '08' => 'Авг',
        '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек'
    ];
    foreach ($dailyMap as $d => $val) {
        [$y, $m] = explode('-', $d);
        $label = ($ruMonths[$m] ?? $m) . ' ' . $y;
        if (!isset($months[$label])) {
            $months[$label] = ['label' => $label, 'pageviews' => 0, 'visits' => 0];
        }
        $months[$label]['pageviews'] += $val['pageviews'];
        $months[$label]['visits'] += $val['visits'];
    }
    $chartData = array_values($months);
} else { // day
    foreach ($dailyMap as $d => $val) {
        $chartData[] = [
            'label' => (new DateTimeImmutable($d))->format('d.m'),
            'pageviews' => $val['pageviews'],
            'visits' => $val['visits'],
        ];
    }
}

$pages = [];
if ($site['collection']['pages']) {
    if ($sourceFilter) {
        $placeholders = implode(',', array_fill(0, count($sourceFilter), '?'));
        $stmt = $pdo->prepare(
            "SELECT path, SUM(pageviews) pageviews, SUM(visits) visits
             FROM source_page_daily_stats
             WHERE site_id = ? AND stat_date >= ? AND stat_date <= ? AND referrer IN ({$placeholders})
             GROUP BY path ORDER BY pageviews DESC LIMIT 30"
        );
        $stmt->execute(array_merge([$siteId, $start, $end], $sourceFilter));
    } else {
        $stmt = $pdo->prepare(
            'SELECT path, SUM(pageviews) pageviews, SUM(visits) visits
             FROM page_daily_stats
             WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
             GROUP BY path ORDER BY pageviews DESC LIMIT 30'
        );
        $stmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
    }
    $pages = $stmt->fetchAll();
}

$referrers = [];
if ($site['collection']['referrers']) {
    $availableStmt = $pdo->prepare(
        'SELECT referrer FROM referrer_daily_stats
         WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
         GROUP BY referrer ORDER BY referrer'
    );
    $availableStmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
    $availableSources = array_map(static fn(array $row): string => $row['referrer'], $availableStmt->fetchAll());
    if ($sourceFilter) {
        $placeholders = implode(',', array_fill(0, count($sourceFilter), '?'));
        $stmt = $pdo->prepare(
            "SELECT referrer, SUM(pageviews) pageviews, SUM(visits) visits
             FROM referrer_daily_stats
             WHERE site_id = ? AND stat_date >= ? AND stat_date <= ? AND referrer IN ({$placeholders})
             GROUP BY referrer ORDER BY visits DESC, pageviews DESC LIMIT 30"
        );
        $stmt->execute(array_merge([$siteId, $start, $end], $sourceFilter));
    } else {
        $stmt = $pdo->prepare(
            'SELECT referrer, SUM(pageviews) pageviews, SUM(visits) visits
             FROM referrer_daily_stats
             WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
             GROUP BY referrer ORDER BY visits DESC, pageviews DESC LIMIT 30'
        );
        $stmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
    }
    $referrers = $stmt->fetchAll();
}

$tech = ['browsers' => [], 'os' => [], 'devices' => []];
if ($site['collection']['tech']) {
    foreach ([
        'browsers' => ['browser_daily_stats', 'source_browser_daily_stats', 'browser'],
        'os' => ['os_daily_stats', 'source_os_daily_stats', 'os'],
        'devices' => ['device_daily_stats', 'source_device_daily_stats', 'device'],
    ] as $key => [$table, $sourceTable, $column]) {
        if ($sourceFilter) {
            $placeholders = implode(',', array_fill(0, count($sourceFilter), '?'));
            $stmt = $pdo->prepare(
                "SELECT {$column} AS label, SUM(pageviews) AS pageviews, SUM(visits) AS visits
                 FROM {$sourceTable}
                 WHERE site_id = ? AND stat_date >= ? AND stat_date <= ? AND referrer IN ({$placeholders})
                 GROUP BY {$column} ORDER BY pageviews DESC LIMIT 20"
            );
            $stmt->execute(array_merge([$siteId, $start, $end], $sourceFilter));
        } else {
            $stmt = $pdo->prepare(
                "SELECT {$column} AS label, SUM(pageviews) AS pageviews, SUM(visits) AS visits
                 FROM {$table}
                 WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
                 GROUP BY {$column} ORDER BY pageviews DESC LIMIT 20"
            );
            $stmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
        }
        $tech[$key] = array_map(static fn(array $row): array => [
            'label' => $row['label'],
            'pageviews' => (int)$row['pageviews'],
            'visits' => (int)$row['visits'],
        ], $stmt->fetchAll());
    }
}

if ($sourceFilter) {
    $placeholders = implode(',', array_fill(0, count($sourceFilter), '?'));
    $stmt = $pdo->prepare(
        "SELECT event_name, SUM(events) AS events, SUM(visits) AS visits
         FROM source_event_daily_stats
         WHERE site_id = ? AND stat_date >= ? AND stat_date <= ? AND referrer IN ({$placeholders})
         GROUP BY event_name ORDER BY events DESC LIMIT 30"
    );
    $stmt->execute(array_merge([$siteId, $start, $end], $sourceFilter));
} else {
    $stmt = $pdo->prepare(
        'SELECT event_name, SUM(events) AS events, SUM(visits) AS visits
         FROM event_daily_stats
         WHERE site_id = :site AND stat_date >= :start AND stat_date <= :end
         GROUP BY event_name ORDER BY events DESC LIMIT 30'
    );
    $stmt->execute([':site' => $siteId, ':start' => $start, ':end' => $end]);
}
$events = array_map(static fn(array $row): array => [
    'name' => $row['event_name'], 'events' => (int)$row['events'], 'visits' => (int)$row['visits'],
], $stmt->fetchAll());

$pageviews = array_sum(array_column($dailyMap, 'pageviews'));
$visits = array_sum(array_column($dailyMap, 'visits'));

respond_json(200, [
    'ok' => true,
    'site' => ['id' => $siteId, 'name' => $site['name']],
    'collection' => $site['collection'],
    'period' => $days,
    'from' => $start,
    'to' => $end,
    'group' => $groupBy,
    'totals' => [
        'pageviews' => $pageviews,
        'visits' => $visits,
        'depth' => $visits > 0 ? round($pageviews / $visits, 2) : 0,
    ],
    'daily' => $chartData,
    'pages' => array_map(fn($r) => [
        'path' => $r['path'],
        'pageviews' => (int)$r['pageviews'],
        'visits' => (int)$r['visits']
    ], $pages),
    'referrers' => array_map(fn($r) => [
        'referrer' => $r['referrer'],
        'pageviews' => (int)$r['pageviews'],
        'visits' => (int)$r['visits']
    ], $referrers),
    'available_sources' => $availableSources ?? [],
    'tech' => $tech,
    'events' => $events,
]);
