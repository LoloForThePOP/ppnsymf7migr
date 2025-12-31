<?php

namespace App\Service\AI;

final class PresentationEmbeddingResult
{
    /**
     * @param float[] $vector
     */
    public function __construct(
        public readonly string $model,
        public readonly int $dimensions,
        public readonly bool $normalized,
        public readonly array $vector,
        public readonly string $vectorBinary,
        public readonly string $contentHash,
        public readonly string $sourceText,
    ) {
    }
}
