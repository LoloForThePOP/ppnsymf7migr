<?php

namespace App\Entity\Embeddables\PPBase;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class IngestionMetadata
{
    // Canonical URL of the scraped source page (used for dedup/provenance)
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    // Fixed-length SHA-256 hex hash of the source URL for fast dedup/indexing.
    // Hex string keeps storage/ORM handling simple vs. binary blobs.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceUrlHash = null;

    // Organization as read from the source (kept separate from any linked profile)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceOrganizationName = null;

    // Main website discovered on the source (if any) (related websites and maybe that one are kept in the PPBase.websites field)
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceOrganizationWebsite = null;

    // Publication/update date extracted from the source page: date the project was first published or last updated on the source page when scrapped in order to confront with possible updates later on
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $sourcePublishedAt = null;

    // When this record was ingested by the scraper
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $ingestedAt = null;

    // Ingestion outcome flag (ok|duplicate|skipped|invalid)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ingestionStatus = null;

    // Optional short comment explaining the ingestion status
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ingestionStatusComment = null;

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $sourceUrl = $sourceUrl !== null ? trim($sourceUrl) : null;
        $this->sourceUrl = $sourceUrl === '' ? null : $sourceUrl;

        if ($this->sourceUrl === null) {
            $this->sourceUrlHash = null;
        } else {
            $this->sourceUrlHash = hash('sha256', $this->sourceUrl);
        }

        return $this;
    }

    public function getSourceUrlHash(): ?string
    {
        return $this->sourceUrlHash;
    }

    public function getSourceOrganizationName(): ?string
    {
        return $this->sourceOrganizationName;
    }

    public function setSourceOrganizationName(?string $name): self
    {
        $this->sourceOrganizationName = $name;
        return $this;
    }

    public function getSourceOrganizationWebsite(): ?string
    {
        return $this->sourceOrganizationWebsite;
    }

    public function setSourceOrganizationWebsite(?string $website): self
    {
        $this->sourceOrganizationWebsite = $website;
        return $this;
    }

    public function getSourcePublishedAt(): ?\DateTimeInterface
    {
        return $this->sourcePublishedAt;
    }

    public function setSourcePublishedAt(?\DateTimeInterface $date): self
    {
        $this->sourcePublishedAt = $date;
        return $this;
    }

    public function getIngestedAt(): ?\DateTimeInterface
    {
        return $this->ingestedAt;
    }

    public function setIngestedAt(?\DateTimeInterface $ingestedAt): self
    {
        $this->ingestedAt = $ingestedAt;
        return $this;
    }

    public function getIngestionStatus(): ?string
    {
        return $this->ingestionStatus;
    }

    public function setIngestionStatus(?string $ingestionStatus): self
    {
        $this->ingestionStatus = $ingestionStatus;
        return $this;
    }

    public function getIngestionStatusComment(): ?string
    {
        return $this->ingestionStatusComment;
    }

    public function setIngestionStatusComment(?string $comment): self
    {
        $this->ingestionStatusComment = $comment;
        return $this;
    }
}
