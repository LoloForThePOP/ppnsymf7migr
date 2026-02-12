<?php

namespace App\Service\Recommendation;

use App\Entity\PPBase;

final class RecommendationResult
{
    /**
     * @param PPBase[] $items
     * @param array<int, array{likes:int, comments:int}> $stats
     */
    public function __construct(
        private readonly array $items,
        private readonly array $stats,
        private readonly bool $personalized,
    ) {
    }

    /**
     * @return PPBase[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<int, array{likes:int, comments:int}>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function isPersonalized(): bool
    {
        return $this->personalized;
    }
}

