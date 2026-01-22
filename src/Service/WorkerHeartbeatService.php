<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WorkerHeartbeatService
{
    private const ACTIVE_WINDOW_SECONDS = 90;

    private string $path;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->path = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'collect' . DIRECTORY_SEPARATOR . 'worker_heartbeat.json';
    }

    public function touch(string $source = 'messenger'): void
    {
        $payload = [
            'last_seen_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'source' => $source,
        ];

        try {
            $dir = \dirname($this->path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents(
                $this->path,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        } catch (\Throwable) {
            // Avoid breaking background jobs if heartbeat fails.
        }
    }

    /**
     * @return array{active:bool,last_seen_at:?string,last_seen_label:string,source:?string}
     */
    public function getStatus(): array
    {
        if (!is_file($this->path)) {
            return [
                'active' => false,
                'last_seen_at' => null,
                'last_seen_label' => 'jamais',
                'source' => null,
            ];
        }

        $raw = file_get_contents($this->path);
        if (!is_string($raw) || $raw === '') {
            return [
                'active' => false,
                'last_seen_at' => null,
                'last_seen_label' => 'jamais',
                'source' => null,
            ];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [
                'active' => false,
                'last_seen_at' => null,
                'last_seen_label' => 'jamais',
                'source' => null,
            ];
        }

        $lastSeenRaw = is_string($data['last_seen_at'] ?? null) ? $data['last_seen_at'] : null;
        $source = is_string($data['source'] ?? null) ? $data['source'] : null;
        if ($lastSeenRaw === null) {
            return [
                'active' => false,
                'last_seen_at' => null,
                'last_seen_label' => 'jamais',
                'source' => $source,
            ];
        }

        try {
            $lastSeen = new \DateTimeImmutable($lastSeenRaw);
        } catch (\Throwable) {
            return [
                'active' => false,
                'last_seen_at' => null,
                'last_seen_label' => 'jamais',
                'source' => $source,
            ];
        }

        $now = new \DateTimeImmutable();
        $diffSeconds = max(0, $now->getTimestamp() - $lastSeen->getTimestamp());
        $active = $diffSeconds <= self::ACTIVE_WINDOW_SECONDS;

        return [
            'active' => $active,
            'last_seen_at' => $lastSeen->format(\DATE_ATOM),
            'last_seen_label' => $this->formatRelative($diffSeconds, $lastSeen),
            'source' => $source,
        ];
    }

    private function formatRelative(int $diffSeconds, \DateTimeImmutable $timestamp): string
    {
        if ($diffSeconds < 10) {
            return "a l'instant";
        }
        if ($diffSeconds < 60) {
            return sprintf('il y a %ds', $diffSeconds);
        }
        if ($diffSeconds < 3600) {
            return sprintf('il y a %dmin', (int) round($diffSeconds / 60));
        }
        if ($diffSeconds < 86400) {
            return sprintf('il y a %dh', (int) round($diffSeconds / 3600));
        }

        return $timestamp->format('d/m/Y H:i');
    }
}
