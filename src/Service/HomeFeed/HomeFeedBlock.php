<?php

namespace App\Service\HomeFeed;

use App\Entity\PPBase;

final class HomeFeedBlock
{
    /**
     * @param PPBase[] $items
     * @param array<int,array{likes:int,comments:int}> $stats
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly array $items,
        private readonly bool $personalized = false,
        private readonly array $stats = [],
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return PPBase[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isPersonalized(): bool
    {
        return $this->personalized;
    }

    /**
     * @return array<int,array{likes:int,comments:int}>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @param PPBase[] $items
     * @param array<int,array{likes:int,comments:int}> $stats
     */
    public function withItemsAndStats(array $items, array $stats): self
    {
        return new self(
            $this->key,
            $this->title,
            $items,
            $this->personalized,
            $stats
        );
    }
}

