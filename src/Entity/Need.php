<?php

namespace App\Entity;

use App\Enum\NeedPaidStatus;
use App\Enum\NeedType;
use App\Repository\NeedRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NeedRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Need
{
    // ────────────────────────────────────────
    // Primary key
    // ────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ────────────────────────────────────────
    // Core fields
    // ────────────────────────────────────────

    #[ORM\Column(enumType: NeedType::class, nullable: true)]
    #[Assert\Choice(callback: [NeedType::class, 'values'], message: 'Veuillez renseigner un type de besoin valide.')]
    private ?NeedType $type = null;

    #[ORM\Column(enumType: NeedPaidStatus::class, nullable: true)]
    #[Assert\Choice(callback: [NeedPaidStatus::class, 'values'], message: 'Veuillez renseigner un type de transaction valide (payé, non, ou peut-être).')]
    private ?NeedPaidStatus $isPaid = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du besoin est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 100,
        minMessage: 'Le titre du besoin doit faire au minimum {{ limit }} caractères.',
        maxMessage: 'Le titre du besoin doit faire au maximum {{ limit }} caractères.'
    )]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: PPBase::class, inversedBy: 'needs')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?PPBase $presentation = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;

    // ────────────────────────────────────────
    // Timestamps
    // ────────────────────────────────────────

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ────────────────────────────────────────
    // Constructor / Lifecycle
    // ────────────────────────────────────────

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ────────────────────────────────────────
    // Getters / Setters
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?NeedType
    {
        return $this->type;
    }

    public function setType(?NeedType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getIsPaid(): ?NeedPaidStatus
    {
        return $this->isPaid;
    }

    public function setIsPaid(?NeedPaidStatus $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPresentation(): ?PPBase
    {
        return $this->presentation;
    }

    public function setPresentation(?PPBase $presentation): self
    {
        $this->presentation = $presentation;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────

    public function __toString(): string
    {
        return $this->title ?? 'Besoin';
    }
    
}
