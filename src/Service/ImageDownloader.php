<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
    public function download(string $url): ?UploadedFile
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (!$this->urlSafetyChecker->isAllowed($url)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

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

            $originalName = basename(parse_url($url, PHP_URL_PATH) ?: 'image');
            if ($originalName === '' || $originalName === '/') {
                $originalName = 'image';
            }

            return new UploadedFile(
                $tmpPath,
                $originalName,
                $contentType ?: null,
                null,
                true
            );
        } catch (TransportExceptionInterface) {
            return null;
        }
    }
}
