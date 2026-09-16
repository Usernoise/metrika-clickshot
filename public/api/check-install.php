<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
require dirname(__DIR__, 2) . '/installation.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond_json(405, ['ok' => false]);
$site = site_by_id(clean_site_id($_GET['site'] ?? ''));
if (!$site || !$site['domains']) respond_json(404, ['ok' => false, 'error' => 'Сайт не найден']);
if (!extension_loaded('curl') || !extension_loaded('dom')) respond_json(503, ['ok' => false, 'error' => 'Требуются PHP curl и dom']);

$checks = [];
$deadline = microtime(true) + 15;
$domains = array_slice($site['domains'], 0, 10);
foreach ($domains as $domain) {
    $html = fetch_installation_html($domain, $domains, $deadline);
    if ($html === null || $html === '') {
        $checks[] = ['domain' => $domain, 'status' => 'unavailable'];
        continue;
    }
    $result = inspect_installation($html, $site['id'], 'metrika.clickshot.ru');
    if (!$site['slide_cookie']['enabled']) {
        $result['warnings'] = array_values(array_diff($result['warnings'], ['asynchronous_counter']));
    }
    $checks[] = ['domain' => $domain, 'status' => $result['installed'] ? 'installed' : 'unconfirmed', 'warnings' => $result['warnings']];
}
$installed = array_filter($checks, static fn(array $check): bool => $check['status'] === 'installed');
respond_json(200, ['ok' => true, 'installed' => (bool)$installed, 'checks' => $checks]);
