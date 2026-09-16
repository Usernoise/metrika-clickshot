<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';

header('Cache-Control: no-store');

$siteId = clean_site_id($_GET['site'] ?? '');
$site = site_by_id($siteId);
if (!$site) {
    respond_json(404, ['ok' => false]);
}

$domains = array_map('strtolower', $site['domains']);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originHost = request_origin_host();
$refererHost = request_referer_host();

require_site_origin($domains);

if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

respond_json(200, [
    'ok' => true,
    'collection' => $site['collection'],
    'slide_cookie' => $site['slide_cookie'],
]);
