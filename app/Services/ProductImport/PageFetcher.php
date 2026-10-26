<?php

namespace App\Services\ProductImport;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Downloads a product page or image, refusing anything that could reach internal services (SSRF) or is unreasonably large. */
class PageFetcher
{
    private const MAX_PAGE_BYTES = 3 * 1024 * 1024;

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const MAX_REDIRECTS = 5;

    /** @param  Closure|null  $resolver  fn (string $host): list<string> of IPs — replaced in tests. */
    public function __construct(private ?Closure $resolver = null) {}

    /** @return array{url: string, html: string} final URL after redirects, and the UTF-8 HTML body */
    public function fetch(string $url): array
    {
        ['url' => $final, 'response' => $response] = $this->download($url, 'text/html,application/xhtml+xml', self::MAX_PAGE_BYTES);

        $type = strtolower($response->header('Content-Type'));
        if ($type !== '' && ! str_contains($type, 'html') && ! str_contains($type, 'xml')) {
            throw new ImportException('The URL is not a web page.', false);
        }

        return ['url' => $final, 'html' => $this->toUtf8($response->body(), $type)];
    }

    /** @return array{bytes: string, mime: string} a JPEG, PNG, WEBP or GIF image, verified by content rather than by header */
    public function fetchImage(string $url, ?string $referer = null): array
    {
        ['response' => $response] = $this->download($url, 'image/avif,image/webp,image/png,image/jpeg,*/*;q=0.5', self::MAX_IMAGE_BYTES, $referer);

        $bytes = $response->body();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || @getimagesizefromstring($bytes) === false) {
            throw new ImportException('The source file is not a JPG, PNG, WEBP or GIF image.', false);
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }

    /** @return array{url: string, response: Response} */
    private function download(string $url, string $accept, int $maxBytes, ?string $referer = null): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $ip = $this->assertPublic($url);
            $parts = parse_url($url);
            $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

            try {
                $response = Http::withHeaders(array_filter([
                    'User-Agent' => 'Mozilla/5.0 (compatible; ShaotalImporter/1.0)',
                    'Accept' => $accept,
                    'Accept-Language' => 'en',
                    'Referer' => $referer,
                ]))->timeout(30)->connectTimeout(8)->withOptions([
                    'allow_redirects' => false,
                    // Pin the connection to the IP we validated so DNS cannot change between check and request.
                    'curl' => [CURLOPT_RESOLVE => [$parts['host'].':'.$port.':'.$ip]],
                    'on_headers' => function ($r) use ($maxBytes) {
                        if ((int) $r->getHeaderLine('Content-Length') > $maxBytes) {
                            throw new ImportException('The file is too large.', false);
                        }
                    },
                    'progress' => function ($total, $downloaded) use ($maxBytes) {
                        if ($downloaded > $maxBytes) {
                            throw new ImportException('The file is too large.', false);
                        }
                    },
                ])->get($url);
            } catch (ImportException $e) {
                throw $e;
            } catch (Throwable $e) {
                $prev = $e->getPrevious();
                if ($prev instanceof ImportException) {
                    throw $prev;
                }
                throw new ImportException('Could not reach the page ('.class_basename($e).').');
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new ImportException('Redirect without a destination.', false);
                }
                $url = $this->absolute($location, $url);

                continue;
            }

            $status = $response->status();
            if ($status === 404 || $status === 410) {
                throw new ImportException("The page was not found (HTTP $status).", false);
            }
            if (in_array($status, [401, 403, 451], true)) {
                throw new ImportException("The site refused access (HTTP $status).", false);
            }
            if ($status === 429 || $status >= 500) {
                throw new ImportException("The site is busy or failing (HTTP $status).");
            }
            if (! $response->successful()) {
                throw new ImportException("Unexpected response (HTTP $status).", false);
            }
            if (strlen($response->body()) > $maxBytes) {
                throw new ImportException('The file is too large.', false);
            }

            return ['url' => $url, 'response' => $response];
        }

        throw new ImportException('Too many redirects.', false);
    }

    /** Validates scheme, port and that every resolved address is public; returns the IP to connect to. */
    private function assertPublic(string $url): string
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || empty($p['host'])) {
            throw new ImportException('Only http and https URLs can be imported.', false);
        }
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443, 8080, 8443], true)) {
            throw new ImportException('That port is not allowed.', false);
        }

        $host = trim($p['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver ? ($this->resolver)($host) : (gethostbynamel($host) ?: []));
        if (! $ips) {
            throw new ImportException('The domain could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ImportException('That address is not allowed.', false);
            }
        }

        return $ips[0];
    }

    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        $root = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '/')) {
            return $root.$location;
        }

        return $root.rtrim(dirname($b['path'] ?? '/'), '/').'/'.$location;
    }

    private function toUtf8(string $body, string $contentType): string
    {
        $charset = null;
        if (preg_match('/charset=([\w\-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w\-]+)/i', substr($body, 0, 4096), $m)) {
            $charset = $m[1];
        }
        if ($charset && ! in_array(strtolower($charset), ['utf-8', 'utf8'], true)) {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if (is_string($converted)) {
                return $converted;
            }
        }

        return $body;
    }
}
