<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'presentation_neighbors',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_neighbor', columns: ['presentation_id', 'neighbor_id', 'model']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_presentation_model', columns: ['presentation_id', 'model', '`rank`']),
        new ORM\Index(name: 'idx_neighbor', columns: ['neighbor_id']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class PresentationNeighbor
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: PPBase::class)]
    #[ORM\JoinColumn(name: 'presentation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PPBase $presentation;

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Id]
    #[ORM\Column(name: '`rank`', type: 'smallint')]
    private int $rank;

    #[ORM\ManyToOne(targetEntity: PPBase::class)]
    #[ORM\JoinColumn(name: 'neighbor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PPBase $neighbor;

    #[ORM\Column(type: 'float')]
    private float $score;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(PPBase $presentation, PPBase $neighbor, string $model, int $rank)
    {
        $this->presentation = $presentation;
        $this->neighbor = $neighbor;
        $this->model = $model;
        $this->rank = $rank;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPresentation(): PPBase
    {
        return $this->presentation;
    }

    public function getNeighbor(): PPBase
    {
        return $this->neighbor;
    }

    public function setNeighbor(PPBase $neighbor): self
    {
        $this->neighbor = $neighbor;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): self
    {
        $this->rank = $rank;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
