<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    // ────────────────────────────────────────
    // Integer Primary Key
    // ────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    // ────────────────────────────────────────
    // String Unique Key
    // ────────────────────────────────────────

    /**
     * Project presentation pages are identified with an unique sting identifier. It is randomized at the creation of the PPBase Object, it can later on be human readable and seo friendly (for example if project title is set, stringId becomes a slugified version of the title).
    */
    #[ORM\Column(length: 191, unique: true)]
    #[Assert\Length(min: 1, max: 191)]
    private ?string $stringId = null;

    // ────────────────────────────────────────
    // Logo (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[Vich\UploadableField(mapping: 'project_logo_image', fileNameProperty: 'logo')]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
        maxSizeMessage: 'Le logo ne doit pas dépasser 5 Mo.'
    )]
    private ?File $logoFile = null;


    // ────────────────────────────────────────
    // Thumbnail (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customThumbnail = null;

    #[Vich\UploadableField(mapping: 'project_custom_thumbnail_image', fileNameProperty: 'customThumbnail')]
    #[Assert\Image(
        maxSize: '5.5M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
        maxSizeMessage: 'La vignette ne doit pas dépasser 5,5 Mo.'
    )]
    private ?File $customThumbnailFile = null;


    // ────────────────────────────────────────
    // Core Fields 
    // ────────────────────────────────────────
    
    #[ORM\Column(length: 400)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 255)]
    private ?string $goal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $keywords = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textDescription = null;

    // Other components are Project Presentation websites, faq, business cards, and can be extended in the future.

    #[ORM\Embedded(class: OtherComponents::class)]
    private OtherComponents $otherComponents;


    // ────────────────────────────────────────
    // Thumbnail (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Embedded(class: Extra::class)]
    private Extra $extra;

    #[ORM\Column]
    private bool $isAdminValidated = false;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column(nullable: true)]
    private ?bool $isDeleted = false;



    // ────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'projectPresentation')]
    private Collection $comments;

    #[ORM\ManyToOne(inversedBy: 'projectPresentations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    // ────────────────────────────────────────
    // Relations Core Components
    // ────────────────────────────────────────

    /**
     * @var Collection<int, Slide>
     */
    #[ORM\OneToMany(targetEntity: Slide::class, mappedBy: 'projectPresentation')]
    private Collection $slides;


    // ────────────────────────────────────────
    // Lifecycle
    // ────────────────────────────────────────

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->slides = new ArrayCollection();
    }


    #[ORM\PrePersist]
    public function generateStringId(): void
    {
        if (!$this->stringId) {
            $this->stringId = base_convert(time() - random_int(0, 10000), 10, 36);
        }
    }


    // ────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────

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

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setProjectPresentation($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getProjectPresentation() === $this) {
                $comment->setProjectPresentation(null);
            }
        }

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * @return Collection<int, Slide>
     */
    public function getSlides(): Collection
    {
        return $this->slides;
    }

    public function addSlide(Slide $slide): static
    {
        if (!$this->slides->contains($slide)) {
            $this->slides->add($slide);
            $slide->setProjectPresentation($this);
        }

        return $this;
    }

    public function removeSlide(Slide $slide): static
    {
        if ($this->slides->removeElement($slide)) {
            // set the owning side to null (unless already changed)
            if ($slide->getProjectPresentation() === $this) {
                $slide->setProjectPresentation(null);
            }
        }

        return $this;
    }



}
