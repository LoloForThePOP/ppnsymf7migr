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

        // <img> tags
        foreach ($crawler->filter('img[src]') as $node) {
            /** @var \DOMElement $node */
            $src = $node->getAttribute('src');
            $url = $this->resolveUrl($src, $base);
            if ($url && (!$enforceSameDomain || $this->isSameDomain($url, $baseHost)) && !isset($seen[$url])) {
                $seen[$url] = true;
                $candidates[] = ['url' => $url, 'source' => 'img'];
            }
            if (count($candidates) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return array_slice($candidates, 0, self::MAX_CANDIDATES);
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_redirects' => 3,
                'headers' => [
                    /* 'User-Agent' => 'ProponImageBot/1.0 (+https://propon.org)',*/
                    'User-Agent' => 'TestImageBot/1.0 (+localhost)',
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return null;
            }

            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            if (!str_contains($contentType, 'text/html')) {
                return null;
            }

            return $response->getContent(false);
        } catch (TransportExceptionInterface) {
            return null;
        }
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
