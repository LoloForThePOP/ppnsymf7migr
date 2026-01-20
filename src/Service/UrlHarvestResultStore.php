<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UrlHarvestResultStore
{
    private const RESULTS_DIRNAME = 'results';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/collect/url_lists')]
        private readonly string $baseDir,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     */
    public function store(string $source, string $url, array $result): ?string
    {
        $path = $this->resolveResultPath($source, $url);
        if ($path === null) {
            return null;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                return null;
            }
        }

        $payload = $result;
        $payload['stored_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return null;
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            return null;
        }

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $source, string $url): ?array
    {
        $path = $this->resolveResultPath($source, $url);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public function hasResult(string $source, string $url): bool
    {
        $path = $this->resolveResultPath($source, $url);
        return $path !== null && is_file($path);
    }

    public function getPublicKey(string $url): string
    {
        return hash('sha1', $url);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadByKey(string $source, string $key): ?array
    {
        $path = $this->resolveResultPathByKey($source, $key);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public function hasResultKey(string $source, string $key): bool
    {
        $path = $this->resolveResultPathByKey($source, $key);
        return $path !== null && is_file($path);
    }

    private function resolveResultPath(string $source, string $url): ?string
    {
        $source = trim($source);
        if ($source === '' || preg_match('/[^a-zA-Z0-9_-]/', $source)) {
            return null;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $hash = $this->getPublicKey($url);

        return $this->resolveResultPathByKey($source, $hash);
    }

    private function resolveResultPathByKey(string $source, string $key): ?string
    {
        $source = trim($source);
        if ($source === '' || preg_match('/[^a-zA-Z0-9_-]/', $source)) {
            return null;
        }

        $key = trim($key);
        if ($key === '' || preg_match('/[^a-f0-9]/i', $key)) {
            return null;
        }

        return $this->baseDir
            . DIRECTORY_SEPARATOR
            . $source
            . DIRECTORY_SEPARATOR
            . self::RESULTS_DIRNAME
            . DIRECTORY_SEPARATOR
            . $key
            . '.json';
    }
}
