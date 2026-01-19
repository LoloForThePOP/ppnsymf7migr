<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UrlHarvestListService
{
    private const URLS_FILENAME = 'urls.csv';
    private const PROMPT_FILENAME = 'prompt.txt';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/collect/url_lists')]
        private readonly string $baseDir,
    ) {
    }

    /**
     * @return array<int, array{name: string, has_urls: bool, has_prompt: bool}>
     */
    public function listSources(): array
    {
        if (!is_dir($this->baseDir)) {
            return [];
        }

        $entries = [];
        $items = scandir($this->baseDir);
        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $this->baseDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($path)) {
                continue;
            }

            $entries[] = [
                'name' => $item,
                'has_urls' => is_file($path . DIRECTORY_SEPARATOR . self::URLS_FILENAME),
                'has_prompt' => is_file($path . DIRECTORY_SEPARATOR . self::PROMPT_FILENAME),
            ];
        }

        usort($entries, static fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        return $entries;
    }

    public function readPrompt(string $source): string
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            return '';
        }

        $file = $path . DIRECTORY_SEPARATOR . self::PROMPT_FILENAME;
        if (!is_file($file)) {
            return '';
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        return trim($content);
    }

    public function writePrompt(string $source, string $content): void
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            throw new \RuntimeException('Source introuvable.');
        }

        $file = $path . DIRECTORY_SEPARATOR . self::PROMPT_FILENAME;
        $payload = rtrim($content);
        if ($payload !== '') {
            $payload .= "\n";
        }

        if (file_put_contents($file, $payload) === false) {
            throw new \RuntimeException('Impossible d\'écrire le prompt.');
        }
    }

    /**
     * @return array{entries: array<int, array{url:string,status:string,last_run_at:string,error:string,notes:string,created_string_id:string,created_url:string}>, error: ?string}
     */
    public function loadEntries(string $source): array
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            return ['entries' => [], 'error' => 'Source introuvable.'];
        }

        $file = $path . DIRECTORY_SEPARATOR . self::URLS_FILENAME;
        if (!is_file($file)) {
            return ['entries' => [], 'error' => 'Fichier urls.csv introuvable.'];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return ['entries' => [], 'error' => 'Impossible de lire urls.csv.'];
        }

        $lines = array_values(array_filter($lines, static fn(string $line) => trim($line) !== ''));
        if ($lines === []) {
            return ['entries' => [], 'error' => null];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, $delimiter);
        }

        $header = [];
        $firstRow = $rows[0] ?? [];
        $firstRow = array_map('trim', $firstRow);
        $lower = array_map('strtolower', $firstRow);
        if (in_array('url', $lower, true)) {
            $header = $lower;
            array_shift($rows);
        } else {
            $header = ['url'];
        }

        $entries = [];
        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                continue;
            }
            $row = array_map('trim', $row);
            $mapped = [];
            foreach ($header as $idx => $column) {
                $mapped[$column] = $row[$idx] ?? '';
            }

            $url = $mapped['url'] ?? $row[0] ?? '';
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            $entries[] = [
                'url' => $url,
                'status' => $this->normalizeStatus($mapped['status'] ?? ''),
                'last_run_at' => (string) ($mapped['last_run_at'] ?? ''),
                'error' => (string) ($mapped['error'] ?? ''),
                'notes' => (string) ($mapped['notes'] ?? ''),
                'created_string_id' => (string) ($mapped['created_string_id'] ?? ''),
                'created_url' => (string) ($mapped['created_url'] ?? ''),
            ];
        }

        return ['entries' => $entries, 'error' => null];
    }

    /**
     * @param array<int, array{url:string,status:string,last_run_at:string,error:string,notes:string,created_string_id:string,created_url:string}> $entries
     */
    public function saveEntries(string $source, array $entries): void
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            throw new \RuntimeException('Source introuvable.');
        }

        $file = $path . DIRECTORY_SEPARATOR . self::URLS_FILENAME;
        $handle = fopen($file, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'écrire urls.csv.');
        }

        fputcsv($handle, ['url', 'status', 'last_run_at', 'error', 'notes', 'created_string_id', 'created_url']);
        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry['url'] ?? '',
                $entry['status'] ?? 'pending',
                $entry['last_run_at'] ?? '',
                $entry['error'] ?? '',
                $entry['notes'] ?? '',
                $entry['created_string_id'] ?? '',
                $entry['created_url'] ?? '',
            ]);
        }

        fclose($handle);
    }

    private function resolveSourcePath(string $source): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (preg_match('/[^a-zA-Z0-9_-]/', $source)) {
            return null;
        }

        $path = $this->baseDir . DIRECTORY_SEPARATOR . $source;
        if (!is_dir($path)) {
            return null;
        }

        return $path;
    }

    private function detectDelimiter(string $line): string
    {
        foreach ([';', "\t", ','] as $delimiter) {
            if (substr_count($line, $delimiter) > 0) {
                return $delimiter;
            }
        }

        return ',';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return 'pending';
        }

        return $status;
    }
}
