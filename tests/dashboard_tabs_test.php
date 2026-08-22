<?php
declare(strict_types=1);

$html = file_get_contents(__DIR__ . '/../public/index.php');

function expect_dashboard(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expect_dashboard(substr_count($html, 'data-tab="stats"') === 1, 'Statistics tab is missing');
expect_dashboard(substr_count($html, 'data-tab="settings"') === 1, 'Settings tab is missing');
expect_dashboard(substr_count($html, 'data-tab="cookie"') === 1, 'Slide Cookie tab is missing');
expect_dashboard(substr_count($html, 'id="delete-site"') === 1, 'Counter deletion must be available only once');

$settingsStart = strpos($html, 'data-tab-panel="settings"');
$snippetStart = strpos($html, 'id="snippet"');
expect_dashboard($settingsStart !== false && $snippetStart !== false && $settingsStart < $snippetStart, 'Snippet must be placed in the counter settings tab');
expect_dashboard(str_contains($html, 'counter-settings-layout'), 'Settings must use a dedicated two-column layout');

echo "Dashboard tabs contract passed\n";
