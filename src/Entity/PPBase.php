<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\ProjectStatuses;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PPBaseRepository;


use App\Entity\Embeddables\PPBase\Extra;
use App\Entity\Embeddables\PPBase\IngestionMetadata;
use App\Entity\Traits\TimestampableTrait;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Embeddables\PPBase\OtherComponents;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;



/**
 * The PPBase entity is automatically processed by:
 * - App\EventListener\SlugGenerationListener
 *   → ensures unique $stringId (i.e. slug) before persist/update 
 */

#[ORM\Entity(repositoryClass: PPBaseRepository::class)]
#[ORM\Table(
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_pp_ing_source_url', columns: ['ing_source_url'])
    ]
)]
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
    #[ORM\Column(length: 190, unique: true)]
    #[Assert\Length(min: 1, max: 190)]
    private ?string $stringId = null;

    // ────────────────────────────────────────
    // Logo (VichUploader)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[Vich\UploadableField(mapping: 'project_logo_image', fileNameProperty: 'logo')]
    #[Assert\Image(
        maxSize: '10M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/avif'],
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
        maxSize: '10M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/avif'],
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

    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    // Free-text keywords, separated with commas, often filled from tags or user input
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $keywords = null;

    // Language of the source presentation (e.g. 'fr')
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $originLanguage = null;

    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textDescription = null;

    // Other components are Project Presentation websites, faq, business cards, and can be extended in the future.

    #[ORM\Embedded(class: OtherComponents::class)]
    private OtherComponents $otherComponents;


    // ────────────────────────────────────────
    // Other fields
    // ────────────────────────────────────────

    #[ORM\Column]
    private bool $isAdminValidated = false;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column(nullable: true)]
    private ?bool $isDeleted = false;

    #[ORM\Column(nullable: true)]
    private ?bool $isCreationFormCompleted = null;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['default' => 0])]
    private ?int $score = null;

    // Some other info about the project presentation,, like views count, stored in an embeddable for clarity.  
    #[ORM\Embedded(class: Extra::class)]
    private Extra $extra;

    // Scraper-only metadata kept separate from core fields (project presentations scrapped from web info, if null then project was human-submitted)
    #[ORM\Embedded(class: IngestionMetadata::class, columnPrefix: 'ing_')]
    private ?IngestionMetadata $ingestion = null;



    // ────────────────────────────────────────
    // Relations Core Presentation Components (slides, needs, news, places, documents...)
    // ────────────────────────────────────────

    /**
     * @var Collection<int, Slide>
     */
    
    #[ORM\OneToMany(
        mappedBy: 'projectPresentation',
        targetEntity: Slide::class,
        cascade: ['persist', 'remove'], 
        orphanRemoval: true           
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Count(
        max: 8,
        maxMessage: 'Vous ne pouvez ajouter plus de {{ limit }} diapositives merci pour votre compréhension.'
    )]
    private Collection $slides;


    /**
     * @var Collection<int, News>
     */
    #[ORM\OneToMany(targetEntity: News::class, mappedBy: 'project')]
    #[ORM\OrderBy(['id' => 'DESC'])]
    private Collection $news;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'projectPresentation')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $documents;

    /**
     * @var Collection<int, Need>
     */
    #[ORM\OneToMany(targetEntity: Need::class, mappedBy: 'project')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $needs;

    /**
     * @var Collection<int, Place>
     */
    #[ORM\OneToMany(targetEntity: Place::class, mappedBy: 'project')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $places;


    /**
     * Project statuses (progress) with enum variables (ProjectStatuses class)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $statuses = [];

    /**
     * Project status as described / detailed by user with free text
     */
    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $statusRemarks = null;

    // ────────────────────────────────────────
    // Others Relations
    // ────────────────────────────────────────

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'projectPresentation')]
    private Collection $comments;

    #[ORM\ManyToOne(inversedBy: 'projectPresentations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    /**
     * @var Collection<int, Follow>
     */
    #[ORM\OneToMany(targetEntity: Follow::class, mappedBy: 'projectPresentation')]
    private Collection $followers;

    /**
     * @var Collection<int, Like>
     */
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'projectPresentation')]
    private Collection $likes;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'projectPresentation')]
    private Collection $categories;





    // ────────────────────────────────────────
    // Lifecycle
    // ────────────────────────────────────────

    public function __construct()
    {   $this->extra = new Extra();
        $this->otherComponents = new OtherComponents();
        $this->ingestion = new IngestionMetadata();
        $this->comments = new ArrayCollection();
        $this->slides = new ArrayCollection();
        $this->needs = new ArrayCollection();
        $this->news = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->places = new ArrayCollection();
    }

    public function getProjectPresentation(): self
    {
        return $this;
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

    public function getOriginLanguage(): ?string
    {
        return $this->originLanguage;
    }

    public function setOriginLanguage(?string $originLanguage): self
    {
        $this->originLanguage = $originLanguage;
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

  
    /**
     * @return Collection<int, News>
     */
    public function getNews(): Collection
    {
        return $this->news;
    }

    public function addNews(News $news): static
    {
        if (!$this->news->contains($news)) {
            $this->news->add($news);
            $news->setProject($this);
        }

        return $this;
    }

    public function removeNews(News $news): static
    {
        if ($this->news->removeElement($news)) {
            // set the owning side to null (unless already changed)
            if ($news->getProject() === $this) {
                $news->setProject(null);
            }
        }

        return $this;
    }


    public function getStatuses(): array
    {
        return $this->statuses ?? [];
    }

    public function setStatuses(array $statuses): self
    {
        // Optional validation: filter only valid keys
        $validKeys = [];
        foreach ($statuses as $status) {
            if (ProjectStatuses::get($status)) {
                $validKeys[] = $status;
            }
        }

        $this->statuses = array_unique($validKeys);

        return $this;
    }

    public function addStatus(string $status): self
    {
        if (ProjectStatuses::get($status) && !in_array($status, $this->statuses ?? [], true)) {
            $this->statuses[] = $status;
        }
        return $this;
    }

    public function removeStatus(string $status): self
    {
        $this->statuses = array_filter(
            $this->statuses ?? [],
            fn ($s) => $s !== $status
        );
        return $this;
    }

    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->statuses ?? [], true);
    }



    public function getEnumStatusItems(): array
    {
        $items = [];
        foreach ($this->getStatuses() as $statusKey) {
            $statusItem = ProjectStatuses::get($statusKey);
            if ($statusItem) {
                $items[$statusKey] = $statusItem;
            }
        }
        return $items;
    }



    public function getStatusRemarks(): ?string
    {
        return $this->statusRemarks;
    }

    public function setStatusRemarks(?string $statusRemarks): static
    {
        $this->statusRemarks = $statusRemarks;

        return $this;
    }





    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setProjectPresentation($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getProjectPresentation() === $this) {
                $document->setProjectPresentation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Follow>
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(Follow $follower): static
    {
        if (!$this->followers->contains($follower)) {
            $this->followers->add($follower);
            $follower->setProjectPresentation($this);
        }

        return $this;
    }

    public function removeFollower(Follow $follower): static
    {
        if ($this->followers->removeElement($follower)) {
            // set the owning side to null (unless already changed)
            if ($follower->getProjectPresentation() === $this) {
                $follower->setProjectPresentation(null);
            }
        }

        return $this;
    }


    /**
     * Returns number of followers (computed from collection).
     */
    public function getFollowerCount(): int
    {
        return $this->followers->count();
    }

    /**
     * Checks whether a specific user follows this project.
     */
    public function isFollowedBy(User $user): bool
    {
        foreach ($this->followers as $follow) {
            if ($follow->getUser() === $user) {
                return true;
            }
        }
        return false;
    }




    /**
     * Toggle the follow relationship for a user.
     * Returns the Follow entity to persist/remove, or null.
     */
    public function toggleFollowEntity(User $user): ?Follow
    {
        foreach ($this->followers as $follow) {

            if ($follow->getUser() === $user) { //so we unfollow presentation
                $this->removeFollower($follow); 
                return $follow; // Return so the caller can remove it
            }

        }

        // Means “follow”
        $follow = new Follow();
        $follow->setUser($user);
        $this->addFollower($follow);

        return $follow;
    }

    /**
     * @return Collection<int, Like>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setProjectPresentation($this);
        }

        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            // set the owning side to null (unless already changed)
            if ($like->getProjectPresentation() === $this) {
                $like->setProjectPresentation(null);
            }
        }

        return $this;
    }


    /**
     * Returns total number of likes for the project.
     */
    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    /**
     * Check if this project is liked by a specific user.
     */
    public function isLikedBy(User $user): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user) {
                return true;
            }
        }
        return false;
    }
    

    public function isCreationFormCompleted(): ?bool
    {
        return $this->isCreationFormCompleted;
    }

    public function setIsCreationFormCompleted(?bool $isCreationFormCompleted): static
    {
        $this->isCreationFormCompleted = $isCreationFormCompleted;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addProjectPresentation($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeProjectPresentation($this);
        }

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getOtherComponents(): OtherComponents
    {
        return $this->otherComponents;
    }

    public function setOtherComponents(OtherComponents $otherComponents): self
    {
        $this->otherComponents = $otherComponents;
        return $this;
    }

    public function getOCItem(string $type, string $id): ?array
    {
        return $this->otherComponents->getItem($type, $id);
    }

    public function setOCItem(string $type, string $id, array $data): void
    {
        if (!$this->otherComponents->replaceItem($type, $id, $data)) {
            throw new \InvalidArgumentException(sprintf('Impossible de mettre à jour l’élément "%s".', $id));
        }
    }


    public function getExtra(): Extra
    {
        return $this->extra;
    }

    public function setExtra(Extra $extra): self
    {
        $this->extra = $extra;
        return $this;
    }

    public function getIngestion(): IngestionMetadata
    {
        if ($this->ingestion === null) {
            $this->ingestion = new IngestionMetadata();
        }

        return $this->ingestion;
    }

    public function setIngestion(?IngestionMetadata $ingestion): self
    {
        $this->ingestion = $ingestion;
        return $this;
    }

    /**
     * @return Collection<int, Need>
     */
    public function getNeeds(): Collection
    {
        return $this->needs;
    }

    public function addNeed(Need $need): static
    {
        if (!$this->needs->contains($need)) {
            $this->needs->add($need);
            $need->setProject($this);
        }

        return $this;
    }

    public function removeNeed(Need $need): static
    {
        if ($this->needs->removeElement($need)) {
            // set the owning side to null (unless already changed)
            if ($need->getProject() === $this) {
                $need->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Place>
     */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    public function addPlace(Place $place): static
    {
        if (!$this->places->contains($place)) {
            $this->places->add($place);
            $place->setProject($this);
        }

        return $this;
    }

    public function removePlace(Place $place): static
    {
        if ($this->places->removeElement($place)) {
            // set the owning side to null (unless already changed)
            if ($place->getProject() === $this) {
                $place->setProject(null);
            }
        }

        return $this;
    }














}
