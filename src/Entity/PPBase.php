<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PPBaseRepository;


use App\Entity\Traits\TimestampableTrait;
use Symfony\Component\HttpFoundation\File\File;

use App\Entity\Embeddables\PPBase\Extra;
use App\Entity\Embeddables\PPBase\OtherComponents;

use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: PPBaseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class PPBase
{

    use TimestampableTrait; 

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Project pages are identified with an unique sting identifier. It is randomized at the creation of the PPBase Object, it can later on be human readable and seo friendly (for example if project title is set, stringId becomes a slugified version of the title).
    */
    #[ORM\Column(length: 191, unique: true)]
    #[Assert\Length(min: 1, max: 191)]
    private ?string $stringId = null;


    #[ORM\Column(length: 400)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 255)]
    private ?string $goal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[Vich\UploadableField(mapping: 'project_logo_image', fileNameProperty: 'logo')]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
        maxSizeMessage: 'Le logo ne doit pas dépasser 5 Mo.'
    )]
    private ?File $logoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customThumbnail = null;

    #[Vich\UploadableField(mapping: 'project_custom_thumbnail_image', fileNameProperty: 'customThumbnail')]
    #[Assert\Image(
        maxSize: '5.5M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
        maxSizeMessage: 'La vignette ne doit pas dépasser 5,5 Mo.'
    )]
    private ?File $customThumbnailFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $keywords = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textDescription = null;

    #[ORM\Embedded(class: OtherComponents::class)]
    private OtherComponents $otherComponents;

    #[ORM\Embedded(class: Extra::class)]
    private Extra $extra;

    #[ORM\Column]
    private bool $isAdminValidated = false;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column(nullable: true)]
    private ?bool $isDeleted = false;


    // ------------------ Lifecycle callbacks ------------------

    #[ORM\PrePersist]
    public function generateStringId(): void
    {
        if (!$this->stringId) {
            $this->stringId = base_convert(time() - random_int(0, 10000), 10, 36);
        }
    }


    // ------------------ Getters / setters ------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function setGoal(string $goal): self
    {
        $this->goal = $goal;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }

    public function setLogoFile(?File $logoFile): void
    {
        $this->logoFile = $logoFile;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    public function getCustomThumbnail(): ?string
    {
        return $this->customThumbnail;
    }

    public function setCustomThumbnail(?string $customThumbnail): self
    {
        $this->customThumbnail = $customThumbnail;
        return $this;
    }

    public function setCustomThumbnailFile(?File $customThumbnailFile): void
    {
        $this->customThumbnailFile = $customThumbnailFile;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCustomThumbnailFile(): ?File
    {
        return $this->customThumbnailFile;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function getTextDescription(): ?string
    {
        return $this->textDescription;
    }

    public function setTextDescription(?string $textDescription): self
    {
        $this->textDescription = $textDescription;
        return $this;
    }

    public function isAdminValidated(): bool
    {
        return $this->isAdminValidated;
    }

    public function setIsAdminValidated(bool $validated): self
    {
        $this->isAdminValidated = $validated;
        return $this;
    }


    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $published): self
    {
        $this->isPublished = $published;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted ?? false;
    }

    public function setIsDeleted(?bool $deleted): self
    {
        $this->isDeleted = $deleted;
        return $this;
    }


    public function getStringId(): ?string
    {
        return $this->stringId;
    }

    public function setStringId(?string $stringId): self
    {
        $this->stringId = $stringId;
        return $this;
    }



}
