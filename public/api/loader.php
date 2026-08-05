<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';

$siteId = clean_site_id($_GET['id'] ?? '');
$site = site_by_id($siteId);
if (!$site) {
    http_response_code(404);
    exit;
}

$payload = [
    'site' => $siteId,
    'collection' => $site['collection'],
    'slide_cookie' => $site['slide_cookie'],
];

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');
echo 'window.__clickShotBootstrap=' . json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) . ';';

if ($site['slide_cookie']['enabled']) {
    echo 'window.__clickShotSlideCookieConfig=' . json_encode([
        'UTM_PARAM' => $site['slide_cookie']['param'],
        'UTM_KEY' => $site['slide_cookie']['key'],
        'YM_COUNTER' => $site['slide_cookie']['ym_counter'] !== '' ? (int)$site['slide_cookie']['ym_counter'] : null,
        'POLICY_URL' => $site['slide_cookie']['policy_url'],
        'BLOCK_METRIKA' => $site['slide_cookie']['block_metrika'],
        'ACCENT_COLOR' => $site['slide_cookie']['accent_color'],
        'DARK_COLOR' => $site['slide_cookie']['dark_color'],
        'ACCENT_TEXT_COLOR' => $site['slide_cookie']['accent_text_color'],
        'STORAGE_KEY' => 'clickshot:cookie-consent:' . $siteId . ':v' . $site['slide_cookie']['version'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
    readfile(dirname(__DIR__) . '/slide-cookie.js');
}

readfile(dirname(__DIR__) . '/counter.js');
