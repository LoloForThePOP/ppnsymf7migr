<?php

namespace App\Entity\Embeddables\PPBase\OtherComponentsModels;

use Symfony\Component\Validator\Constraints as Assert;

class BusinessCardComponent implements ComponentInterface
{
    // User-provided fields
    #[Assert\Length(max: 100, groups: ['input'])]
    #[Assert\Regex(pattern: '/\S+/', message: 'Ce champ ne peut pas être vide ou contenir uniquement des espaces.', groups: ['input'])]
    private ?string $title;

    #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.', groups: ['input'])]
    #[Assert\Length(max: 180, groups: ['input'])]
    private ?string $email1;

    #[Assert\Length(min: 6, max: 20, groups: ['input'])]
    #[Assert\Regex(pattern: '/^[0-9+\s().-]{6,20}$/', message: 'Veuillez saisir un numéro de téléphone valide.', groups: ['input'])]
    private ?string $tel1;

    #[Assert\Url(message: 'Veuillez saisir une adresse web valide.', groups: ['input'])]
    #[Assert\Length(max: 255, groups: ['input'])]
    private ?string $website1;

    #[Assert\Url(message: 'Veuillez saisir une adresse web valide.', groups: ['input'])]
    #[Assert\Length(max: 255, groups: ['input'])]
    private ?string $website2;

    #[Assert\Length(max: 500, groups: ['input'])]
    private ?string $postalMail;

    #[Assert\Length(max: 500, groups: ['input'])]
    private ?string $remarks;

    // Internal fields
    private string $id;
    private int $position;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        ?string $title = null,
        ?string $email1 = null,
        ?string $tel1 = null,
        ?string $website1 = null,
        ?string $website2 = null,
        ?string $postalMail = null,
        ?string $remarks = null,
        int $position = 0,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->email1 = $email1;
        $this->tel1 = $tel1;
        $this->website1 = $website1;
        $this->website2 = $website2;
        $this->postalMail = $postalMail;
        $this->remarks = $remarks;
        $this->position = $position;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public static function createNew(): self
    {
        return new self(
            id: bin2hex(random_bytes(16)),
            position: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null
        );
    }

    public static function fromArray(array $data): self
    {
        $createdAt = isset($data['createdAt']) && is_string($data['createdAt'])
            ? new \DateTimeImmutable($data['createdAt'])
            : new \DateTimeImmutable();

        $updatedAt = isset($data['updatedAt']) && is_string($data['updatedAt'])
            ? new \DateTimeImmutable($data['updatedAt'])
            : null;

        return new self(
            id: $data['id'],
            title: $data['title'] ?? null,
            email1: $data['email1'] ?? null,
            tel1: $data['tel1'] ?? null,
            website1: $data['website1'] ?? null,
            website2: $data['website2'] ?? null,
            postalMail: $data['postalMail'] ?? null,
            remarks: $data['remarks'] ?? null,
            position: $data['position'] ?? 0,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'email1' => $this->email1,
            'tel1' => $this->tel1,
            'website1' => $this->website1,
            'website2' => $this->website2,
            'postalMail' => $this->postalMail,
            'remarks' => $this->remarks,
            'position' => $this->position,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }

    // getters/setters
    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }
    public function setUpdatedAt(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): void { $this->title = $title; }

    public function getEmail1(): ?string { return $this->email1; }
    public function setEmail1(?string $email1): void { $this->email1 = $email1; }

    public function getTel1(): ?string { return $this->tel1; }
    public function setTel1(?string $tel1): void { $this->tel1 = $tel1; }

    public function getWebsite1(): ?string { return $this->website1; }
    public function setWebsite1(?string $website1): void { $this->website1 = $website1; }

    public function getWebsite2(): ?string { return $this->website2; }
    public function setWebsite2(?string $website2): void { $this->website2 = $website2; }

    public function getPostalMail(): ?string { return $this->postalMail; }
    public function setPostalMail(?string $postalMail): void { $this->postalMail = $postalMail; }

    public function getRemarks(): ?string { return $this->remarks; }
    public function setRemarks(?string $remarks): void { $this->remarks = $remarks; }
}
