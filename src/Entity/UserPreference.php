<?php

namespace App\Entity;

use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preferences')]
#[ORM\HasLifecycleCallbacks]
class UserPreference
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * @var array<string,float>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $favCategories = null;

    /**
     * @var array<string,float>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $favKeywords = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touchTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return array<string,float>
     */
    public function getFavCategories(): array
    {
        if (!is_array($this->favCategories)) {
            return [];
        }

        return $this->favCategories;
    }

    /**
     * @param array<string,float> $favCategories
     */
    public function setFavCategories(array $favCategories): self
    {
        $this->favCategories = $favCategories === [] ? null : $favCategories;

        return $this;
    }

    /**
     * @return array<string,float>
     */
    public function getFavKeywords(): array
    {
        if (!is_array($this->favKeywords)) {
            return [];
        }

        return $this->favKeywords;
    }

    /**
     * @param array<string,float> $favKeywords
     */
    public function setFavKeywords(array $favKeywords): self
    {
        $this->favKeywords = $favKeywords === [] ? null : $favKeywords;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function refreshUpdatedAt(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
