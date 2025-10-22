<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProfileRepository;

use App\Entity\Traits\TimestampableTrait;

use Symfony\Component\HttpFoundation\File\File;

use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;


#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: ProfileRepository::class)]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Description cannot exceed {{ limit }} characters.'
    )]
    private ?string $description = null;

    
    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Url(message: 'Website 3 must be a valid URL.')]
    #[Assert\Length(max: 150)]
    private ?string $website1 = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Url(message: 'Website 3 must be a valid URL.')]
    #[Assert\Length(max: 150)]
    private ?string $website2 = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Url(message: 'Website 3 must be a valid URL.')]
    #[Assert\Length(max: 150)]
    private ?string $website3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;



    /**
     * This is not a mapped field of entity metadata, just a simple property.
     */
    #[Vich\UploadableField(mapping: 'persorg_image', fileNameProperty: 'image')]
    #[Assert\Image(
        maxSize: '1500k',
        maxSizeMessage: 'Poids maximal accepté pour l\'image : 1500 k',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'],
        mimeTypesMessage: 'Le format de fichier ({{ type }}) n\'est pas pris en compte. Les formats acceptés sont : {{ types }}'
    )]
    private ?File $imageFile = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $postalMail = null;

    #[ORM\Column(length: 25, nullable: true)]
        #[Assert\Regex(
        pattern: '/^\+?[0-9\s().-]{6,25}$/',
        message: 'The phone number format is invalid.'
    )]
    private ?string $tel1 = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?array $extra = null;

    #[ORM\OneToOne(inversedBy: 'profile', cascade: ['persist', 'remove'])]
    private ?User $user = null;



    public function __serialize(): array
{
    $data = get_object_vars($this);
    unset($data['imageFile']); // prevent File object serialization
    return $data;
}

public function __unserialize(array $data): void
{
    foreach ($data as $key => $value) {
        $this->$key = $value;
    }
}


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getWebsite1(): ?string
    {
        return $this->website1;
    }

    public function setWebsite1(?string $website1): static
    {
        $this->website1 = $website1;

        return $this;
    }

    public function getWebsite2(): ?string
    {
        return $this->website2;
    }

    public function setWebsite2(?string $website2): static
    {
        $this->website2 = $website2;

        return $this;
    }

    public function getWebsite3(): ?string
    {
        return $this->website3;
    }

    public function setWebsite3(?string $website3): static
    {
        $this->website3 = $website3;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function setImageFile(?File $imageFile = null): void
    {   
        $this->imageFile = $imageFile;

        if ($imageFile) {
            // Update a timestamp to force Doctrine update
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getPostalMail(): ?string
    {
        return $this->postalMail;
    }

    public function setPostalMail(?string $postalMail): static
    {
        $this->postalMail = $postalMail;

        return $this;
    }

    public function getTel1(): ?string
    {
        return $this->tel1;
    }

    public function setTel1(?string $tel1): static
    {
        $this->tel1 = $tel1;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }

    public function setExtra(?array $extra): static
    {
        $this->extra = $extra;

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
