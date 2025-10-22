<?php
namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
class UsernameListener
{
    public function __construct(
        private UserRepository $userRepository,
        private SluggerInterface $slugger
    ) {}

    public function prePersist(User $user, PrePersistEventArgs $args): void
    {
        $this->generateUniqueUsernameAndSlug($user);
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        // Only regenerate if username has changed
        $changes = $args->getEntityChangeSet();

        if (array_key_exists('username', $changes)) {
            $this->generateUniqueUsernameAndSlug($user);
        }
    }

    private function generateUniqueUsernameAndSlug(User $user): void
    {
        // --- 1. Ensure unique username ---
        $baseUsername = trim($user->getUsername());
        $username = $baseUsername;
        $i = 1;

        while ($existing = $this->userRepository->findOneBy(['username' => $username])) {
            if ($existing->getId() === $user->getId()) {
                break; // same user; skip
            }
            $username = sprintf('%s %d', $baseUsername, $i++);
        }

        $user->setUsername($username);

        // --- 2. Ensure unique slug ---
        $baseSlug = strtolower($this->slugger->slug($username)->toString());
        $slug = $baseSlug;
        $i = 1;

        while ($existing = $this->userRepository->findOneBy(['usernameSlug' => $slug])) {
            if ($existing->getId() === $user->getId()) {
                break; // same user
            }
            $slug = sprintf('%s-%d', $baseSlug, $i++);
        }

        $user->setUsernameSlug($slug);
    }
}
