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
        $crawler->filter('h1, h2, h3, h4, h5, h6, p, li, dt, dd, address')->each(function (Crawler $node) use (&$textParts) {
            $text = trim($node->text());
            if ($text !== '') {
                $textParts[] = $text;
            }
        });

        $links = [];
        $images = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }
            if (preg_match('#^https?://#i', $href) || str_starts_with(strtolower($href), 'mailto:')) {
                $links[] = $href;
            }
        });

        // Meta images (og/twitter)
        $crawler->filter('meta[property="og:image"], meta[property="og:image:url"], meta[name="twitter:image"], meta[name="twitter:image:src"], meta[itemprop="image"]')
            ->each(function (Crawler $node) use (&$images, $baseUrl) {
                $content = trim($node->attr('content') ?? '');
                $this->addImageUrl($images, $content, $baseUrl);
            });

        // Icons (favicon, apple touch icon)
        $crawler->filter('link[rel~="icon"], link[rel="apple-touch-icon"], link[rel="mask-icon"]')
            ->each(function (Crawler $node) use (&$images, $baseUrl) {
                $href = trim($node->attr('href') ?? '');
                $this->addImageUrl($images, $href, $baseUrl);
            });

        // Img sources (src, data-src, srcset, data-srcset)
        $crawler->filter('img')->each(function (Crawler $node) use (&$images, $baseUrl) {
            $src = trim($node->attr('src') ?? '');
            $this->addImageUrl($images, $src, $baseUrl);

            $dataSrc = trim($node->attr('data-src') ?? '');
            $this->addImageUrl($images, $dataSrc, $baseUrl);

            $srcset = $node->attr('srcset');
            foreach ($this->parseSrcset($srcset) as $candidate) {
                $this->addImageUrl($images, $candidate, $baseUrl);
            }

            $dataSrcset = $node->attr('data-srcset');
            foreach ($this->parseSrcset($dataSrcset) as $candidate) {
                $this->addImageUrl($images, $candidate, $baseUrl);
            }
        });

        // Picture source srcset
        $crawler->filter('source')->each(function (Crawler $node) use (&$images, $baseUrl) {
            $srcset = $node->attr('srcset');
            foreach ($this->parseSrcset($srcset) as $candidate) {
                $this->addImageUrl($images, $candidate, $baseUrl);
            }
            $dataSrcset = $node->attr('data-srcset');
            foreach ($this->parseSrcset($dataSrcset) as $candidate) {
                $this->addImageUrl($images, $candidate, $baseUrl);
            }
        });

        // Inline background-image URLs
        $crawler->filter('[style]')->each(function (Crawler $node) use (&$images, $baseUrl) {
            $style = $node->attr('style') ?? '';
            foreach ($this->parseBackgroundImages($style) as $candidate) {
                $this->addImageUrl($images, $candidate, $baseUrl);
            }
        });

        $links = array_values(array_unique(array_slice($links, 0, 30)));
        $images = array_values(array_unique(array_slice($images, 0, 40)));

        return [
            'text' => implode("\n", array_slice($textParts, 0, 80)),
            'links' => $links,
            'images' => $images,
        ];
    }

    /**
     * @param array<int, string> $images
     */
    private function addImageUrl(array &$images, ?string $candidate, ?string $baseUrl): void
    {
        if (!is_string($candidate)) {
            return;
        }
        $candidate = trim($candidate);
        if ($candidate === '' || str_starts_with($candidate, 'data:')) {
            return;
        }

        $url = $this->resolveUrl($candidate, $baseUrl);
        if ($url) {
            $images[] = $url;
        }
    }

    /**
     * @return string[]
     */
    private function parseSrcset(?string $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $candidates = [];
        foreach (preg_split('/\s*,\s*/', $value) as $entry) {
            $parts = preg_split('/\s+/', trim($entry));
            if (!empty($parts[0])) {
                $candidates[] = $parts[0];
            }
        }

        return $candidates;
    }

    /**
     * @return string[]
     */
    private function parseBackgroundImages(string $style): array
    {
        if ($style === '') {
            return [];
        }

        if (!preg_match_all('/url\\(([^)]+)\\)/i', $style, $matches)) {
            return [];
        }

        $urls = [];
        foreach ($matches[1] as $candidate) {
            $candidate = trim($candidate, " \t\n\r\0\x0B\"'");
            if ($candidate !== '') {
                $urls[] = $candidate;
            }
        }

        return $urls;
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
