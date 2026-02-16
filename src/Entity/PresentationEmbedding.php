<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'presentation_embeddings')]
#[ORM\HasLifecycleCallbacks]
class PresentationEmbedding
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: PPBase::class)]
    #[ORM\JoinColumn(name: 'presentation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PPBase $presentation;

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(type: 'smallint')]
    private int $dims;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $normalized = true;

    #[ORM\Column(type: 'blob')]
    private mixed $vector;

    #[ORM\Column(type: 'binary', length: 32)]
    private mixed $contentHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(PPBase $presentation, string $model)
    {
        $this->presentation = $presentation;
        $this->model = $model;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDims(): int
    {
        return $this->dims;
    }

    public function setDims(int $dims): self
    {
        $this->dims = $dims;

        return $this;
    }

    public function isNormalized(): bool
    {
        return $this->normalized;
    }

    public function setNormalized(bool $normalized): self
    {
        $this->normalized = $normalized;

        return $this;
    }

    public function getVectorBinary(): string
    {
        if (is_resource($this->vector)) {
            $contents = stream_get_contents($this->vector);
            return $contents === false ? '' : $contents;
        }

        return (string) $this->vector;
    }

    public function setVectorBinary(string $vector): self
    {
        $this->vector = $vector;

        return $this;
    }

    public function getContentHash(): string
    {
        if (is_resource($this->contentHash)) {
            $metadata = stream_get_meta_data($this->contentHash);
            if (is_array($metadata) && !($metadata['seekable'] ?? false)) {
                $contents = stream_get_contents($this->contentHash);
                return $contents === false ? '' : $contents;
            }

            rewind($this->contentHash);
            $contents = stream_get_contents($this->contentHash);
            return $contents === false ? '' : $contents;
        }

        return (string) $this->contentHash;
    }

    public function setContentHash(string $contentHash): self
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
