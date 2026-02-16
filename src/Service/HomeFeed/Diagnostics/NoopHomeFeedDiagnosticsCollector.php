<?php

namespace App\Service\HomeFeed\Diagnostics;

final class NoopHomeFeedDiagnosticsCollector implements HomeFeedDiagnosticsCollectorInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function snapshot(): array
    {
        return [
            'queryCount' => 0,
            'queryMs' => 0.0,
        ];
    }
}

