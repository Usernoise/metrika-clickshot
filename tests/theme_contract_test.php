<?php
declare(strict_types=1);

function expect_theme(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$dashboard = file_get_contents(__DIR__ . '/../public/index.php');
$themeCss = file_get_contents(__DIR__ . '/../public/admin-theme-neu.css');
$themeJs = file_get_contents(__DIR__ . '/../public/admin-theme-neu.js');
$stats = file_get_contents(__DIR__ . '/../public/api/stats.php');

// Переключение тем: атрибут на <html>, кнопка и сохранение выбора.
expect_theme(str_contains($dashboard, 'data-theme="neu"'), 'The default theme is not the neumorphic one');
expect_theme(str_contains($dashboard, 'data-theme-toggle'), 'There is no theme switch button');
expect_theme(str_contains($dashboard, 'clickshot:admin-theme'), 'The theme choice is not persisted');
expect_theme(str_contains($dashboard, 'function applyTheme('), 'There is no theme manager');
expect_theme(str_contains($dashboard, 'function switchTab('), 'There is no shared tab switcher');

// Неоморфическая тема лежит в отдельных файлах и подключается к панели.
expect_theme(str_contains($dashboard, '/admin-theme-neu.css'), 'The theme stylesheet is not linked');
expect_theme(str_contains($dashboard, '/admin-theme-neu.js'), 'The theme script is not linked');
expect_theme(str_contains($themeCss, '[data-theme="neu"]'), 'The theme stylesheet is not scoped to the neumorphic theme');
expect_theme(str_contains($themeCss, '#e6e9f0'), 'The theme surface color is missing');
expect_theme(str_contains($themeCss, '#c3c7d1'), 'The theme shade color is missing');
expect_theme(str_contains($themeCss, '#5c7cfa'), 'The theme accent color is missing');
expect_theme(!str_contains($themeCss, 'backdrop-filter: blur'), 'The theme uses a backdrop blur');

// Элементы рельса и неоморфических блоков присутствуют в разметке.
foreach ([
    'class="rail"',
    'data-neu-tab="stats"',
    'data-neu-tab="settings"',
    'data-neu-tab="cookie"',
    'id="neu-chart-svg"',
    'id="neu-donut"',
    'id="neu-activity"',
    'id="neu-live"',
    'id="neu-ago"',
    'id="neu-period"',
    'id="neu-title"',
] as $marker) {
    expect_theme(str_contains($dashboard, $marker), "The dashboard is missing the markup: {$marker}");
}

// Убраны перегруженные и не несущие смысла элементы.
foreach ([
    'class="rail-logo"',
    'class="rail-user"',
    'id="neu-search"',
    'neu-kpi-chip',
] as $marker) {
    expect_theme(!str_contains($dashboard, $marker), "The dashboard still contains the removed element: {$marker}");
}

// Глифы пунктов рельса — контурные SVG-иконки, а не цветные квадратики.
expect_theme(substr_count($dashboard, 'class="rail-ic"') === 4, 'The rail must use inline SVG icons');

// Живой режим и счётчик «обновлено N назад» живут в теме.
expect_theme(str_contains($themeJs, 'clickshot:stats'), 'The theme does not listen to statistics updates');
expect_theme(str_contains($themeJs, 'clickshot:theme'), 'The theme does not react to theme switching');
expect_theme(str_contains($themeJs, 'LIVE_INTERVAL = 3000'), 'The live refresh interval is not 3 seconds');
expect_theme(!str_contains($themeJs, 'applySearch'), 'The removed search should not stay in the theme script');

// Правило вложенности: в неоморфической теме нет выпуклой карточки внутри выпуклой.
expect_theme(
    str_contains($themeCss, '[data-theme="neu"] .collection-panel') && str_contains($themeCss, 'background: transparent'),
    'The settings section must be a flat container, not a raised card'
);
expect_theme(
    str_contains($themeCss, '[data-theme="neu"] #slide-cookie-panel') && str_contains($themeCss, 'box-shadow: none'),
    'The Slide Cookie section must be a flat container, not a raised card'
);

// Дельты KPI опираются на предыдущий период из API.
expect_theme(str_contains($stats, "'previous' => \$previous"), 'The stats API does not return the previous period');
expect_theme(str_contains($dashboard, 'applyDeltas(d.totals, d.previous)'), 'The dashboard does not render KPI deltas');
expect_theme(str_contains($dashboard, 'TAB_TITLES'), 'The page title does not follow the active tab');

// Классическая тема остаётся доступной: контракт вкладок не должен поехать.
expect_theme(substr_count($dashboard, 'data-tab="stats"') === 1, 'The statistics tab is missing');
expect_theme(substr_count($dashboard, 'data-tab="settings"') === 1, 'The settings tab is missing');
expect_theme(substr_count($dashboard, 'data-tab="cookie"') === 1, 'The Slide Cookie tab is missing');
expect_theme(str_contains($dashboard, 'id="delete-site"'), 'The counter deletion action is missing');
expect_theme(str_contains($dashboard, 'id="snippet"'), 'The embed snippet is missing');

echo "Theme contract passed\n";
