<?php

namespace App\Service\HomeFeed\Diagnostics;

interface HomeFeedDiagnosticsCollectorInterface
{
    public function isEnabled(): bool;

    /**
     * @return array{queryCount:int,queryMs:float}
     */
    public function snapshot(): array;
}

