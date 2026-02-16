<?php

namespace App\Entity;

use App\Repository\PresentationEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresentationEventRepository::class)]
#[ORM\Table(name: 'presentation_event')]
#[ORM\Index(columns: ['type'], name: 'idx_presentation_event_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_presentation_event_created_at')]
#[ORM\Index(columns: ['project_presentation_id'], name: 'idx_presentation_event_pp')]
#[ORM\Index(columns: ['visitor_hash'], name: 'idx_presentation_event_visitor')]
class PresentationEvent
{
    public const TYPE_VIEW = 'view';
    public const TYPE_SHARE_OPEN = 'share_open';
    public const TYPE_SHARE_COPY = 'share_copy';
    public const TYPE_SHARE_EXTERNAL = 'share_external';
    public const TYPE_HOME_FEED_IMPRESSION = 'home_feed_impression';
    public const TYPE_HOME_FEED_CLICK = 'home_feed_click';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: PPBase::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PPBase $projectPresentation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visitorHash = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $meta = null;

    public function __construct(string $type, PPBase $presentation)
    {
        $this->type = $type;
        $this->projectPresentation = $presentation;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getProjectPresentation(): ?PPBase
    {
        return $this->projectPresentation;
    }

    public function setProjectPresentation(PPBase $presentation): self
    {
        $this->projectPresentation = $presentation;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getVisitorHash(): ?string
    {
        return $this->visitorHash;
    }

    public function setVisitorHash(?string $visitorHash): self
    {
        $this->visitorHash = $visitorHash;
        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
}
