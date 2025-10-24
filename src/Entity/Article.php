<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ArticleRepository;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;
use Doctrine\Common\Collections\ArrayCollection;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]

class Article
{
    // ────────────────────────────────────────
    // Primary Key
    // ────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    use TimestampableTrait;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isValidated = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column]
    private ?int $viewsCount = null;

    // ────────────────────────────────────────
    // Thumbnail (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[Vich\UploadableField(mapping: 'article_thumbnail', fileNameProperty: 'thumbnail')]
    #[Assert\Image(
        maxSize: '5500k',
        maxSizeMessage: 'Poids maximal accepté pour l’image : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif'],
        mimeTypesMessage: 'Le format de fichier ({{ type }}) n’est pas pris en charge. Formats acceptés : {{ types }}.'
    )]
    private ?File $thumbnailFile = null;


    // ────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────

   #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    private ?User $author = null;

   /**
    * @var Collection<int, Comment>
    */
   #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'article', cascade: ['remove'])]
   private Collection $comments;


    // ────────────────────────────────────────
    // Lifecycle
    // ────────────────────────────────────────

    public function __construct()
    {
    
        $this->viewsCount = 0;
        $this->comments = new ArrayCollection(); 
    }


    // ────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $validated): self
    {
        $this->isValidated = $validated;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    // ────────────────────────────────────────
    // Thumbnail Methods
    // ────────────────────────────────────────

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getThumbnailFile(): ?File
    {
        return $this->thumbnailFile;
    }

    public function setThumbnailFile(?File $file = null): void
    {
        $this->thumbnailFile = $file;

        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    
   

    public function getViewsCount(): ?int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;

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
            $comment->setArticle($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getArticle() === $this) {
                $comment->setArticle(null);
            }
        }

        return $this;
    }

    


}
