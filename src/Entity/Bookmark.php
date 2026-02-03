<?php

namespace App\Entity;

use App\Repository\BookmarkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
#[ORM\Table(name: 'bookmark')]
#[ORM\UniqueConstraint(name: 'user_project_unique_bookmark', columns: ['user_id', 'project_presentation_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_bookmark_user')]
#[ORM\Index(columns: ['project_presentation_id'], name: 'idx_bookmark_project_presentation')]
class Bookmark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'bookmarks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PPBase $projectPresentation = null;

    #[ORM\ManyToOne(inversedBy: 'bookmarks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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
