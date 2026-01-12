<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ImageDownloader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlSafetyChecker $urlSafetyChecker,
    ) {
    }

    /**
     * Download an image and wrap it as UploadedFile, with basic validation.
     */
    public function download(string $url, ?string $preferredBaseName = null): ?UploadedFile
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->requestWithSafeRedirects($url);
            if ($response === null) {
                return null;
            }

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return null;
            }

            $headers = $response->getHeaders(false);
            $contentType = $headers['content-type'][0] ?? '';
            if (!str_starts_with($contentType, 'image/')) {
                return null;
            }

            $contentLength = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null;
            if ($contentLength !== null && $contentLength > 5_000_000) { // ~5MB
                return null;
            }

            $content = $response->getContent(false);
            if ($contentLength === null && strlen($content) > 5_000_000) {
                return null;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'img_');
            if ($tmpPath === false) {
                return null;
            }

            file_put_contents($tmpPath, $content);

            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '');
            if ($originalName === '' || $originalName === '/') {
                $originalName = 'image';
            }

            $extension = $this->extensionFromContentType($contentType)
                ?? pathinfo($originalName, PATHINFO_EXTENSION);
            $extension = ltrim((string) $extension, '.');

            $baseName = $preferredBaseName !== null ? $this->sanitizeBaseName($preferredBaseName) : '';
            if ($baseName === '') {
                $baseName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'image';
            }

            if ($extension === '') {
                $extension = 'jpg';
            }

            $finalName = $baseName . '.' . $extension;

            return new UploadedFile(
                $tmpPath,
                $finalName,
                $contentType ?: null,
                null,
                true
            );
        } catch (TransportExceptionInterface) {
            return null;
        }
    }

    private function requestWithSafeRedirects(string $url, int $maxRedirects = 3): ?ResponseInterface
    {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!$this->urlSafetyChecker->isAllowed($currentUrl)) {
                return null;
            }

            $response = $this->httpClient->request('GET', $currentUrl, [
                'timeout' => 10,
                'max_redirects' => 0,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;
                if (!is_string($location) || $location === '') {
                    return null;
                }

                $resolved = $this->resolveRedirectUrl($location, $currentUrl);
                if ($resolved === null) {
                    return null;
                }

                $currentUrl = $resolved;
                continue;
            }

            return $response;
        }

        return null;
    }

    private function resolveRedirectUrl(string $location, string $baseUrl): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['host'])) {
            return null;
        }

        if (str_starts_with($location, '//')) {
            $scheme = $base['scheme'] ?? 'https';
            return $scheme . ':' . $location;
        }

        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $location);
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(dirname($path), '/\\');

        return sprintf('%s://%s%s/%s', $scheme, $host, $port, ltrim($dir . '/' . $location, '/'));
    }

    private function extensionFromContentType(string $contentType): ?string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        return match ($contentType) {
            'image/jpeg', 'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            default => null,
        };
    }

    private function sanitizeBaseName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '-');

        return $name;
    }
}
