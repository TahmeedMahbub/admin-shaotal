<?php

namespace App\Services\ProductImport;

/** Canonical form of a product URL, so the same page submitted in different spellings is detected as a duplicate. */
class UrlNormalizer
{
    private const TRACKING = [
        'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'yclid', 'dclid', 'igshid', 'srsltid', 'mc_cid', 'mc_eid',
        '_ga', '_gl', 'ref', 'ref_', 'referrer', 'source', 'campaign', 'affiliate', 'aff', 'cmpid', 'variant',
    ];

    /** @return string|null normalized URL, or null when it is not a usable http(s) URL */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\s<>"]/', $url) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $p = parse_url($url);
        if (! $p || empty($p['host'])) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower(rtrim($p['host'], '.')));
        $host = (function_exists('idn_to_ascii') ? idn_to_ascii($host) : false) ?: $host;
        if (! preg_match('/^[a-z0-9.\-\[\]:]+$/', $host) || ! str_contains($host, '.') && ! str_contains($host, ':')) {
            return null;
        }

        $scheme = strtolower($p['scheme']);
        $port = $p['port'] ?? null;
        $keepPort = $port && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443));

        $path = preg_replace('#/{2,}#', '/', $p['path'] ?? '/');
        $path = preg_replace_callback('/%[0-9a-f]{2}/i', fn ($m) => strtoupper($m[0]), $path);
        $path = $path === '/' ? '/' : rtrim($path, '/');

        $query = '';
        if (! empty($p['query'])) {
            parse_str($p['query'], $params);
            foreach ($params as $k => $v) {
                $lk = strtolower((string) $k);
                if (str_starts_with($lk, 'utm_') || in_array($lk, self::TRACKING, true) || $v === '') {
                    unset($params[$k]);
                }
            }
            ksort($params);
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        // http and https are treated as the same page; fragments never identify a product.
        return 'https://'.$host.($keepPort ? ':'.$port : '').$path.($query !== '' ? '?'.$query : '');
    }

    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }
}
