<?php

namespace App\Entity;

use App\Enum\NeedPaidStatus;
use App\Enum\NeedStatus;
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

    #[ORM\Column(name: 'payment_status', enumType: NeedPaidStatus::class, nullable: true)]
    #[Assert\Choice(callback: [NeedPaidStatus::class, 'values'], message: 'Veuillez renseigner un type de transaction valide (payé, non, ou peut-être).')]
    private ?NeedPaidStatus $paymentStatus = null;

    #[ORM\Column(enumType: NeedStatus::class)]
    #[Assert\Choice(callback: [NeedStatus::class, 'values'], message: 'Veuillez renseigner un statut de besoin valide.')]
    private NeedStatus $status = NeedStatus::Open;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le titre du besoin est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 100,
        minMessage: 'Le titre du besoin doit faire au minimum {{ limit }} caractères.',
        maxMessage: 'Le titre du besoin doit faire au maximum {{ limit }} caractères.'
    )]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description du besoin doit faire au maximum {{ limit }} caractères.'
    )]
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

    public function getPaymentStatus(): ?NeedPaidStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?NeedPaidStatus $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getStatus(): NeedStatus
    {
        return $this->status;
    }

    public function setStatus(NeedStatus $status): self
    {
        $this->status = $status;
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

    public function getProjectPresentation(): ?PPBase
    {
        return $this->project;
    }

    public function setProject(?PPBase $project): static
    {
        $this->project = $project;

        return $this;
    }



}
