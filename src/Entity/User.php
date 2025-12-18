<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Component\Validator\Constraints as Assert;


use App\Entity\Traits\TimestampableTrait;   
use Symfony\Component\Security\Core\User\EquatableInterface;


/**
 * The User entity is automatically processed by:
 * - App\EventListener\UsernameListener
 *   → ensures unique username and generates usernameSlug before persist/update
 * - App\Service\ProfileCreatorListener
 *  → ensures a Profile entity is created for each new User before persist      
 */

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{

    use TimestampableTrait; 
    

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Email cannot be empty.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot exceed {{ limit }} characters.')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    #[Assert\Type('bool')]
    #[Assert\NotNull(message: 'isVerified must not be null.')]
    private ?bool $isVerified = null;

    #[ORM\Column]
    #[Assert\Type('bool')]
    #[Assert\NotNull(message: 'isActive must not be null.')]
    private ?bool $isActive = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailValidationToken = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'Username cannot be empty.')]
    #[Assert\Length(min: 2, max: 40, minMessage: 'Username must be at least {{ limit }} characters long.')]
    #[Assert\Regex(
        pattern: '/^[\p{L}\p{N}\s._-]+$/u',
        message: 'Username can only contain letters, numbers, spaces, dots, underscores, and dashes.'
    )]
    private ?string $username = null;

    #[ORM\Column(length: 120)]
    private ?string $usernameSlug = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $resetPasswordToken = null;


    // ────────────────────────────────────────
    // Relations
    // ────────────────────────────────────────

    /**
     * @var Collection<int, PPBase>
     */
    #[ORM\OneToMany(targetEntity: PPBase::class, mappedBy: 'creator')]
    private Collection $projectPresentations;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'creator')]
    private Collection $comments;

    #[ORM\OneToOne(mappedBy: 'creator', cascade: ['persist', 'remove'])]
    private ?News $news = null;

    /**
     * @var Collection<int, Follow>
     */
    #[ORM\OneToMany(targetEntity: Follow::class, mappedBy: 'user')]
    private Collection $follows;

    /**
     * @var Collection<int, Like>
     */
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'user')]
    private Collection $likes;

    /**
     * @var Collection<int, ConversationParticipant>
     */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'user')]
    private Collection $conversationParticipations;

    /**
     * @var Collection<int, ConversationMessage>
     */
    #[ORM\OneToMany(targetEntity: ConversationMessage::class, mappedBy: 'sender')]
    private Collection $conversationMessages;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'creator')]
    private Collection $articles;



    public function __construct()
    {   
        $this->roles = ['ROLE_USER'];
        $this->isActive = true;
        $this->isVerified = false;
        $this->projectPresentations = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->follows = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->conversationParticipations = new ArrayCollection();
        $this->conversationMessages = new ArrayCollection();
        $this->articles = new ArrayCollection();
        
    }



    // Had to add this because Social Login didn't work, Symfony thinks the database-reloaded user differs from the session user.

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        // Only compare immutable identifying fields
        return $this->getUserIdentifier() === $user->getUserIdentifier();
    }

    // ────────────────────────────────────────
    // Serializable Interface Implementation
    // ────────────────────────────────────────

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'password' => $this->password,
        ];
    }

    public function __unserialize(array $data): void
    {
        if (!array_key_exists('id', $data)) {
            $normalized = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && str_contains($key, "\0")) {
                    $parts = explode("\0", $key);
                    $key = end($parts);
                }
                $normalized[$key] = $value;
            }
            $data = $normalized;
        }

        $this->id = $data['id'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->roles = $data['roles'] ?? [];
        $this->password = $data['password'] ?? null;
    }




    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }


    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): static
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): static
    {
        $this->facebookId = $facebookId;

        return $this;
    }


    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getEmailValidationToken(): ?string
    {
        return $this->emailValidationToken;
    }

    public function setEmailValidationToken(?string $emailValidationToken): static
    {
        $this->emailValidationToken = $emailValidationToken;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUsernameSlug(): ?string
    {
        return $this->usernameSlug;
    }

    public function setUsernameSlug(string $usernameSlug): static
    {
        $this->usernameSlug = $usernameSlug;

        return $this;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        // unset the owning side of the relation if necessary
        if ($profile === null && $this->profile !== null) {
            $this->profile->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }

        $this->profile = $profile;

        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;

        return $this;
    }

    /**
     * @return Collection<int, PPBase>
     */
    public function getProjectPresentations(): Collection
    {
        return $this->projectPresentations;
    }

    public function addProjectPresentation(PPBase $projectPresentation): static
    {
        if (!$this->projectPresentations->contains($projectPresentation)) {
            $this->projectPresentations->add($projectPresentation);
            $projectPresentation->setCreator($this);
        }

        return $this;
    }

    public function removeProjectPresentation(PPBase $projectPresentation): static
    {
        if ($this->projectPresentations->removeElement($projectPresentation)) {
            // set the owning side to null (unless already changed)
            if ($projectPresentation->getCreator() === $this) {
                $projectPresentation->setCreator(null);
            }
        }

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
            $comment->setCreator($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getCreator() === $this) {
                $comment->setCreator(null);
            }
        }

        return $this;
    }

    public function getNews(): ?News
    {
        return $this->news;
    }

    public function setNews(?News $news): static
    {
        // unset the owning side of the relation if necessary
        if ($news === null && $this->news !== null) {
            $this->news->setCreator(null);
        }

        // set the owning side of the relation if necessary
        if ($news !== null && $news->getCreator() !== $this) {
            $news->setCreator($this);
        }

        $this->news = $news;

        return $this;
    }

    /**
     * @return Collection<int, Follow>
     */
    public function getFollows(): Collection
    {
        return $this->follows;
    }

    public function addFollow(Follow $follow): static
    {
        if (!$this->follows->contains($follow)) {
            $this->follows->add($follow);
            $follow->setUser($this);
        }

        return $this;
    }

    public function removeFollow(Follow $follow): static
    {
        if ($this->follows->removeElement($follow)) {
            // set the owning side to null (unless already changed)
            if ($follow->getUser() === $this) {
                $follow->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Returns true if the user follows a given project.
     */
    public function isFollowingProject(PPBase $project): bool
    {
        foreach ($this->follows as $follow) {
            if ($follow->getProjectPresentation() === $project) {
                return true;
            }
        }
        return false;
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
            $like->setUser($this);
        }

        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            // set the owning side to null (unless already changed)
            if ($like->getUser() === $this) {
                $like->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Check if user has liked a given project.
     */
    public function hasLikedProject(PPBase $project): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getProjectPresentation() === $project) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Collection<int, ConversationParticipant>
     */
    public function getConversationParticipations(): Collection
    {
        return $this->conversationParticipations;
    }

    public function addConversationParticipation(ConversationParticipant $conversationParticipation): static
    {
        if (!$this->conversationParticipations->contains($conversationParticipation)) {
            $this->conversationParticipations->add($conversationParticipation);
            $conversationParticipation->setUser($this);
        }

        return $this;
    }

    public function removeConversationParticipation(ConversationParticipant $conversationParticipation): static
    {
        if ($this->conversationParticipations->removeElement($conversationParticipation)) {
            // set the owning side to null (unless already changed)
            if ($conversationParticipation->getUser() === $this) {
                $conversationParticipation->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function getConversationMessages(): Collection
    {
        return $this->conversationMessages;
    }

    public function addConversationMessage(ConversationMessage $conversationMessage): static
    {
        if (!$this->conversationMessages->contains($conversationMessage)) {
            $this->conversationMessages->add($conversationMessage);
            $conversationMessage->setSender($this);
        }

        return $this;
    }

    public function removeConversationMessage(ConversationMessage $conversationMessage): static
    {
        if ($this->conversationMessages->removeElement($conversationMessage)) {
            // set the owning side to null (unless already changed)
            if ($conversationMessage->getSender() === $this) {
                $conversationMessage->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setCreator($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getCreator() === $this) {
                $article->setCreator(null);
            }
        }

        return $this;
    }

}
