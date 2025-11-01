<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\Profile;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
class ProfileCreatorListener
{
    public function prePersist(User $user, PrePersistEventArgs $event): void
    {
        // User already has a profile → do nothing
        if ($user->getProfile()) {
            return;
        }

        $profile = new Profile();
        $profile->setUser($user);
        $user->setProfile($profile);
    }
}
