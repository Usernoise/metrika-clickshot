<?php
declare(strict_types=1);

/** Static evidence only; does not claim that the browser delivered a hit. */
function inspect_installation(string $html, string $siteId, string $collectorHost): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $found = false;
    $warnings = [];
    foreach ($dom->getElementsByTagName('script') as $script) {
        $src = $script->getAttribute('src');
        $url = parse_url(str_starts_with($src, '//') ? 'https:' . $src : $src);
        if (!$url) continue;
        $host = strtolower($url['host'] ?? '');
        if (preg_match('/^mc\.(?:yandex\.(?:ru|com)|webvisor\.(?:org|com))$/i', $host)) {
            $warnings[] = 'static_metrika_script';
        }
        if ($host !== strtolower($collectorHost) || ($url['path'] ?? '') !== '/counter.js') continue;
        parse_str($url['query'] ?? '', $query);
        $id = $script->getAttribute('data-id') ?: ($query['id'] ?? '');
        if ($id !== $siteId) continue;
        $found = true;
        if ($script->hasAttribute('async') || $script->hasAttribute('defer')) $warnings[] = 'asynchronous_counter';
    }
    foreach ($dom->getElementsByTagName('img') as $image) {
        if (preg_match('~(?:https?:)?//mc\.yandex\.(?:ru|com)/watch/~i', $image->getAttribute('src'))) {
            $warnings[] = 'metrika_pixel';
        }
    }
    return ['installed' => $found, 'warnings' => array_values(array_unique($warnings))];
}

function installation_public_ip(string $host): ?string
{
    $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? null;
        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    return null;
}

function fetch_installation_html(string $domain, array $allowedDomains, float $deadline): ?string
{
    $url = 'https://' . $domain . '/';
    for ($redirect = 0; $redirect <= 3; $redirect++) {
        $parts = parse_url($url);
        $host = http_url_host($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || !in_array($host, $allowedDomains, true)) return null;
        $ip = installation_public_ip($host);
        $remaining = (int)(($deadline - microtime(true)) * 1000);
        if ($ip === null || $remaining <= 0) return null;
        $body = '';
        $location = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => min(3000, $remaining),
            CURLOPT_TIMEOUT_MS => min(5000, $remaining),
            CURLOPT_USERAGENT => 'ClickShot-Metrika-Installation-Check/1.1',
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_PROXY => '',
            CURLOPT_WRITEFUNCTION => static function($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1048576) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function($handle, string $line) use (&$location): int {
                if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
                return strlen($line);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false) return null;
        if ($status >= 200 && $status < 300) return $body;
        if (!in_array($status, [301, 302, 303, 307, 308], true) || $location === '') return null;
        if (str_starts_with($location, '//')) $url = 'https:' . $location;
        elseif (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) $url = $location;
        else {
            $prefix = str_starts_with($location, '/') ? '' : rtrim(dirname($parts['path'] ?? '/'), '/.') . '/';
            $url = 'https://' . $host . $prefix . $location;
        }
    }
    return null;
}
