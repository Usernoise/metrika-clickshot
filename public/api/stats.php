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

$start = (new DateTimeImmutable('today'))
    ->modify('-' . ($days - 1) . ' days')
    ->format('Y-m-d');

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT stat_date, pageviews, visits
     FROM daily_stats
     WHERE site_id = :site AND stat_date >= :start
     ORDER BY stat_date'
);
$stmt->execute([':site' => $siteId, ':start' => $start]);
$rows = $stmt->fetchAll();

$map = [];
foreach ($rows as $row) {
    $map[$row['stat_date']] = [
        'pageviews' => (int)$row['pageviews'],
        'visits' => (int)$row['visits']
    ];
}

$dailyMap = [];
$date = new DateTimeImmutable($start);
$today = new DateTimeImmutable('today');

while ($date <= $today) {
    $key = $date->format('Y-m-d');
    $dailyMap[$key] = [
        'pageviews' => $map[$key]['pageviews'] ?? 0,
        'visits' => $map[$key]['visits'] ?? 0,
    ];
    $date = $date->modify('+1 day');
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

$stmt = $pdo->prepare(
    'SELECT path, SUM(pageviews) pageviews, SUM(visits) visits
     FROM page_daily_stats
     WHERE site_id = :site AND stat_date >= :start
     GROUP BY path ORDER BY pageviews DESC LIMIT 30'
);
$stmt->execute([':site' => $siteId, ':start' => $start]);
$pages = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT referrer, SUM(pageviews) pageviews, SUM(visits) visits
     FROM referrer_daily_stats
     WHERE site_id = :site AND stat_date >= :start
     GROUP BY referrer ORDER BY visits DESC, pageviews DESC LIMIT 30'
);
$stmt->execute([':site' => $siteId, ':start' => $start]);
$referrers = $stmt->fetchAll();

$pageviews = array_sum(array_column($dailyMap, 'pageviews'));
$visits = array_sum(array_column($dailyMap, 'visits'));

respond_json(200, [
    'ok' => true,
    'site' => ['id' => $siteId, 'name' => $site['name']],
    'period' => $days,
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
]);
