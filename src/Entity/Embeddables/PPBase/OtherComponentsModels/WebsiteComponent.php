<?php

namespace App\Entity\Embeddables\PPBase\OtherComponentsModels;
 
use Symfony\Component\Validator\Constraints as Assert;

class WebsiteComponent implements ComponentInterface
{
    // ============================
    // User-provided fields
    // ============================

    #[Assert\NotBlank(groups: ['input'])] //validation group allows to validate specific properties.
    #[Assert\Length(max: 150, groups: ['input'])]
    private string $title;

    #[Assert\NotBlank(groups: ['input'])]
    #[Assert\Url(
        requireTld: true,
        groups: ['input'],
        message: 'Vous devez utiliser une adresse web valide'
    )]
    private string $url;


    // ============================
    // System fields (never validated in "input" group)
    // ============================

    private string $id;
    private string $icon = '';               // assigned by WebsiteProcessingService
    private int $position = 0;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;


    // ============================
    // Constructor
    // ============================

    public function __construct(
        string $id,
        string $title,
        string $url,
        string $icon,
        int $position,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        $this->icon = $icon;
        $this->position = $position;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }


    // ============================
    // Factory for new items
    // ============================

    public static function createNew(string $title, string $url): self
    {
        return new self(
            id: bin2hex(random_bytes(16)),
            title: $title,
            url: $url,
            icon: '',
            position: 0, // This will be overridden automatically by addComponent method
            createdAt: new \DateTimeImmutable(),
            updatedAt: null
        );
    }



    // ============================
    // Getters & Setters
    // ============================

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }
    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }


    // ============================
    // Array Serialization
    // ============================

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'url'       => $this->url,
            'icon'      => $this->icon,
            'position'  => $this->position,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }



public static function fromArray(array $data): self
{
    $createdAt = $data['createdAt'] ?? null;
    if (!$createdAt instanceof \DateTimeImmutable) {
        // Fallback to "now" or null
        $createdAt = new \DateTimeImmutable();
    }

    $updatedAt = $data['updatedAt'] ?? null;
    if ($updatedAt !== null && !$updatedAt instanceof \DateTimeImmutable) {
        $updatedAt = null;
    }

    return new self(
        id: $data['id'],
        title: $data['title'],
        url: $data['url'],
        icon: $data['icon'] ?? '',
        position: $data['position'] ?? 0,
        createdAt: $createdAt,
        updatedAt: $updatedAt,
    );
}









}
