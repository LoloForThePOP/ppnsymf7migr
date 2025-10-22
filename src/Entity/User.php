<?php

namespace App\Entity;

use App\Repository\UserRepository;
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
 */

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
    

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

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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



    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->isActive = true;
        $this->isVerified = false;
        $this->createdAt = new \DateTimeImmutable();
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

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
}
