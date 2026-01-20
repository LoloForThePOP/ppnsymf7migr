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
    public function download(string $url, ?string $preferredBaseName = null, ?string $referer = null): ?UploadedFile
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $primaryHeaders = $this->buildRequestHeaders($referer);
            $response = $this->requestWithSafeRedirects($url, $primaryHeaders);
            if ($response === null) {
                return null;
            }

            $status = $response->getStatusCode();
            if (in_array($status, [401, 403], true) && $referer !== null) {
                $response = $this->requestWithSafeRedirects($url, $this->buildRequestHeaders(null));
                if ($response === null) {
                    return null;
                }
                $status = $response->getStatusCode();
            }
            if ($status < 200 || $status >= 300) {
                return null;
            }

            $headers = $response->getHeaders(false);
            $contentLength = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null;
            if ($contentLength !== null && $contentLength > 5_000_000) { // ~5MB
                return null;
            }

            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '');
            if ($originalName === '' || $originalName === '/') {
                $originalName = 'image';
            }
            $extensionFromUrl = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $extensionFromUrl = ltrim($extensionFromUrl, '.');

            $content = $response->getContent(false);
            if ($contentLength === null && strlen($content) > 5_000_000) {
                return null;
            }

            $contentType = $this->resolveContentType(
                $headers['content-type'][0] ?? '',
                $content,
                $extensionFromUrl
            );
            if ($contentType === null) {
                return null;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'img_');
            if ($tmpPath === false) {
                return null;
            }

            file_put_contents($tmpPath, $content);

            $extension = $this->extensionFromContentType($contentType)
                ?? $extensionFromUrl;
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

    /**
     * @return array<string, mixed>
     */
    public function debugDownload(string $url, ?string $referer = null): array
    {
        $debug = [
            'url' => $url,
            'final_url' => null,
            'status' => null,
            'content_type' => null,
            'content_length' => null,
            'sniffed_type' => null,
            'resolved_type' => null,
            'error' => null,
        ];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $debug['error'] = 'invalid_url';
            return $debug;
        }

        $headers = $this->buildRequestHeaders($referer);
        $result = $this->requestWithSafeRedirectsDebug($url, $headers);
        if ($result['response'] === null) {
            $debug['final_url'] = $result['final_url'];
            $debug['error'] = $result['error'] ?? 'blocked';
            return $debug;
        }

        $response = $result['response'];
        $debug['final_url'] = $result['final_url'];
        $status = $response->getStatusCode();
        if (in_array($status, [401, 403], true) && $referer !== null) {
            $fallback = $this->requestWithSafeRedirectsDebug($url, $this->buildRequestHeaders(null));
            if ($fallback['response'] !== null) {
                $response = $fallback['response'];
                $debug['final_url'] = $fallback['final_url'];
                $status = $response->getStatusCode();
            }
        }

        $debug['status'] = $status;
        if ($status < 200 || $status >= 300) {
            $debug['error'] = 'http_' . $status;
            return $debug;
        }

        $headers = $response->getHeaders(false);
        $debug['content_type'] = $headers['content-type'][0] ?? null;
        $contentLength = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null;
        $debug['content_length'] = $contentLength;
        if ($contentLength !== null && $contentLength > 5_000_000) {
            $debug['error'] = 'too_large';
            return $debug;
        }

        $originalName = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if ($originalName === '' || $originalName === '/') {
            $originalName = 'image';
        }
        $extensionFromUrl = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extensionFromUrl = ltrim($extensionFromUrl, '.');

        $content = $response->getContent(false);
        if ($contentLength === null && strlen($content) > 5_000_000) {
            $debug['error'] = 'too_large';
            return $debug;
        }

        $debug['sniffed_type'] = $this->detectContentType($content);
        $debug['resolved_type'] = $this->resolveContentType(
            (string) ($debug['content_type'] ?? ''),
            $content,
            $extensionFromUrl
        );
        if ($debug['resolved_type'] === null) {
            $debug['error'] = 'invalid_content_type';
        }

        return $debug;
    }

    /**
     * @param array<string, string> $headers
     */
    private function requestWithSafeRedirects(string $url, array $headers, int $maxRedirects = 3): ?ResponseInterface
    {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!$this->urlSafetyChecker->isAllowed($currentUrl)) {
                return null;
            }

            $response = $this->httpClient->request('GET', $currentUrl, [
                'timeout' => 10,
                'max_redirects' => 0,
                'headers' => $headers,
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

    /**
     * @param array<string, string> $headers
     * @return array{response: ?ResponseInterface, final_url: ?string, error: ?string}
     */
    private function requestWithSafeRedirectsDebug(string $url, array $headers, int $maxRedirects = 3): array
    {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!$this->urlSafetyChecker->isAllowed($currentUrl)) {
                return ['response' => null, 'final_url' => $currentUrl, 'error' => 'blocked_url'];
            }

            $response = $this->httpClient->request('GET', $currentUrl, [
                'timeout' => 10,
                'max_redirects' => 0,
                'headers' => $headers,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $responseHeaders = $response->getHeaders(false);
                $location = $responseHeaders['location'][0] ?? null;
                if (!is_string($location) || $location === '') {
                    return ['response' => null, 'final_url' => $currentUrl, 'error' => 'invalid_redirect'];
                }

                $resolved = $this->resolveRedirectUrl($location, $currentUrl);
                if ($resolved === null) {
                    return ['response' => null, 'final_url' => $currentUrl, 'error' => 'invalid_redirect'];
                }

                $currentUrl = $resolved;
                continue;
            }

            return ['response' => $response, 'final_url' => $currentUrl, 'error' => null];
        }

        return ['response' => null, 'final_url' => $currentUrl, 'error' => 'too_many_redirects'];
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

    private function resolveContentType(string $headerType, string $content, string $extensionFromUrl): ?string
    {
        $headerType = strtolower(trim(explode(';', $headerType)[0] ?? ''));
        if ($headerType !== '' && str_starts_with($headerType, 'image/')) {
            return $headerType;
        }

        $sniffedType = $this->detectContentType($content);
        if ($sniffedType !== null && str_starts_with($sniffedType, 'image/')) {
            return $sniffedType;
        }

        if ($this->looksLikeSvg($content)) {
            return 'image/svg+xml';
        }

        if ($extensionFromUrl !== '' && $this->isAllowedImageExtension($extensionFromUrl)) {
            return $this->contentTypeFromExtension($extensionFromUrl);
        }

        return null;
    }

    private function detectContentType(string $content): ?string
    {
        if (!function_exists('finfo_buffer')) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->buffer($content);

        return is_string($type) ? $type : null;
    }

    private function looksLikeSvg(string $content): bool
    {
        $snippet = strtolower(ltrim(substr($content, 0, 1024)));
        return str_contains($snippet, '<svg');
    }

    private function isAllowedImageExtension(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'], true);
    }

    private function contentTypeFromExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
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

    /**
     * @return array<string, string>
     */
    private function buildRequestHeaders(?string $referer): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ];

        if (is_string($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            $headers['Referer'] = $referer;
            $origin = $this->originFromUrl($referer);
            if ($origin !== null) {
                $headers['Origin'] = $origin;
            }
        }

        return $headers;
    }

    private function originFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }
}
