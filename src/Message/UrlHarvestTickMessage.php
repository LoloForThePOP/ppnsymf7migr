<?php

namespace App\Message;

final class UrlHarvestTickMessage
{
    public function __construct(
        private readonly string $source,
    ) {
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
