<?php

namespace App\Service\HomeFeed;

use App\Entity\User;

final class HomeFeedContext
{
    /**
     * @param string[] $anonCategoryHints
     * @param string[] $anonKeywordHints
     * @param array{lat: float, lng: float, radius: float}|null $locationHint
     */
    public function __construct(
        private readonly ?User $viewer,
        private readonly int $cardsPerBlock = 10,
        private readonly int $maxBlocks = 6,
        private readonly array $anonCategoryHints = [],
        private readonly array $anonKeywordHints = [],
        private readonly ?array $locationHint = null,
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

    /**
     * @return string[]
     */
    public function getAnonKeywordHints(): array
    {
        return array_slice(array_values(array_unique($this->anonKeywordHints)), 0, 16);
    }

    /**
     * @return array{lat: float, lng: float, radius: float}|null
     */
    public function getLocationHint(): ?array
    {
        if (!is_array($this->locationHint)) {
            return null;
        }

        $lat = $this->locationHint['lat'] ?? null;
        $lng = $this->locationHint['lng'] ?? null;
        $radius = $this->locationHint['radius'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        if (!is_numeric($radius)) {
            $radius = 10.0;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => max(1.0, min(200.0, (float) $radius)),
        ];
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
