<?php

namespace App\Entity;

use App\Repository\UluleProjectCatalogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UluleProjectCatalogRepository::class)]
#[ORM\Table(name: 'ulule_project_catalog')]
class UluleProjectCatalog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $ululeId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $lang = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?bool $goalRaised = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isOnline = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isCancelled = null;

    #[ORM\Column(nullable: true)]
    private ?int $descriptionLength = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastImportedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ululeCreatedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $importStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $importStatusComment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $importedStringId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastError = null;

    public function __construct(int $ululeId)
    {
        $this->ululeId = $ululeId;
        $this->importStatus = self::STATUS_PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUluleId(): int
    {
        return $this->ululeId;
    }

    public function setUluleId(int $ululeId): self
    {
        $this->ululeId = $ululeId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $sourceUrl = $sourceUrl !== null ? trim($sourceUrl) : null;
        $this->sourceUrl = $sourceUrl === '' ? null : $sourceUrl;
        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getGoalRaised(): ?bool
    {
        return $this->goalRaised;
    }

    public function setGoalRaised(?bool $goalRaised): self
    {
        $this->goalRaised = $goalRaised;
        return $this;
    }

    public function getIsOnline(): ?bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(?bool $isOnline): self
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function getIsCancelled(): ?bool
    {
        return $this->isCancelled;
    }

    public function setIsCancelled(?bool $isCancelled): self
    {
        $this->isCancelled = $isCancelled;
        return $this;
    }

    public function getDescriptionLength(): ?int
    {
        return $this->descriptionLength;
    }

    public function setDescriptionLength(?int $descriptionLength): self
    {
        $this->descriptionLength = $descriptionLength;
        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function getLastImportedAt(): ?\DateTimeImmutable
    {
        return $this->lastImportedAt;
    }

    public function setLastImportedAt(?\DateTimeImmutable $lastImportedAt): self
    {
        $this->lastImportedAt = $lastImportedAt;
        return $this;
    }

    public function getUluleCreatedAt(): ?\DateTimeImmutable
    {
        return $this->ululeCreatedAt;
    }

    public function setUluleCreatedAt(?\DateTimeImmutable $ululeCreatedAt): self
    {
        $this->ululeCreatedAt = $ululeCreatedAt;
        return $this;
    }

    public function getImportStatus(): ?string
    {
        return $this->importStatus;
    }

    public function setImportStatus(?string $importStatus): self
    {
        $this->importStatus = $importStatus;
        return $this;
    }

    public function getImportStatusComment(): ?string
    {
        return $this->importStatusComment;
    }

    public function setImportStatusComment(?string $importStatusComment): self
    {
        $this->importStatusComment = $importStatusComment;
        return $this;
    }

    public function getImportedStringId(): ?string
    {
        return $this->importedStringId;
    }

    public function setImportedStringId(?string $importedStringId): self
    {
        $this->importedStringId = $importedStringId;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }
}
