<?php

namespace App\Entity;

use App\Enum\SlideType;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SlideRepository;
use App\Entity\Traits\TimestampableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SlideRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Slide
{
    private const YOUTUBE_URL_PATTERN = "/^(?:http(?:s)?:\\/\\/)?(?:www\\.)?(?:m\\.)?(?:youtu\\.be\\/|youtube\\.com\\/(?:(?:watch)?\\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\\/))([^\\?&\"'>]+)/";
    use TimestampableTrait;

    // ────────────────────────────────────────
    // Identity
    // ────────────────────────────────────────
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ────────────────────────────────────────
    // Type
    // ────────────────────────────────────────
    #[ORM\Column(length: 30, enumType: SlideType::class)]
    private ?SlideType $type = null;

    // ────────────────────────────────────────
    // Image-specific fields
    // ────────────────────────────────────────


    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    
    #[Vich\UploadableField(
        mapping: 'presentation_slide_file',
        fileNameProperty: 'imagePath',
        mimeType: 'mimeType'
    )]
    #[Assert\NotNull(groups: ['file_required'], message: 'Veuillez choisir une image.')]
    #[Assert\Image(
        maxSize: '4500k',
        maxSizeMessage: 'Poids maximal accepté : {{ limit }} {{ suffix }}.',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/apng', 'image/webp', 'image/avif'],
        mimeTypesMessage: 'Le format ({{ type }}) n’est pas pris en charge. Formats acceptés : {{ types }}.'
    )]
    private ?File $imageFile = null;

    // ────────────────────────────────────────
    // Video-specific fields
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Veuillez saisir une adresse YouTube valide.', requireTld: false)]
    #[Assert\Regex(
        pattern: self::YOUTUBE_URL_PATTERN,
        message: 'Le lien doit être un lien YouTube valide.'
    )]
    private ?string $youtubeUrl = null;

    // ────────────────────────────────────────
    // Common content fields
    // ────────────────────────────────────────

    #[ORM\Column(length: 400, nullable: true)]
    #[Assert\Length(max: 400, maxMessage: 'La légende ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $caption = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Les crédits ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $licence = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\PositiveOrZero(message: 'La position doit être positive ou nulle.')]
    private ?int $position = null;

    // ────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────
    #[ORM\ManyToOne(inversedBy: 'slides')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?PPBase $projectPresentation = null;

    // ────────────────────────────────────────
    // Lifecycle
    // ────────────────────────────────────────
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────
    public function isImage(): bool
    {
        return $this->type === SlideType::IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->type === SlideType::YOUTUBE_VIDEO;
    }

    // ────────────────────────────────────────
    // Getters & Setters
    // ────────────────────────────────────────
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?SlideType
    {
        return $this->type;
    }

    public function setType(?SlideType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): void
    {
        $this->imageFile = $imageFile;
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): self
    {
        $this->youtubeUrl = $youtubeUrl;
        return $this;
    }

    public function getYoutubeVideoId(): ?string
    {
        if ($this->youtubeUrl === null) {
            return null;
        }

        if (preg_match(self::YOUTUBE_URL_PATTERN, $this->youtubeUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Legacy helper for templates still using slide.address
     */
    public function getAddress(): ?string
    {
        return $this->getYoutubeVideoId();
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
        return $this;
    }

    public function getLicence(): ?string
    {
        return $this->licence;
    }

    public function setLicence(?string $licence): self
    {
        $this->licence = $licence;
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

    public function getProjectPresentation(): ?PPBase
    {
        return $this->projectPresentation;
    }

    public function setProjectPresentation(?PPBase $projectPresentation): self
    {
        $this->projectPresentation = $projectPresentation;
        return $this;
    }



}
