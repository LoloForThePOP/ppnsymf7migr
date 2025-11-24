<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;

class WebpageContentExtractor
{
    /**
     * Extracts simplified text, same-domain links, and images from raw HTML.
     *
     * @return array{text:string, links: string[], images: string[]}
     */
    public function extract(string $html, ?string $baseUrl = null): array
    {
        $crawler = new Crawler($html);

        // Remove scripts/styles
        $crawler->filter('script, style')->each(fn(Crawler $node) => $node->getNode(0)?->parentNode?->removeChild($node->getNode(0)));

        $textParts = [];
        $crawler->filter('h1, h2, h3, h4, p, li')->each(function (Crawler $node) use (&$textParts) {
            $text = trim($node->text());
            if ($text !== '') {
                $textParts[] = $text;
            }
        });

        $links = [];
        $images = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href !== '') {
                $links[] = $href;
            }
        });

        $crawler->filter('img[src]')->each(function (Crawler $node) use (&$images, $baseUrl) {
            $src = trim($node->attr('src') ?? '');
            $url = $this->resolveUrl($src, $baseUrl);
            if ($url) {
                $images[] = $url;
            }
        });

        $links = array_values(array_unique(array_slice($links, 0, 10)));
        $images = array_values(array_unique(array_slice($images, 0, 10)));

        return [
            'text' => implode("\n", array_slice($textParts, 0, 80)),
            'links' => $links,
            'images' => $images,
        ];
    }

    private function normalizeHost(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
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

    private function resolveUrl(string $maybeRelative, ?string $baseUrl): ?string
    {
        if ($maybeRelative === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }

        if ($baseUrl === null) {
            return null;
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['host'])) {
            return null;
        }
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $path = $base['path'] ?? '/';

        if (str_starts_with($maybeRelative, '//')) {
            return $scheme . ':' . $maybeRelative;
        }

        if (str_starts_with($maybeRelative, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $maybeRelative);
        }

        $dir = rtrim(dirname($path), '/\\');
        return sprintf('%s://%s%s/%s', $scheme, $host, $port, ltrim($dir . '/' . $maybeRelative, '/'));
    }
}
