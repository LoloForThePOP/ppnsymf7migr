<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UluleQueueStateService
{
    private const STATE_FILENAME = 'ulule_queue.json';
    private const DEFAULT_QUEUE_STATE = [
        'paused' => false,
        'running' => false,
        'remaining' => null,
        'run_id' => null,
        'current_id' => null,
        'last_processed_id' => null,
    ];
    private const DEFAULT_FILTERS = [
        'lang' => 'fr',
        'country' => 'FR',
        'status' => 'currently',
        'sort' => 'new',
        'page_start' => 1,
        'page_count' => 10,
        'min_description_length' => 500,
        'exclude_funded' => false,
        'include_video' => true,
        'include_secondary_images' => true,
        'extra_query' => '',
        'prompt_extra' => '',
        'eligible_only' => true,
        'status_filter' => 'pending',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/collect')]
        private readonly string $baseDir,
    ) {
    }

    /**
     * @return array{queue: array{paused:bool,running:bool,remaining:?int,run_id:?string,current_id:?int,last_processed_id:?int}, filters: array<string, mixed>}
     */
    public function readState(): array
    {
        $payload = $this->readStateFile();
        $queue = self::DEFAULT_QUEUE_STATE;
        if (isset($payload['queue']) && is_array($payload['queue'])) {
            $queue = array_merge($queue, $payload['queue']);
        }

        $filters = self::DEFAULT_FILTERS;
        if (isset($payload['filters']) && is_array($payload['filters'])) {
            $filters = array_merge($filters, $payload['filters']);
        }

        $queue['paused'] = filter_var($queue['paused'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $queue['running'] = filter_var($queue['running'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $queue['remaining'] = $this->normalizeNullableInt($queue['remaining'] ?? null);
        $queue['current_id'] = $this->normalizeNullableInt($queue['current_id'] ?? null);
        $queue['last_processed_id'] = $this->normalizeNullableInt($queue['last_processed_id'] ?? null);
        $queue['run_id'] = is_string($queue['run_id'] ?? null) ? $queue['run_id'] : null;

        return [
            'queue' => $queue,
            'filters' => $this->normalizeFilters($filters),
        ];
    }

    /**
     * @param array{queue?: array<string, mixed>, filters?: array<string, mixed>} $state
     */
    public function writeState(array $state): void
    {
        $current = $this->readState();
        $queue = $current['queue'];
        $filters = $current['filters'];

        if (isset($state['queue']) && is_array($state['queue'])) {
            $queue = array_merge($queue, $state['queue']);
        }
        if (isset($state['filters']) && is_array($state['filters'])) {
            $filters = array_merge($filters, $state['filters']);
        }

        $payload = [
            'queue' => $queue,
            'filters' => $filters,
        ];

        $this->writeStateFile($payload);
    }

    public function resolveLockPath(): string
    {
        return rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ulule_queue.lock';
    }

    /**
     * @return array<string, mixed>
     */
    private function readStateFile(): array
    {
        $file = $this->resolveStatePath();
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeStateFile(array $payload): void
    {
        $file = $this->resolveStatePath();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('État Ulule invalide.');
        }

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0775, true);
        }

        if (file_put_contents($file, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d\'écrire l\'état Ulule.');
        }
    }

    private function resolveStatePath(): string
    {
        return rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STATE_FILENAME;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $filters['lang'] = trim((string) ($filters['lang'] ?? self::DEFAULT_FILTERS['lang']));
        $filters['country'] = trim((string) ($filters['country'] ?? self::DEFAULT_FILTERS['country']));
        $filters['status'] = trim((string) ($filters['status'] ?? self::DEFAULT_FILTERS['status']));
        $filters['sort'] = trim((string) ($filters['sort'] ?? self::DEFAULT_FILTERS['sort']));
        $filters['page_start'] = max(1, (int) ($filters['page_start'] ?? self::DEFAULT_FILTERS['page_start']));
        $filters['page_count'] = max(1, (int) ($filters['page_count'] ?? self::DEFAULT_FILTERS['page_count']));
        $filters['min_description_length'] = max(0, (int) ($filters['min_description_length'] ?? self::DEFAULT_FILTERS['min_description_length']));
        $filters['exclude_funded'] = filter_var($filters['exclude_funded'] ?? self::DEFAULT_FILTERS['exclude_funded'], FILTER_VALIDATE_BOOLEAN);
        $filters['include_video'] = filter_var($filters['include_video'] ?? self::DEFAULT_FILTERS['include_video'], FILTER_VALIDATE_BOOLEAN);
        $filters['include_secondary_images'] = filter_var($filters['include_secondary_images'] ?? self::DEFAULT_FILTERS['include_secondary_images'], FILTER_VALIDATE_BOOLEAN);
        $filters['extra_query'] = trim((string) ($filters['extra_query'] ?? self::DEFAULT_FILTERS['extra_query']));
        $filters['prompt_extra'] = trim((string) ($filters['prompt_extra'] ?? self::DEFAULT_FILTERS['prompt_extra']));
        $filters['eligible_only'] = filter_var($filters['eligible_only'] ?? self::DEFAULT_FILTERS['eligible_only'], FILTER_VALIDATE_BOOLEAN);
        $filters['status_filter'] = trim((string) ($filters['status_filter'] ?? self::DEFAULT_FILTERS['status_filter']));

        return $filters;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }
}
