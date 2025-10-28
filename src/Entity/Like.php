<?php

namespace App\Entity;

use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: 'project_like')]
#[ORM\UniqueConstraint(name: 'user_project_unique_like', columns: ['user_id', 'project_presentation_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_like_user')]
#[ORM\Index(columns: ['project_presentation_id'], name: 'idx_like_project_presentation')]
class Like
{
    // ─────────────────────────────────────────────
    // PROPERTIES
    // ─────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;


    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;


    
    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PPBase $projectPresentation = null;

    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    // ─────────────────────────────────────────────
    // CONSTRUCTOR
    // ─────────────────────────────────────────────

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ─────────────────────────────────────────────
    // GETTERS & SETTERS
    // ─────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
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

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    public function __toString(): string
    {
        return sprintf(
            '%s liked project #%d',
            $this->user?->getUsername() ?? 'Unknown user',
            $this->projectPresentation?->getId() ?? 0
        );
    }

    public function getProjectPresentation(): ?PPBase
    {
        return $this->projectPresentation;
    }

    public function setProjectPresentation(?PPBase $projectPresentation): static
    {
        $this->projectPresentation = $projectPresentation;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
