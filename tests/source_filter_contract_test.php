<?php
declare(strict_types=1);

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$lib = file_get_contents(__DIR__ . '/../lib.php');
$hit = file_get_contents(__DIR__ . '/../public/api/hit.php');
$stats = file_get_contents(__DIR__ . '/../public/api/stats.php');
$dashboard = file_get_contents(__DIR__ . '/../public/index.php');

foreach ([
    'source_page_daily_stats',
    'source_browser_daily_stats',
    'source_os_daily_stats',
    'source_device_daily_stats',
    'source_event_daily_stats',
] as $table) {
    expect_true(str_contains($lib, $table), "Missing source-aware aggregate table: {$table}");
    expect_true(str_contains($hit, $table), "Hits are not stored in {$table}");
    expect_true(str_contains($stats, $table), "Stats API does not query {$table}");
}

expect_true(str_contains($dashboard, '<option value="1">Сегодня</option>'), 'The today period option is missing');
expect_true(str_contains($dashboard, 'source-filter-search'), 'The source filter has no search field');
expect_true(str_contains($dashboard, 'source-filter-apply'), 'The source filter has no apply action');

echo "Source filter contract passed\n";
