<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UrlHarvestListService
{
    private const URLS_FILENAME = 'urls.csv';
    private const PROMPT_FILENAME = 'prompt.txt';
    private const CONFIG_FILENAME = 'config.json';
    private const ALLOWED_STATUSES = ['pending', 'queued', 'processing', 'done', 'normalized', 'error', 'skipped'];
    private const DEFAULT_PAYLOAD_POLICY = [
        'min_text_chars' => 600,
        'warn_text_chars' => 350,
        'min_assets' => 2,
    ];
    private const DEFAULT_QUEUE_STATE = [
        'paused' => false,
        'running' => false,
        'persist' => true,
        'remaining' => null,
    ];
    private const SOURCE_PAYLOAD_POLICIES = [
        'je_veux_aider' => [
            'min_text_chars' => 250,
            'warn_text_chars' => 160,
            'min_assets' => 1,
        ],
        'fondation_du_patrimoine' => [
            'min_text_chars' => 400,
            'warn_text_chars' => 250,
            'min_assets' => 1,
        ],
    ];

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

    /**
     * @return array{min_text_chars:int,warn_text_chars:int,min_assets:int}
     */
    public function readPayloadPolicy(string $source): array
    {
        $policy = self::DEFAULT_PAYLOAD_POLICY;

        if (isset(self::SOURCE_PAYLOAD_POLICIES[$source])) {
            $policy = array_merge($policy, self::SOURCE_PAYLOAD_POLICIES[$source]);
        }

        $config = $this->readConfig($source);
        if (isset($config['payload']) && is_array($config['payload'])) {
            $payload = $config['payload'];
            foreach (self::DEFAULT_PAYLOAD_POLICY as $key => $default) {
                if (isset($payload[$key]) && is_int($payload[$key])) {
                    $policy[$key] = $payload[$key];
                }
            }
        }

        return $policy;
    }

    /**
     * @return array{paused:bool,running:bool,persist:bool}
     */
    public function readQueueState(string $source): array
    {
        $state = self::DEFAULT_QUEUE_STATE;
        $config = $this->readConfig($source);
        if (isset($config['queue']) && is_array($config['queue'])) {
            $queue = $config['queue'];
            if (isset($queue['paused'])) {
                $state['paused'] = filter_var($queue['paused'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($queue['running'])) {
                $state['running'] = filter_var($queue['running'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($queue['persist'])) {
                $state['persist'] = filter_var($queue['persist'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('remaining', $queue)) {
                $remaining = $queue['remaining'];
                $state['remaining'] = is_int($remaining) ? $remaining : (is_numeric($remaining) ? (int) $remaining : null);
            }
        }

        return $state;
    }

    /**
     * @param array{paused?:bool,running?:bool,persist?:bool,remaining?:?int} $state
     */
    public function writeQueueState(string $source, array $state): void
    {
        $config = $this->readConfig($source);
        $queue = self::DEFAULT_QUEUE_STATE;
        if (isset($config['queue']) && is_array($config['queue'])) {
            $queue = array_merge($queue, $config['queue']);
        }
        $queue = array_merge($queue, $state);
        $config['queue'] = $queue;

        $this->writeConfig($source, $config);
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
     * @return array{entries: array<int, array{url:string,status:string,last_run_at:string,error:string,notes:string,created_string_id:string,created_url:string,payload_status:string,payload_text_chars:string,payload_links:string,payload_images:string}>, error: ?string}
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
            $url = $this->normalizeUrl($url);
            if ($url === null) {
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
                'payload_status' => (string) ($mapped['payload_status'] ?? ''),
                'payload_text_chars' => (string) ($mapped['payload_text_chars'] ?? ''),
                'payload_links' => (string) ($mapped['payload_links'] ?? ''),
                'payload_images' => (string) ($mapped['payload_images'] ?? ''),
            ];
        }

        return ['entries' => $entries, 'error' => null];
    }

    /**
     * @param array<int, array{url:string,status:string,last_run_at:string,error:string,notes:string,created_string_id:string,created_url:string,payload_status:string,payload_text_chars:string,payload_links:string,payload_images:string}> $entries
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

        fputcsv($handle, [
            'url',
            'status',
            'last_run_at',
            'error',
            'notes',
            'created_string_id',
            'created_url',
            'payload_status',
            'payload_text_chars',
            'payload_links',
            'payload_images',
        ]);
        foreach ($entries as $entry) {
            $url = $this->normalizeUrl($entry['url'] ?? '');
            if ($url === null) {
                continue;
            }
            fputcsv($handle, [
                $url,
                $this->normalizeStatus($entry['status'] ?? 'pending'),
                $entry['last_run_at'] ?? '',
                $entry['error'] ?? '',
                $entry['notes'] ?? '',
                $entry['created_string_id'] ?? '',
                $entry['created_url'] ?? '',
                $entry['payload_status'] ?? '',
                $entry['payload_text_chars'] ?? '',
                $entry['payload_links'] ?? '',
                $entry['payload_images'] ?? '',
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

    public function resolveQueueLockPath(string $source): ?string
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            return null;
        }

        return $path . DIRECTORY_SEPARATOR . '.queue.lock';
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(string $source): array
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            return [];
        }

        $file = $path . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $payload = json_decode($content, true);
        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeConfig(string $source, array $config): void
    {
        $path = $this->resolveSourcePath($source);
        if ($path === null) {
            throw new \RuntimeException('Source introuvable.');
        }

        $file = $path . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;
        $payload = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new \RuntimeException('Configuration invalide.');
        }

        if (file_put_contents($file, $payload . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d\'écrire la configuration.');
        }
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

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return 'pending';
        }

        return $status;
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return $value;
    }
}
