<?php

namespace App\Message;

final class UluleImportTickMessage
{
    public function __construct(private readonly string $runId)
    {
    }

    public function getRunId(): string
    {
        return $this->runId;
    }
}
