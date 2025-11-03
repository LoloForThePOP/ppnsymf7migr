<?php

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: NewsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class News implements \Stringable
{
    // ────────────────────────────────────────
    // Identity
    // ────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ────────────────────────────────────────
    // Timestamps
    // ────────────────────────────────────────

    use \App\Entity\Traits\TimestampableTrait;

    // ────────────────────────────────────────
    // Content
    // ────────────────────────────────────────


    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le texte de la news ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $textContent = null;


    // ────────────────────────────────────────
    // Images (Up to 3)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image3 = null;


    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La légende de l’image 1 ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $captionImage1 = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La légende de l’image 2 ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $captionImage2 = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La légende de l’image 3 ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $captionImage3 = null;

    #[Vich\UploadableField(mapping: 'news_image', fileNameProperty: 'image1')]
    #[Assert\Image(
        maxSize: '6500k',
        maxSizeMessage: 'Poids maximal accepté : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/apng', 'image/webp'],
        mimeTypesMessage: 'Format non pris en charge : {{ type }}. Formats autorisés : {{ types }}.'
    )]
    private ?File $image1File = null;

    #[Vich\UploadableField(mapping: 'news_image', fileNameProperty: 'image2')]
    #[Assert\Image(
        maxSize: '6500k',
        maxSizeMessage: 'Poids maximal accepté : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/apng', 'image/webp'],
        mimeTypesMessage: 'Format non pris en charge : {{ type }}. Formats autorisés : {{ types }}.'
    )]
    private ?File $image2File = null;

    #[Vich\UploadableField(mapping: 'news_image', fileNameProperty: 'image3')]
    #[Assert\Image(
        maxSize: '6500k',
        maxSizeMessage: 'Poids maximal accepté : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/apng', 'image/webp'],
        mimeTypesMessage: 'Format non pris en charge : {{ type }}. Formats autorisés : {{ types }}.'
    )]
    private ?File $image3File = null;

    #[ORM\ManyToOne(inversedBy: 'news')]
    private ?PPBase $project = null;

    #[ORM\OneToOne(inversedBy: 'news', cascade: ['persist', 'remove'])]
    private ?User $creator = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'news')]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }

    // ────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getTextContent(): ?string
    {
        return $this->textContent;
    }

    public function setTextContent(?string $textContent): self
    {
        $this->textContent = trim((string) $textContent);
        return $this;
    }


    // ────────────────────────────────────────
    // Images — unified pattern
    // ────────────────────────────────────────

    public function setImage1File(?File $file = null): void
    {
        $this->image1File = $file;
        if ($file !== null) $this->updatedAt = new \DateTimeImmutable();
    }

    public function getImage1File(): ?File
    {
        return $this->image1File;
    }

    public function getImage1(): ?string
    {
        return $this->image1;
    }

    public function setImage1(?string $image1): self
    {
        $this->image1 = $image1;
        return $this;
    }

    public function setImage2File(?File $file = null): void
    {
        $this->image2File = $file;
        if ($file !== null) $this->updatedAt = new \DateTimeImmutable();
    }

    public function getImage2File(): ?File
    {
        return $this->image2File;
    }

    public function getImage2(): ?string
    {
        return $this->image2;
    }

    public function setImage2(?string $image2): self
    {
        $this->image2 = $image2;
        return $this;
    }

    public function setImage3File(?File $file = null): void
    {
        $this->image3File = $file;
        if ($file !== null) $this->updatedAt = new \DateTimeImmutable();
    }

    public function getImage3File(): ?File
    {
        return $this->image3File;
    }

    public function getImage3(): ?string
    {
        return $this->image3;
    }

    public function setImage3(?string $image3): self
    {
        $this->image3 = $image3;
        return $this;
    }

    // ────────────────────────────────────────
    // Captions
    // ────────────────────────────────────────

    public function getCaptionImage1(): ?string
    {
        return $this->captionImage1;
    }

    public function setCaptionImage1(?string $caption): self
    {
        $this->captionImage1 = $caption;
        return $this;
    }

    public function getCaptionImage2(): ?string
    {
        return $this->captionImage2;
    }

    public function setCaptionImage2(?string $caption): self
    {
        $this->captionImage2 = $caption;
        return $this;
    }

    public function getCaptionImage3(): ?string
    {
        return $this->captionImage3;
    }

    public function setCaptionImage3(?string $caption): self
    {
        $this->captionImage3 = $caption;
        return $this;
    }


    // ────────────────────────────────────────
    // Misc
    // ────────────────────────────────────────

    public function __toString(): string
    {
        return sprintf('News #%d', $this->id ?? 0);
    }

    // ────────────────────────────────────────
    // Relations   
    // ────────────────────────────────────────


    public function getProject(): ?PPBase
    {
        return $this->project;
    }

    public function setProject(?PPBase $project): static
    {
        $this->project = $project;

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
            $comment->setNews($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getNews() === $this) {
                $comment->setNews(null);
            }
        }

        return $this;
    }

}
