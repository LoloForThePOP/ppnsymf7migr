<?php

namespace App\Entity\Embeddables\PPBase;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Extra
{
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $viewsCount = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isRandomizedStringId = true;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['default' => 0])]
    private ?int $overallQualityAssessment = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $arePrivateMessagesActivated = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $cacheThumbnailUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortEditorialText = null;

    // ────────────────────────────────────────
    // Views
    // ────────────────────────────────────────

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function incrementViews(int $by = 1): self
    {
        $this->viewsCount += max(1, $by);
        return $this;
    }

    public function resetViews(): self
    {
        $this->viewsCount = 0;
        return $this;
    }

    // ────────────────────────────────────────
    // String ID randomization
    // ────────────────────────────────────────

    public function isRandomizedStringId(): bool
    {
        return $this->isRandomizedStringId;
    }

    public function setIsRandomizedStringId(bool $state): self
    {
        $this->isRandomizedStringId = $state;
        return $this;
    }

    // ────────────────────────────────────────
    // Quality Assessment
    // ────────────────────────────────────────

    public function getOverallQualityAssessment(): ?int
    {
        return $this->overallQualityAssessment;
    }

    public function setOverallQualityAssessment(?int $score): self
    {
        $this->overallQualityAssessment = $score;
        return $this;
    }

    // ────────────────────────────────────────
    // Private Messages
    // ────────────────────────────────────────

    public function arePrivateMessagesActivated(): bool
    {
        return $this->arePrivateMessagesActivated;
    }

    public function setArePrivateMessagesActivated(bool $enabled): self
    {
        $this->arePrivateMessagesActivated = $enabled;
        return $this;
    }

    // ────────────────────────────────────────
    // Cache
    // ────────────────────────────────────────

    public function getCacheThumbnailUrl(): ?string
    {
        return $this->cacheThumbnailUrl;
    }

    public function setCacheThumbnailUrl(?string $url): self
    {
        $this->cacheThumbnailUrl = $url;
        return $this;
    }

    // ────────────────────────────────────────
    // Editorial
    // ────────────────────────────────────────

    public function getShortEditorialText(): ?string
    {
        return $this->shortEditorialText;
    }

    public function setShortEditorialText(?string $text): self
    {
        $this->shortEditorialText = $text;
        return $this;
    }

}
