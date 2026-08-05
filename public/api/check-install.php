<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(405, ['ok' => false, 'error' => 'Метод не поддерживается']);
}

$site = site_by_id(clean_site_id($_GET['site'] ?? ''));
if (!$site || !$site['domains']) {
    respond_json(404, ['ok' => false, 'error' => 'Сайт не найден']);
}

function public_address(string $host): ?string
{
    $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? null;
        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    return null;
}

$checks = [];
foreach ($site['domains'] as $domain) {
    $ip = public_address($domain);
    if ($ip === null) {
        $checks[] = ['domain' => $domain, 'status' => 'unavailable'];
        continue;
    }

    $url = 'https://' . $domain . '/';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'ClickShot-Metrika-Installation-Check/1.0',
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_RESOLVE => [$domain . ':443:' . $ip],
    ]);
    $html = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if (!is_string($html) || $httpCode < 200 || $httpCode >= 400) {
        $checks[] = ['domain' => $domain, 'status' => 'unavailable', 'http_code' => $httpCode, 'error' => $error];
        continue;
    }

    $hasCounter = str_contains($html, 'metrika.clickshot.ru/counter.js')
        && preg_match('~counter\.js\?[^"\']*\bid=' . preg_quote((string)$site['id'], '~') . '\b~i', $html) === 1;
    $checks[] = ['domain' => $domain, 'status' => $hasCounter ? 'installed' : 'missing', 'http_code' => $httpCode];
}

$installed = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'installed'));
respond_json(200, ['ok' => true, 'installed' => (bool)$installed, 'checks' => $checks]);
