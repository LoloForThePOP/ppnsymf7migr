<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Enum\CommentStatus;
use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Comment
{

    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\Length(
        min: 2,
        max: 2500,
        minMessage: 'Le commentaire doit contenir au minimum {{ limit }} caractères',
        maxMessage: 'Le commentaire doit contenir au maximum {{ limit }} caractères'
    )]
    private ?string $content = null;

    #[ORM\Column(enumType: CommentStatus::class)]
    private CommentStatus $status = CommentStatus::Pending;


    // ────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────

    // Inner Relations (parent comment / child comments)

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $replies;


    // Outer Relations (commented entities and users)

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $repliedUser = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    private ?Article $article = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    private ?PPBase $projectPresentation = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    private ?User $creator = null;


    // ────────────────────────────────────────
    // Lifecycle
    // ────────────────────────────────────────

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }



    // ────────────────────────────────────────
    // Getters / Setters
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);
        return $this;
    }

    public function getStatus(): CommentStatus
    {
        return $this->status;
    }

    public function setStatus(CommentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === CommentStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === CommentStatus::Rejected;
    }


    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(self $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }
        return $this;
    }

    public function removeReply(self $reply): self
    {
        if ($this->replies->removeElement($reply) && $reply->getParent() === $this) {
            $reply->setParent(null);
        }
        return $this;
    }

    public function getRepliedUser(): ?User
    {
        return $this->repliedUser;
    }

    public function setRepliedUser(?User $repliedUser): self
    {
        $this->repliedUser = $repliedUser;
        return $this;
    }


    // ────────────────────────────────────────
    // Logic helpers
    // ────────────────────────────────────────

    public function isCreatedByEntityOwner(string $entity): bool
    {
        return match ($entity) {
            //to fill 'projectPresentation' => $this->projectPresentation?->getCreator() === $this->user,
            //'article'             => $this->article?->getAuthor() === $this->user,
            default                => false,
        };
    }

    public function getCommentedEntityType(): string
    {
        return match (true) {
            //to fill $this->projectPresentation !== null => 'projectPresentation',
            //$this->article !== null             => 'article',
            //$this->news !== null                => 'news',
            default                             => throw new \LogicException('Unknown commented entity type.'),
        };
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getProjectPresentation(): ?PPBase
    {
        return $this->projectPresentation;
    }

    public function setProjectPresentation(?PPBase $projectPresentation): static
    {
        $this->projectPresentation = $projectPresentation;

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



}
