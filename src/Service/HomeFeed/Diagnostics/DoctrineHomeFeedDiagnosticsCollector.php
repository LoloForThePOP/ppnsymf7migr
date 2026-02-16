<?php

namespace App\Service\HomeFeed\Diagnostics;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;

final class DoctrineHomeFeedDiagnosticsCollector implements HomeFeedDiagnosticsCollectorInterface
{
    public function __construct(
        private readonly BacktraceDebugDataHolder $debugDataHolder,
    ) {
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function snapshot(): array
    {
        $queryCount = 0;
        $queryMs = 0.0;
        $data = $this->debugDataHolder->getData();

        foreach ($data as $queries) {
            if (!is_array($queries)) {
                continue;
            }

            foreach ($queries as $query) {
                if (!is_array($query)) {
                    continue;
                }

                $queryCount++;
                $executionMs = $query['executionMS'] ?? 0.0;
                if (is_numeric($executionMs)) {
                    // Doctrine debug collector stores query duration in seconds.
                    $queryMs += ((float) $executionMs) * 1000.0;
                }
            }
        }

        return [
            'queryCount' => $queryCount,
            'queryMs' => $queryMs,
        ];
    }
}
