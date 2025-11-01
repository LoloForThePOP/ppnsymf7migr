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

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;

    #[ORM\ManyToOne(inversedBy: 'needs')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?PPBase $project = null;



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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getProject(): ?PPBase
    {
        return $this->project;
    }

    public function setProject(?PPBase $project): static
    {
        $this->project = $project;

        return $this;
    }



}
