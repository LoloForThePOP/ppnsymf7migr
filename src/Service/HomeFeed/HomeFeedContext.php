<?php

namespace App\Service\HomeFeed;

use App\Entity\User;

final class HomeFeedContext
{
    /**
     * @param string[] $anonCategoryHints
     */
    public function __construct(
        private readonly ?User $viewer,
        private readonly int $cardsPerBlock = 10,
        private readonly int $maxBlocks = 6,
        private readonly array $anonCategoryHints = [],
        private readonly bool $creatorCapEnabled = false,
        private readonly int $creatorCapPerBlock = 2,
    ) {
    }

    public function getViewer(): ?User
    {
        return $this->viewer;
    }

    public function isLoggedIn(): bool
    {
        return $this->viewer instanceof User;
    }

    public function getCardsPerBlock(): int
    {
        return max(8, min(12, $this->cardsPerBlock));
    }

    public function getMaxBlocks(): int
    {
        return max(1, min(12, $this->maxBlocks));
    }

    /**
     * @return string[]
     */
    public function getAnonCategoryHints(): array
    {
        return array_slice(array_values(array_unique($this->anonCategoryHints)), 0, 8);
    }

    public function isCreatorCapEnabled(): bool
    {
        return $this->creatorCapEnabled;
    }

    public function getCreatorCapPerBlock(): int
    {
        return max(1, min(12, $this->creatorCapPerBlock));
    }
}
