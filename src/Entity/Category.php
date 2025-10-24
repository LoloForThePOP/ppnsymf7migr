<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\Traits\TimestampableTrait;


#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Category
{
    // ────────────────────────────────────────
    // Properties
    // ────────────────────────────────────────

    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Un nom unique est requis.')]
    #[Assert\Length(max: 50, maxMessage: 'Le nom unique ne doit pas dépasser {{ limit }} caractères.')]
    #[Groups(['searchable'])]
    private ?string $uniqueName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['searchable'])]
    private ?string $label = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;

    // ────────────────────────────────────────
    // Category Icon (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[Vich\UploadableField(mapping: 'category_icon', fileNameProperty: 'image')]
    #[Assert\Image(
        maxSize: '50k',
        maxSizeMessage: 'Poids maximal accepté : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg', 'image/svg+xml', 'image/webp'],
        mimeTypesMessage: 'Format ({{ type }}) non pris en charge. Formats autorisés : {{ types }}'
    )]
    private ?File $imageFile = null;


    // ────────────────────────────────────────
    // Getters / Setters
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUniqueName(): ?string
    {
        return $this->uniqueName;
    }

    public function setUniqueName(string $uniqueName): self
    {
        $this->uniqueName = $uniqueName;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
    

}
