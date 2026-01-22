<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Extracts image candidates (og:image, twitter:image, <img>) from a source page.
 * Images are not downloaded here; callers can present the list for manual selection.
 */
class ImageCandidateFetcher
{
    private const MAX_CANDIDATES = 40;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlSafetyChecker $urlSafetyChecker,
    ) {
    }

    /**
     * @return array<int, array{url:string, source:string}>
     */
    public function fetch(string $sourceUrl): array
    {
        $html = $this->fetchHtml($sourceUrl);
        if ($html === null) {
            return [];
        }

        return $this->extractCandidates($html, $sourceUrl, false);
    }

    public function fetchPage(string $sourceUrl): ?string
    {
        return $this->fetchHtml($sourceUrl);
    }

    /**
     * Extract candidates from raw HTML (e.g. pasted source).
     *
     * @return array<int, array{url:string, source:string}>
     */
    public function extractFromHtml(string $html, ?string $sourceUrl): array
    {
        // Allow cross-domain assets when HTML is pasted manually
        return $this->extractCandidates($html, $sourceUrl, false);
    }

    /**
     * @return array<int, array{url:string, source:string}>
     */
    private function extractCandidates(string $html, ?string $sourceUrl, bool $enforceSameDomain): array
    {
        $base = $sourceUrl ? parse_url($sourceUrl) : false;
        $baseHost = $sourceUrl ? $this->normalizeHost($sourceUrl) : null;
        $crawler = new Crawler($html);

        $candidates = [];
        $seen = [];

        // Meta images first (highest priority)
        foreach (['meta[property="og:image"]', 'meta[name="twitter:image"]'] as $selector) {
            foreach ($crawler->filter($selector) as $node) {
                /** @var \DOMElement $node */
                $content = $node->getAttribute('content');
                $url = $this->resolveUrl($content, $base);
                if ($url && (!$enforceSameDomain || $this->isSameDomain($url, $baseHost)) && !isset($seen[$url])) {
                    $seen[$url] = true;
                    $candidates[] = ['url' => $url, 'source' => 'meta'];
                }
            }
        }

        // <img> tags (src + lazy + srcset, including srcset-only)
        foreach ($crawler->filter('img') as $node) {
            /** @var \DOMElement $node */
            $src = $node->getAttribute('src');
            $url = $this->resolveUrl($src, $base);
            if ($url && (!$enforceSameDomain || $this->isSameDomain($url, $baseHost)) && !isset($seen[$url])) {
                $seen[$url] = true;
                $candidates[] = ['url' => $url, 'source' => 'img'];
            }

            $dataSrc = $node->getAttribute('data-src') ?: $node->getAttribute('data-lazy-src') ?: $node->getAttribute('data-original');
            if ($dataSrc) {
                $dataUrl = $this->resolveUrl($dataSrc, $base);
                if ($dataUrl && (!$enforceSameDomain || $this->isSameDomain($dataUrl, $baseHost)) && !isset($seen[$dataUrl])) {
                    $seen[$dataUrl] = true;
                    $candidates[] = ['url' => $dataUrl, 'source' => 'img:data'];
                }
            }

            $srcset = $node->getAttribute('srcset') ?: $node->getAttribute('data-srcset');
            if ($srcset) {
                $this->extractFromSrcset($srcset, $base, $baseHost, $enforceSameDomain, $seen, $candidates, 'img:srcset');
            }

            if (count($candidates) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        if (count($candidates) < self::MAX_CANDIDATES) {
            // <source> inside <picture>
            foreach ($crawler->filter('source[srcset]') as $node) {
                /** @var \DOMElement $node */
                $srcset = $node->getAttribute('srcset');
                if ($srcset) {
                    $this->extractFromSrcset($srcset, $base, $baseHost, $enforceSameDomain, $seen, $candidates, 'source:srcset');
                }
                if (count($candidates) >= self::MAX_CANDIDATES) {
                    break;
                }
            }
        }

        return array_slice($candidates, 0, self::MAX_CANDIDATES);
    }

    /**
     * @param array<string,mixed>|false $base
     * @param array<string, bool> $seen
     * @param array<int, array{url:string, source:string}> $candidates
     */
    private function extractFromSrcset(
        string $srcset,
        array|false $base,
        ?string $baseHost,
        bool $enforceSameDomain,
        array &$seen,
        array &$candidates,
        string $sourceLabel
    ): void {
        foreach (explode(',', $srcset) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $entry);
            $candidate = $parts[0] ?? '';
            if ($candidate === '') {
                continue;
            }
            $url = $this->resolveUrl($candidate, $base);
            if ($url && (!$enforceSameDomain || $this->isSameDomain($url, $baseHost)) && !isset($seen[$url])) {
                $seen[$url] = true;
                $candidates[] = ['url' => $url, 'source' => $sourceLabel];
                if (count($candidates) >= self::MAX_CANDIDATES) {
                    return;
                }
            }
        }
    }

    private function fetchHtml(string $url): ?string
    {
        $currentUrl = $url;

        for ($i = 0; $i <= 3; $i++) {
            if (!$this->urlSafetyChecker->isAllowed($currentUrl)) {
                return null;
            }

            try {
                $response = $this->httpClient->request('GET', $currentUrl, [
                    'timeout' => 10,
                    'max_redirects' => 0,
                    'headers' => [
                        /* 'User-Agent' => 'ProponImageBot/1.0 (+https://propon.org)',*/
                        'User-Agent' => 'TestImageBot/1.0 (+localhost)',
                    ],
                ]);
            } catch (TransportExceptionInterface) {
                return null;
            }

            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;
                if (!is_string($location) || $location === '') {
                    return null;
                }

                $base = parse_url($currentUrl) ?: false;
                $resolved = $this->resolveUrl($location, $base);
                if ($resolved === null) {
                    return null;
                }

                $currentUrl = $resolved;
                continue;
            }

            if ($status < 200 || $status >= 300) {
                return null;
            }

            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            if (!str_contains($contentType, 'text/html')) {
                return null;
            }

            return $response->getContent(false);
        }

        return null;
    }

    /**
     * @param array<string,mixed>|false $base
     */
    private function resolveUrl(string $maybeRelative, array|false $base): ?string
    {
        $maybeRelative = trim($maybeRelative);
        if ($maybeRelative === '') {
            return null;
        }

        if (str_starts_with($maybeRelative, '//')) {
            $scheme = $base['scheme'] ?? 'https';
            return $scheme . ':' . $maybeRelative;
        }

        if (preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }

        if ($base === false || empty($base['host'])) {
            return null;
        }

        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($maybeRelative, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $maybeRelative);
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(dirname($path), '/\\');
        return sprintf('%s://%s%s/%s', $scheme, $host, $port, ltrim($dir . '/' . $maybeRelative, '/'));
    }

    private function isSameDomain(string $url, ?string $baseHost): bool
    {
        if ($baseHost === null) {
            return true;
        }

        $host = $this->normalizeHost($url);
        return $host === $baseHost;
    }

    private function normalizeHost(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }
}
